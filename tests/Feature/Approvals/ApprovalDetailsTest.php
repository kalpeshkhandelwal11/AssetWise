<?php

namespace Tests\Feature\Approvals;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\Location;
use App\Models\MovementType;
use App\Services\MovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * The approval screen for a movement shows what is moving (from → to) and what the
 * destination already holds in the same category, so the approver can spot e.g. a third
 * laptop going to the same person.
 */
class ApprovalDetailsTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_single_movement_shows_details_and_existing_same_category_assets(): void
    {
        $this->seedRolesAndPermissions();
        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);
        MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);

        $laptops = AssetCategory::factory()->create(['name' => 'Laptops']);
        $phones = AssetCategory::factory()->create(['name' => 'Phones']);
        $ravi = Employee::factory()->create(['name' => 'Ravi Kumar']);

        $held = Asset::factory()->create(['name' => 'OLD-LAPTOP', 'category_id' => $laptops->id, 'custodian_id' => $ravi->id]);
        Asset::factory()->create(['name' => 'RAVI-PHONE', 'category_id' => $phones->id, 'custodian_id' => $ravi->id]);
        $moving = Asset::factory()->create(['name' => 'NEW-LAPTOP', 'category_id' => $laptops->id, 'status_id' => $held->status_id]);

        $manager = $this->createUserWithRole('Asset Manager');
        $movement = app(MovementService::class)->submit($moving, [
            'movement_type_id' => MovementType::where('code', 'ASSIGNMENT')->value('id'),
            'to_custodian_id'  => $ravi->id,
            'notes'            => 'Replacement for the broken one',
        ], $manager);

        $approver = $this->createUserWithRole('Approver');

        $this->actingAs($approver)->get(route('approvals.show', $movement->approval_request_id))
            ->assertOk()
            ->assertSee('Movement details')
            ->assertSee('Replacement for the broken one')
            ->assertSee('Already assigned to Ravi Kumar in Laptops')
            ->assertSee('OLD-LAPTOP')
            ->assertDontSee('RAVI-PHONE');   // other category
    }

    public function test_location_is_used_when_no_custodian_is_named(): void
    {
        $category = AssetCategory::factory()->create();
        $store = Location::create(['name' => 'Main Store', 'code' => 'MS', 'is_active' => true]);
        Asset::factory()->create(['name' => 'IN-STORE', 'category_id' => $category->id, 'location_id' => $store->id]);
        $moving = Asset::factory()->create(['category_id' => $category->id]);

        $movement = \App\Models\AssetMovement::factory()->create([
            'asset_id' => $moving->id, 'to_location_id' => $store->id, 'to_custodian_id' => null,
        ]);

        $holdings = app(MovementService::class)->existingHoldingsFor($movement);

        $this->assertSame('Main Store', $holdings->first()['holder']);
        $this->assertSame(1, $holdings->first()['total']);
    }

    public function test_a_transfer_naming_a_person_and_a_location_shows_both(): void
    {
        $category = AssetCategory::factory()->create(['name' => 'Radios']);
        $priya = Employee::factory()->create(['name' => 'Priya Shah']);
        $plaza = Location::create(['name' => 'North Plaza', 'code' => 'NP', 'is_active' => true]);
        Asset::factory()->create(['name' => 'PRIYA-RADIO', 'category_id' => $category->id, 'custodian_id' => $priya->id]);
        Asset::factory()->create(['name' => 'PLAZA-RADIO-1', 'category_id' => $category->id, 'location_id' => $plaza->id]);
        Asset::factory()->create(['name' => 'PLAZA-RADIO-2', 'category_id' => $category->id, 'location_id' => $plaza->id]);
        $moving = Asset::factory()->create(['category_id' => $category->id]);

        $movement = \App\Models\AssetMovement::factory()->create([
            'asset_id' => $moving->id, 'to_custodian_id' => $priya->id, 'to_location_id' => $plaza->id,
        ]);

        $holdings = app(MovementService::class)->existingHoldingsFor($movement);

        $this->assertCount(2, $holdings);
        // Person first, then the place.
        $this->assertSame(['person', 'Priya Shah', 1], [$holdings[0]['kind'], $holdings[0]['holder'], $holdings[0]['total']]);
        $this->assertSame(['location', 'North Plaza', 2], [$holdings[1]['kind'], $holdings[1]['holder'], $holdings[1]['total']]);
    }

    public function test_a_kit_assignment_approval_shows_what_the_recipient_already_holds(): void
    {
        $this->seedRolesAndPermissions();
        $workflow = ApprovalWorkflow::factory()->module('kit_assignment')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);
        $type = MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);

        $laptops = AssetCategory::factory()->create(['name' => 'Laptops']);
        $ravi = Employee::factory()->create(['name' => 'Ravi Kumar']);
        Asset::factory()->create(['name' => 'RAVI-OLD-LAPTOP', 'category_id' => $laptops->id, 'custodian_id' => $ravi->id]);
        $kitLaptop = Asset::factory()->create(['category_id' => $laptops->id]);

        $manager = $this->createUserWithRole('Asset Manager');
        $this->actingAs($manager)->post(route('kit-assignments.store'), [
            'asset_ids'        => [$kitLaptop->id],
            'movement_type_id' => $type->id,
            'to_custodian_id'  => $ravi->id,
        ])->assertRedirect();

        $assignment = \App\Models\KitAssignment::firstOrFail();
        $this->assertSame('single', $assignment->approval_mode);

        $this->actingAs($this->createUserWithRole('Approver'))->get(route('approvals.show', $assignment->approval_request_id))
            ->assertOk()
            ->assertSee('Already assigned to Ravi Kumar in Laptops')
            ->assertSee('RAVI-OLD-LAPTOP');
    }

    public function test_a_new_asset_awaiting_creation_approval_links_to_its_detail_page(): void
    {
        $this->seedRolesAndPermissions();
        $wf = ApprovalWorkflow::factory()->module('asset_creation')->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
        $draft = \App\Models\AssetStatus::firstOrCreate(['code' => 'DRAFT'], ['name' => 'Draft', 'color' => '#999', 'is_system' => true, 'is_active' => true]);
        $asset = Asset::factory()->create(['name' => 'NEW-SERVER', 'status_id' => $draft->id]);
        $request = app(\App\Services\WorkflowService::class)->submit($asset, 'asset_creation', $this->createUserWithRole('Asset Manager'));
        $approver = $this->createUserWithRole('Approver');

        $this->actingAs($approver)->get(route('approvals.show', $request))
            ->assertOk()
            ->assertSee('Open asset details')
            ->assertSee(route('assets.show', $asset), false);

        // …and the inbox row links straight to it too.
        $this->actingAs($approver)->get(route('approvals.index'))
            ->assertOk()->assertSee('view asset')->assertSee(route('assets.show', $asset), false);

        // The approver can actually open the draft asset's page.
        $this->actingAs($approver)->get(route('assets.show', $asset))->assertOk()->assertSee('NEW-SERVER');
    }

    public function test_a_disposal_approval_links_to_the_asset(): void
    {
        $this->seedRolesAndPermissions();
        $wf = ApprovalWorkflow::factory()->module('disposal')->create(['is_active' => true]);
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
        $asset = Asset::factory()->create();
        $disposal = \App\Models\DisposalRequest::factory()->create(['asset_id' => $asset->id]);
        $request = app(\App\Services\WorkflowService::class)->submit($disposal, 'disposal', $this->createUserWithRole('Asset Manager'));

        $this->assertTrue($request->subjectAsset()->is($asset));
        $this->actingAs($this->createUserWithRole('Approver'))->get(route('approvals.show', $request))
            ->assertOk()->assertSee(route('assets.show', $asset), false);
    }

    public function test_a_kit_approval_lists_every_asset_with_a_link(): void
    {
        $this->seedRolesAndPermissions();
        $wf = ApprovalWorkflow::factory()->module('kit_assignment')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
        $type = MovementType::firstOrCreate(['code' => 'ASSIGNMENT'], ['name' => 'Assignment', 'is_active' => true]);
        $laptop = Asset::factory()->create(['name' => 'KIT-LAPTOP']);
        $bag = Asset::factory()->create(['name' => 'KIT-BAG']);

        $this->actingAs($this->createUserWithRole('Asset Manager'))->post(route('kit-assignments.store'), [
            'asset_ids' => [$laptop->id, $bag->id], 'movement_type_id' => $type->id,
            'to_custodian_id' => Employee::factory()->create()->id,
        ])->assertRedirect();
        $assignment = \App\Models\KitAssignment::firstOrFail();

        $this->actingAs($this->createUserWithRole('Approver'))->get(route('approvals.show', $assignment->approval_request_id))
            ->assertOk()
            ->assertSee('Assets in this kit')
            ->assertSee('KIT-LAPTOP')->assertSee('KIT-BAG')
            ->assertSee(route('assets.show', $laptop), false)
            ->assertDontSee('Open asset details'); // multi-asset: listed, not one button
    }
}
