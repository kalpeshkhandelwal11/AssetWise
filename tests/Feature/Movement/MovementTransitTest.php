<?php

namespace Tests\Feature\Movement;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\AssetTagAssignment;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\Tag;
use App\Models\User;
use App\Services\MovementService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Phase 2: physical movements go In Transit on approval and land on a barcode scan at the
 * destination; custodian changes still apply immediately; in-transit/draft assets are locked.
 */
class MovementTransitTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $manager;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $workflow = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $workflow->id]);

        foreach (['AVAILABLE' => 'Available', 'ASSIGNED' => 'Assigned', 'IN_TRANSIT' => 'In Transit'] as $code => $name) {
            AssetStatus::firstOrCreate(['code' => $code], ['name' => $name, 'color' => '#999', 'is_system' => true, 'is_active' => true]);
        }

        $this->manager = $this->createUserWithRole('Asset Manager');
        $this->approver = $this->createUserWithRole('Approver');
    }

    private function type(string $code): MovementType
    {
        return MovementType::firstOrCreate(['code' => $code], ['name' => ucwords(strtolower(str_replace('_', ' ', $code))), 'is_active' => true]);
    }

    private function asset(array $attrs = []): Asset
    {
        return Asset::factory()->create($attrs + ['status_id' => AssetStatus::where('code', 'AVAILABLE')->value('id')]);
    }

    private function moveAndApprove(Asset $asset, array $data): AssetMovement
    {
        $movement = app(MovementService::class)->submit($asset, $data, $this->manager);
        app(WorkflowService::class)->approve($movement->approvalRequest, $this->approver);

        return $movement->fresh();
    }

    private function tag(Asset $asset, string $number): void
    {
        $tag = Tag::factory()->create(['tag_number' => $number, 'status' => 'assigned']);
        AssetTagAssignment::create(['asset_id' => $asset->id, 'tag_id' => $tag->id, 'status' => 'active', 'assigned_at' => now(), 'assigned_by' => $this->manager->id]);
    }

    public function test_location_transfer_goes_in_transit_and_keeps_its_old_location(): void
    {
        $from = Location::create(['name' => 'Plaza A', 'code' => 'PA', 'is_active' => true]);
        $to = Location::create(['name' => 'Plaza B', 'code' => 'PB', 'is_active' => true]);
        $asset = $this->asset(['location_id' => $from->id]);

        $movement = $this->moveAndApprove($asset, ['movement_type_id' => $this->type('TRANSFER')->id, 'to_location_id' => $to->id]);

        $this->assertSame('completed', $movement->status);
        $this->assertSame('in_transit', $movement->receipt_status);
        $this->assertSame('IN_TRANSIT', $asset->fresh()->status->code);
        $this->assertSame($from->id, $asset->fresh()->location_id, 'location changes only on receipt');
        $this->assertSame('In Transit', $movement->display_status);
    }

    public function test_location_manager_scans_it_in_and_it_becomes_available(): void
    {
        $to = Location::create(['name' => 'Plaza B', 'code' => 'PB', 'is_active' => true]);
        $managerUser = User::factory()->create();
        $to->update(['manager_employee_id' => Employee::factory()->create(['user_id' => $managerUser->id])->id]);

        $asset = $this->asset(['custodian_id' => Employee::factory()->create()->id]);
        $this->tag($asset, 'TAG-500');
        $movement = $this->moveAndApprove($asset, ['movement_type_id' => $this->type('TRANSFER')->id, 'to_location_id' => $to->id]);

        // Wrong tag is refused…
        $this->actingAs($managerUser)->post(route('movements.receive', $movement), [
            'tag_number' => 'TAG-999', 'verification_condition' => 'ok',
        ])->assertSessionHasErrors('tag_number');

        // …the right scan (as a /scan URL from the QR) lands the move.
        $this->actingAs($managerUser)->post(route('movements.receive', $movement), [
            'tag_number' => url('/scan/TAG-500'), 'verification_condition' => 'ok',
        ])->assertSessionHasNoErrors();

        $asset->refresh();
        $this->assertSame($to->id, $asset->location_id);
        $this->assertSame('AVAILABLE', $asset->status->code);
        $this->assertNull($asset->custodian_id, 'a relocation without a named recipient ends custody');
        $this->assertSame('received', $movement->fresh()->receipt_status);
        $this->assertSame($managerUser->id, $movement->fresh()->received_by);
        $this->assertSame('scan', $movement->fresh()->receipt_method);
    }

    public function test_a_named_recipient_makes_the_received_asset_assigned(): void
    {
        $to = Location::create(['name' => 'Plaza B', 'code' => 'PB', 'is_active' => true]);
        $person = Employee::factory()->create();
        $asset = $this->asset();
        $movement = $this->moveAndApprove($asset, ['movement_type_id' => $this->type('TRANSFER')->id, 'to_location_id' => $to->id, 'to_custodian_id' => $person->id]);

        app(MovementService::class)->receive($movement, $this->manager, ['condition' => 'ok', 'method' => 'on_behalf']);

        $this->assertSame('ASSIGNED', $asset->fresh()->status->code);
        $this->assertSame($person->id, $asset->fresh()->custodian_id);
    }

    public function test_custodian_change_applies_immediately_with_no_transit(): void
    {
        $person = Employee::factory()->create(); // no login — there is nobody to scan
        $asset = $this->asset();

        $movement = $this->moveAndApprove($asset, ['movement_type_id' => $this->type('ASSIGNMENT')->id, 'to_custodian_id' => $person->id]);

        $this->assertNull($movement->receipt_status);
        $this->assertSame($person->id, $asset->fresh()->custodian_id);
        $this->assertSame('ASSIGNED', $asset->fresh()->status->code);
    }

    public function test_department_only_transfer_applies_immediately(): void
    {
        $department = \App\Models\Department::create(['name' => 'Ops', 'code' => 'OPS', 'is_active' => true]);
        $asset = $this->asset();

        $movement = $this->moveAndApprove($asset, ['movement_type_id' => $this->type('TRANSFER')->id, 'to_department_id' => $department->id]);

        $this->assertNull($movement->receipt_status);
        $this->assertSame($department->id, $asset->fresh()->department_id);
    }

    public function test_inter_company_transfer_changes_company_only_on_receipt(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $asset = $this->asset(['company_id' => $a->id]);

        $movement = $this->moveAndApprove($asset, ['movement_type_id' => $this->type('INTER_COMPANY_TRANSFER')->id, 'to_company_id' => $b->id]);
        $this->assertSame($a->id, $asset->fresh()->company_id);

        app(MovementService::class)->receive($movement, $this->manager, ['condition' => 'ok', 'method' => 'on_behalf']);
        $this->assertSame($b->id, $asset->fresh()->company_id);
    }

    public function test_receiver_of_another_company_is_routed_to_receipt_by_the_scan(): void
    {
        $a = Company::factory()->create();
        $b = Company::factory()->create();
        $asset = $this->asset(['company_id' => $a->id]);
        $this->tag($asset, 'TAG-ICT');
        $movement = $this->moveAndApprove($asset, ['movement_type_id' => $this->type('INTER_COMPANY_TRANSFER')->id, 'to_company_id' => $b->id]);

        $stores = User::factory()->restrictedTo($b->id)->create();
        $stores->assignRole('Asset Manager'); // movement.verify + tags.view, but only company B

        $this->actingAs($stores)->get(route('scan.resolve', 'TAG-ICT'))
            ->assertRedirect(route('movements.receive.form', $movement));
        $this->actingAs($stores)->get(route('movements.receive.form', $movement))->assertOk()->assertSee($asset->name);
    }

    public function test_in_transit_assets_cannot_be_moved_or_disposed(): void
    {
        $to = Location::create(['name' => 'Plaza B', 'code' => 'PB', 'is_active' => true]);
        $asset = $this->asset();
        $this->moveAndApprove($asset, ['movement_type_id' => $this->type('TRANSFER')->id, 'to_location_id' => $to->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MovementService::class)->submit($asset->fresh(), ['movement_type_id' => $this->type('ASSIGNMENT')->id, 'to_custodian_id' => Employee::factory()->create()->id], $this->manager);
    }

    public function test_in_transit_assets_are_hidden_from_pickers(): void
    {
        $asset = $this->asset(['name' => 'MOVING-THING', 'status_id' => AssetStatus::where('code', 'IN_TRANSIT')->value('id')]);
        $this->asset(['name' => 'STILL-HERE']);

        $names = Asset::query()->operable()->pluck('name');

        $this->assertContains('STILL-HERE', $names);
        $this->assertNotContains('MOVING-THING', $names);
    }

    public function test_custody_cannot_be_hand_edited_while_in_transit(): void
    {
        $to = Location::create(['name' => 'Plaza B', 'code' => 'PB', 'is_active' => true]);
        $asset = $this->asset();
        $this->moveAndApprove($asset, ['movement_type_id' => $this->type('TRANSFER')->id, 'to_location_id' => $to->id]);

        $this->actingAs($this->manager)->put(route('assets.update', $asset), ['location_id' => $to->id, 'name' => $asset->name])
            ->assertSessionHasErrors('location_id');
    }

    /** A transfer to a plaza managed by $managerUser, approved and in transit. */
    private function inTransitTo(Asset $asset, User $managerUser): AssetMovement
    {
        $to = Location::create(['name' => 'Plaza S' . $asset->id, 'code' => 'PS' . $asset->id, 'is_active' => true]);
        $to->update(['manager_employee_id' => Employee::factory()->create(['user_id' => $managerUser->id])->id]);

        return $this->moveAndApprove($asset, ['movement_type_id' => $this->type('TRANSFER')->id, 'to_location_id' => $to->id]);
    }

    public function test_a_tagged_asset_is_received_only_by_its_tag_not_its_asset_id(): void
    {
        $managerUser = User::factory()->create();
        $asset = $this->asset(['asset_tag' => 'AST-7001']);
        $this->tag($asset, 'TAG-7001');
        $movement = $this->inTransitTo($asset, $managerUser);

        // Typing the Asset ID is not proof of having the item when it carries a tag.
        $this->actingAs($managerUser)->post(route('movements.receive', $movement), [
            'tag_number' => 'AST-7001', 'verification_condition' => 'ok',
        ])->assertSessionHasErrors('tag_number');
        $this->assertTrue($movement->fresh()->isInTransit());

        $this->actingAs($managerUser)->post(route('movements.receive', $movement), [
            'tag_number' => 'TAG-7001', 'verification_condition' => 'ok',
        ])->assertSessionHasNoErrors();
        $this->assertSame('received', $movement->fresh()->receipt_status);
    }

    public function test_an_untagged_asset_is_received_by_its_asset_id(): void
    {
        $managerUser = User::factory()->create();
        $asset = $this->asset(['asset_tag' => 'AST-7002']);
        $movement = $this->inTransitTo($asset, $managerUser);

        $this->actingAs($managerUser)->post(route('movements.receive', $movement), [
            'tag_number' => 'ast-7002', 'verification_condition' => 'ok',
        ])->assertSessionHasNoErrors();
        $this->assertSame('received', $movement->fresh()->receipt_status);
    }

    public function test_reporting_it_missing_at_receipt_needs_no_scan_only_a_note(): void
    {
        $managerUser = User::factory()->create();
        $asset = $this->asset(['asset_tag' => 'AST-7003']);
        $this->tag($asset, 'TAG-7003');
        $movement = $this->inTransitTo($asset, $managerUser);

        $this->actingAs($managerUser)->post(route('movements.receive', $movement), [
            'verification_condition' => 'missing', 'verification_notes' => 'Never arrived on the truck',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($movement->fresh()->isShort());
    }

    public function test_verify_also_needs_the_tag_for_a_tagged_asset(): void
    {
        $verifier = $this->createUserWithRole('Asset Manager'); // has movement.verify, not Super Admin
        $verifier->givePermissionTo('movement.verify');
        $asset = $this->asset(['asset_tag' => 'AST-7004']);
        $this->tag($asset, 'TAG-7004');
        $movement = $this->moveAndApprove($asset, [
            'movement_type_id' => $this->type('CUSTODIAN_CHANGE')->id, 'to_custodian_id' => Employee::factory()->create()->id,
        ]);

        $this->actingAs($verifier)->post(route('movements.verify', $movement), [
            'tag_number' => 'AST-7004', 'verification_condition' => 'ok',
        ])->assertSessionHasErrors('tag_number');

        $this->actingAs($verifier)->post(route('movements.verify', $movement), [
            'tag_number' => 'TAG-7004', 'verification_condition' => 'ok',
        ])->assertSessionHasNoErrors();
        $this->assertNotNull($movement->fresh()->verified_at);
    }
}
