<?php

namespace Tests\Feature\Assets;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\CategoryDepreciationDefault;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\DepreciationSettingRequest;
use App\Models\Employee;
use App\Models\Location;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The asset Edit form must not change what another module owns:
 *  - company / custodian / location / department belong to Movement (approval + history);
 *  - purchase cost / date feed the depreciation schedule and must not drift from it.
 */
class AssetEditGuardsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function payload(Asset $asset, array $extra = []): array
    {
        return $extra + [
            'name'          => $asset->name,
            'company_id'    => $asset->company_id,
            'category_id'   => $asset->category_id,
            'asset_type_id' => $asset->asset_type_id,
            'status_id'     => $asset->status_id,
        ];
    }

    private function location(): Location
    {
        static $n = 0;
        $n++;

        return Location::create(['name' => "Site {$n}", 'code' => "SITE{$n}", 'is_active' => true]);
    }

    private function draft(): AssetStatus
    {
        return AssetStatus::firstOrCreate(['code' => 'DRAFT'], ['name' => 'Draft', 'color' => '#9ca3af', 'is_system' => true, 'is_active' => true]);
    }

    // ------------------------------------------------------------------ fix 1 + 2: movement fields

    public function test_live_asset_custodian_cannot_be_changed_by_editing(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['custodian_id' => Employee::factory()->create()->id]);
        $original = $asset->custodian_id;

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['custodian_id' => Employee::factory()->create()->id]))
            ->assertSessionHasErrors('custodian_id');

        $this->assertSame($original, $asset->fresh()->custodian_id);
        $this->assertSame(0, AssetMovement::count());
    }

    public function test_live_asset_location_and_department_cannot_be_changed_by_editing(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['location_id' => $this->location()->id]);
        $original = $asset->location_id;

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['location_id' => $this->location()->id]))
            ->assertSessionHasErrors('location_id');

        $this->assertSame($original, $asset->fresh()->location_id);
    }

    public function test_a_location_that_was_never_set_can_be_filled_in(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['location_id' => null]);
        $location = $this->location();

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['location_id' => $location->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame($location->id, $asset->fresh()->location_id);
    }

    public function test_posting_the_unchanged_values_still_saves_other_edits(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['custodian_id' => Employee::factory()->create()->id]);

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['name' => 'Renamed', 'custodian_id' => $asset->custodian_id]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $asset->fresh()->name);
    }

    public function test_draft_assets_stay_fully_editable(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create([
            'status_id'    => $this->draft()->id,
            'custodian_id' => Employee::factory()->create()->id,
        ]);
        $company = Company::factory()->create();
        $custodian = Employee::factory()->create();

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['company_id' => $company->id, 'custodian_id' => $custodian->id]))
            ->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame($company->id, $asset->company_id);
        $this->assertSame($custodian->id, $asset->custodian_id);
    }

    public function test_edit_form_shows_locked_fields_and_the_move_button(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['custodian_id' => Employee::factory()->create()->id]);

        $this->actingAs($manager)->get(route('assets.edit', $asset))
            ->assertOk()
            ->assertSee('Move this asset')
            ->assertSee(route('movements.create', ['asset_id' => $asset->id]), false)
            ->assertDontSee('name="custodian_id"', false)
            ->assertDontSee('name="company_id"', false);
    }

    // ------------------------------------------------------------------ fix 3: purchase cost / date

    private function method(): DepreciationMethod
    {
        return DepreciationMethod::firstOrCreate(
            ['code' => 'straight_line'],
            ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true],
        );
    }

    private function withCategoryDefault(Asset $asset): void
    {
        CategoryDepreciationDefault::create([
            'category_id'            => $asset->category_id,
            'depreciation_method_id' => $this->method()->id,
            'useful_life_months'     => 60,
            'salvage_percent'        => 5,
            'start_basis'            => 'purchase_date',
        ]);
    }

    private function depreciationWorkflow(): void
    {
        $workflow = ApprovalWorkflow::factory()->module('depreciation')->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);
    }

    /** A live asset depreciating on its purchase cost, as creation leaves it. */
    private function depreciatingAsset(): Asset
    {
        $asset = Asset::factory()->create(['purchase_cost' => 100000, 'purchase_date' => now()->subYear()->toDateString()]);
        $this->withCategoryDefault($asset);
        app(DepreciationService::class)->applyCategoryDefault($asset->fresh());
        $this->assertNotNull($asset->fresh()->activeDepreciationSetting());

        return $asset->fresh();
    }

    public function test_adding_a_cost_to_an_undepreciated_asset_starts_depreciation(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['purchase_cost' => null, 'purchase_date' => now()->subMonths(3)->toDateString()]);
        $this->withCategoryDefault($asset);

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['purchase_cost' => 50000, 'purchase_date' => $asset->purchase_date->toDateString()]))
            ->assertSessionHasNoErrors();

        $setting = $asset->fresh()->activeDepreciationSetting();
        $this->assertNotNull($setting, 'no schedule started');
        $this->assertEqualsWithDelta(50000, (float) $setting->cost_basis, 0.01);
    }

    public function test_changing_cost_of_a_depreciating_asset_waits_for_approval_then_moves_both(): void
    {
        $this->depreciationWorkflow();
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = $this->depreciatingAsset();
        $newDate = now()->subMonths(11)->toDateString();

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, [
                'name'          => 'Renamed laptop',
                'purchase_cost' => 120000,
                'purchase_date' => $newDate,
            ]))
            ->assertSessionHasNoErrors();

        // Other edits save now; the purchase figures wait for approval.
        $asset->refresh();
        $this->assertSame('Renamed laptop', $asset->name);
        $this->assertEqualsWithDelta(100000, (float) $asset->purchase_cost, 0.01);

        $request = DepreciationSettingRequest::where('asset_id', $asset->id)->sole();
        $this->assertSame('purchase_correction', $request->change_type);
        $this->assertStringContainsString('100,000.00 → 120,000.00', $request->getApprovalLabel());

        $approver = $this->createUserWithRole('Approver');
        app(WorkflowService::class)->approve($request->approvalRequest, $approver);

        $asset->refresh();
        $this->assertEqualsWithDelta(120000, (float) $asset->purchase_cost, 0.01);
        $this->assertSame($newDate, $asset->purchase_date->toDateString());
        $setting = $asset->activeDepreciationSetting();
        $this->assertEqualsWithDelta(120000, (float) $setting->cost_basis, 0.01);
        $this->assertSame($newDate, $setting->start_date->toDateString());
        $this->assertSame('applied', $request->fresh()->status);
    }

    public function test_a_rejected_correction_leaves_asset_and_schedule_agreeing(): void
    {
        $this->depreciationWorkflow();
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = $this->depreciatingAsset();

        $this->actingAs($manager)->put(route('assets.update', $asset), $this->payload($asset, [
            'purchase_cost' => 120000, 'purchase_date' => $asset->purchase_date->toDateString(),
        ]));

        $request = DepreciationSettingRequest::where('asset_id', $asset->id)->sole();
        app(WorkflowService::class)->reject($request->approvalRequest, $this->createUserWithRole('Approver'), 'Invoice says 100k');

        $asset->refresh();
        $this->assertEqualsWithDelta(100000, (float) $asset->purchase_cost, 0.01);
        $this->assertEqualsWithDelta(100000, (float) $asset->activeDepreciationSetting()->cost_basis, 0.01);
    }

    public function test_correction_without_a_depreciation_workflow_saves_nothing(): void
    {
        $manager = $this->createUserWithRole('Asset Manager'); // no depreciation workflow
        $asset = $this->depreciatingAsset();

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['name' => 'Renamed', 'purchase_cost' => 120000, 'purchase_date' => $asset->purchase_date->toDateString()]))
            ->assertSessionHasErrors('workflow');

        $asset->refresh();
        $this->assertNotSame('Renamed', $asset->name);
        $this->assertSame(0, DepreciationSettingRequest::count());
    }

    public function test_cost_is_locked_when_depreciation_runs_on_another_basis(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = $this->depreciatingAsset();
        // e.g. after an inter-company transfer the buyer depreciates net book value, not cost.
        $asset->activeDepreciationSetting()->update(['cost_basis' => 80000]);

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['purchase_cost' => 90000, 'purchase_date' => $asset->purchase_date->toDateString()]))
            ->assertSessionHasErrors('purchase_cost');

        $this->assertEqualsWithDelta(100000, (float) $asset->fresh()->purchase_cost, 0.01);
    }

    public function test_cost_is_locked_once_depreciation_has_ended(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = $this->depreciatingAsset();
        $asset->activeDepreciationSetting()->update(['is_active' => false]); // written off / sold

        $this->actingAs($manager)
            ->put(route('assets.update', $asset), $this->payload($asset, ['purchase_cost' => 90000, 'purchase_date' => $asset->purchase_date->toDateString()]))
            ->assertSessionHasErrors('purchase_cost');

        $this->actingAs($manager)->get(route('assets.edit', $asset))
            ->assertOk()->assertSee('Depreciation has ended');
    }
}
