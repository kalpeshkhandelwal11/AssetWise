<?php

namespace Tests\Feature\Assets;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\DisposalType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AssetCreationApprovalTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function draft(): AssetStatus
    {
        return AssetStatus::firstOrCreate(['code' => 'DRAFT'], ['name' => 'Draft', 'color' => '#9ca3af', 'is_system' => true, 'is_active' => true]);
    }

    private function available(): AssetStatus
    {
        return AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
    }

    private function payload(): array
    {
        return [
            'name'          => 'New Asset',
            'company_id'    => Company::factory()->create()->id,
            'category_id'   => AssetCategory::factory()->create()->id,
            'asset_type_id' => AssetType::firstOrCreate(['code' => 'HW'], ['name' => 'HW', 'is_active' => true])->id,
            'status_id'     => $this->available()->id,
        ];
    }

    private function activateCreationWorkflow(): void
    {
        $wf = ApprovalWorkflow::factory()->module('asset_creation')->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
    }

    public function test_without_workflow_asset_saves_live(): void
    {
        $this->draft();

        $this->actingAs($this->createUserWithRole('Super Admin'))
             ->post(route('assets.store'), $this->payload())
             ->assertRedirect();

        $asset = Asset::firstOrFail();
        $this->assertFalse($asset->isDraft());
        $this->assertDatabaseCount('approval_requests', 0);
    }

    public function test_with_active_workflow_asset_is_parked_as_draft(): void
    {
        $this->draft();
        $this->activateCreationWorkflow();

        $this->actingAs($this->createUserWithRole('Super Admin'))
             ->post(route('assets.store'), $this->payload())
             ->assertRedirect();

        $asset = Asset::firstOrFail();
        $this->assertTrue($asset->isDraft());
        $this->assertTrue($asset->hasPendingCreationApproval());
    }

    public function test_approval_promotes_the_asset_to_available(): void
    {
        $this->draft();
        $this->activateCreationWorkflow();
        $approver = $this->createUserWithRole('Approver');

        $this->actingAs($this->createUserWithRole('Super Admin'))
             ->post(route('assets.store'), $this->payload());

        $asset = Asset::firstOrFail();
        $request = $asset->approvalRequests()->firstOrFail();

        $this->actingAs($approver)->post(route('approvals.approve', $request->id));

        $this->assertSame('AVAILABLE', $asset->fresh()->status->code);
    }

    public function test_rejection_leaves_the_asset_in_draft(): void
    {
        $this->draft();
        $this->activateCreationWorkflow();
        $approver = $this->createUserWithRole('Approver');

        $this->actingAs($this->createUserWithRole('Super Admin'))
             ->post(route('assets.store'), $this->payload());

        $asset = Asset::firstOrFail();
        $request = $asset->approvalRequests()->firstOrFail();

        $this->actingAs($approver)->post(route('approvals.reject', $request->id), ['comment' => 'Missing info']);

        $this->assertTrue($asset->fresh()->isDraft());
        $this->assertFalse($asset->fresh()->hasPendingCreationApproval());
    }

    public function test_draft_asset_cannot_be_disposed(): void
    {
        $draftAsset = Asset::factory()->create(['status_id' => $this->draft()->id]);
        $disposalType = DisposalType::create(['name' => 'Scrap', 'code' => 'SCRAP', 'is_active' => true]);

        $this->actingAs($this->createUserWithRole('Super Admin'))
             ->post(route('disposals.store'), [
                 'asset_id'         => $draftAsset->id,
                 'disposal_type_id' => $disposalType->id,
                 'reason'           => 'test',
             ])
             ->assertSessionHasErrors('asset');
    }
}
