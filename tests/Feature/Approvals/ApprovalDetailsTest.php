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
}
