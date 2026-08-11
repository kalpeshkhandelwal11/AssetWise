<?php

namespace Tests\Feature\Depreciation;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\DepreciationMethod;
use App\Models\DepreciationSettingRequest;
use App\Services\Depreciation\StraightLineCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class DepreciationApprovalTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function depreciationWorkflow(): ApprovalWorkflow
    {
        $this->seedRolesAndPermissions();
        $workflow = ApprovalWorkflow::factory()->module('depreciation')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        return $workflow;
    }

    private function straightLine(): DepreciationMethod
    {
        return DepreciationMethod::firstOrCreate(
            ['code' => 'straight_line'],
            ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
        );
    }

    private function payload(Asset $asset, DepreciationMethod $method): array
    {
        return [
            'depreciation_method_id' => $method->id,
            'useful_life_months'     => 60,
            'salvage_percent'        => 5,
            'start_date'             => '2024-01-01',
            'cost_basis'             => 100000,
        ];
    }

    public function test_update_requires_depreciation_manage(): void
    {
        $this->depreciationWorkflow();
        $method = $this->straightLine();
        $asset = Asset::factory()->create();
        $viewer = $this->createUserWithRole('Viewer'); // depreciation.view only

        $this->actingAs($viewer)
            ->put(route('assets.depreciation.update', $asset), $this->payload($asset, $method))
            ->assertForbidden();
    }

    public function test_submit_fails_without_a_configured_workflow(): void
    {
        $this->seedRolesAndPermissions(); // no depreciation workflow
        $method = $this->straightLine();
        $asset = Asset::factory()->create();
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)
            ->put(route('assets.depreciation.update', $asset), $this->payload($asset, $method))
            ->assertSessionHasErrors('workflow');

        $this->assertDatabaseCount('depreciation_setting_requests', 0);
    }

    public function test_approved_change_creates_the_active_setting_and_schedule(): void
    {
        $this->depreciationWorkflow();
        $method = $this->straightLine();
        $asset = Asset::factory()->create();
        $manager = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');

        $this->actingAs($manager)
            ->put(route('assets.depreciation.update', $asset), $this->payload($asset, $method))
            ->assertRedirect(route('assets.show', $asset));

        $request = DepreciationSettingRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame('pending_approval', $request->status);
        $this->assertNotNull($request->approval_request_id);
        $this->assertNull($asset->fresh()->activeDepreciationSetting());

        $this->actingAs($approver)
            ->post(route('approvals.approve', $request->approval_request_id))
            ->assertRedirect(route('approvals.index'));

        $this->assertSame('applied', $request->fresh()->status);
        $setting = $asset->fresh()->activeDepreciationSetting();
        $this->assertNotNull($setting);
        $this->assertEqualsWithDelta(5000, (float) $setting->salvage_value, 0.01);
        $this->assertGreaterThan(0, $setting->scheduleLines()->count());
    }

    public function test_rejection_unblocks_resubmission(): void
    {
        $this->depreciationWorkflow();
        $method = $this->straightLine();
        $asset = Asset::factory()->create();
        $manager = $this->createUserWithRole('Asset Manager');
        $approver = $this->createUserWithRole('Approver');

        $this->actingAs($manager)->put(route('assets.depreciation.update', $asset), $this->payload($asset, $method));
        $request = DepreciationSettingRequest::where('asset_id', $asset->id)->firstOrFail();

        $this->actingAs($approver)
            ->post(route('approvals.reject', $request->approval_request_id), ['comment' => 'Wrong useful life']);

        $this->assertSame('rejected', $request->fresh()->status);
        $this->assertFalse($asset->fresh()->hasPendingDepreciationRequest());

        // A second submission is now allowed.
        $this->actingAs($manager)
            ->put(route('assets.depreciation.update', $asset), $this->payload($asset, $method))
            ->assertRedirect();

        $this->assertDatabaseCount('depreciation_setting_requests', 2);
    }

    public function test_a_second_pending_request_is_blocked(): void
    {
        $this->depreciationWorkflow();
        $method = $this->straightLine();
        $asset = Asset::factory()->create();
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)->put(route('assets.depreciation.update', $asset), $this->payload($asset, $method));

        $this->actingAs($manager)
            ->put(route('assets.depreciation.update', $asset), $this->payload($asset, $method))
            ->assertSessionHasErrors('asset');

        $this->assertDatabaseCount('depreciation_setting_requests', 1);
    }
}
