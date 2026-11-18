<?php

namespace Tests\Feature\Movement;

use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetLossRequest;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\Employee;
use App\Models\MovementType;
use App\Models\User;
use App\Services\MovementService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * An assignment / custodian change takes effect on approval and is signed off on the Verify
 * screen. Marking it missing or incomplete there goes to an administrator exactly like a short
 * receipt; "return to sender" undoes the assignment.
 */
class VerifyShortTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $manager;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        foreach (['transfer', 'asset_loss'] as $module) {
            $wf = ApprovalWorkflow::factory()->module($module)->create(['is_active' => true]);
            ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $wf->id]);
        }
        foreach (['AVAILABLE' => 'Available', 'ASSIGNED' => 'Assigned', 'IN_TRANSIT' => 'In Transit', 'LOST' => 'Lost'] as $code => $name) {
            AssetStatus::firstOrCreate(['code' => $code], ['name' => $name, 'color' => '#999', 'is_system' => true, 'is_active' => true]);
        }
        $this->manager = $this->createUserWithRole('Asset Manager');
        $this->admin = $this->createUserWithRole('Super Admin'); // verifies (tag scan bypass) and resolves
    }

    /** Asset held by $from, assigned to $to and approved: custody has already moved. */
    private function approvedAssignment(Employee $from, Employee $to): AssetMovement
    {
        $asset = Asset::factory()->create([
            'custodian_id' => $from->id,
            'status_id'    => AssetStatus::where('code', 'ASSIGNED')->value('id'),
        ]);
        $type = MovementType::firstOrCreate(['code' => 'CUSTODIAN_CHANGE'], ['name' => 'Custodian Change', 'is_active' => true]);

        $movement = app(MovementService::class)->submit($asset, [
            'movement_type_id' => $type->id, 'to_custodian_id' => $to->id,
        ], $this->manager);
        app(WorkflowService::class)->approve($movement->approvalRequest, $this->createUserWithRole('Approver'));

        $this->assertSame($to->id, $asset->fresh()->custodian_id); // applied on approval

        return $movement->fresh();
    }

    public function test_marking_missing_at_verification_opens_a_short_issue(): void
    {
        $movement = $this->approvedAssignment(Employee::factory()->create(), Employee::factory()->create());

        $this->actingAs($this->admin)->post(route('movements.verify', $movement), [
            'verification_condition' => 'missing', 'verification_notes' => 'Ravi says he never got it',
        ])->assertRedirect(route('movements.index'));

        $movement->refresh();
        $this->assertTrue($movement->isShort());
        $this->assertSame('missing', $movement->short_type);
        $this->assertNull($movement->verified_at);

        // Listed for resolution, and the asset is held until then.
        $this->actingAs($this->admin)->get(route('movements.short.index'))->assertOk()->assertSee('Ravi says he never got it');
        $this->assertNotNull($movement->asset->openTransitMovement());
    }

    public function test_a_note_is_required_and_ok_or_damaged_still_just_sign_off(): void
    {
        $movement = $this->approvedAssignment(Employee::factory()->create(), Employee::factory()->create());

        $this->actingAs($this->admin)->post(route('movements.verify', $movement), ['verification_condition' => 'incomplete'])
            ->assertSessionHasErrors('verification_notes');
        $this->assertFalse($movement->fresh()->isShort());

        $this->actingAs($this->admin)->post(route('movements.verify', $movement), ['verification_condition' => 'damaged', 'verification_notes' => 'Scratched'])
            ->assertRedirect();
        $this->assertNotNull($movement->fresh()->verified_at);
        $this->assertFalse($movement->fresh()->isShort());
    }

    public function test_return_to_sender_undoes_the_assignment(): void
    {
        $from = Employee::factory()->create();
        $movement = $this->approvedAssignment($from, Employee::factory()->create());
        app(MovementService::class)->verify($movement, $this->admin, 'missing', 'Not received');

        app(MovementService::class)->resolveShort($movement->fresh(), 'returned_to_sender', $this->admin, 'Back to the previous holder');

        $asset = $movement->asset->fresh();
        $this->assertSame($from->id, $asset->custodian_id);
        $this->assertSame('ASSIGNED', $asset->status->code);
        $this->assertSame('returned', $movement->fresh()->receipt_status);
        $this->assertNull($asset->openTransitMovement());
    }

    public function test_received_later_and_accept_as_is_close_it_without_reapplying(): void
    {
        $to = Employee::factory()->create();
        $first = $this->approvedAssignment(Employee::factory()->create(), $to);
        app(MovementService::class)->verify($first, $this->admin, 'missing', 'Not yet');
        app(MovementService::class)->resolveShort($first->fresh(), 'received_later', $this->admin, 'Turned up');

        $first->refresh();
        $this->assertSame('received', $first->receipt_status);
        $this->assertNotNull($first->verified_at);
        $this->assertSame($to->id, $first->asset->fresh()->custodian_id);

        $second = $this->approvedAssignment(Employee::factory()->create(), $to);
        app(MovementService::class)->verify($second, $this->admin, 'incomplete', 'No charger');
        app(MovementService::class)->resolveShort($second->fresh(), 'accepted_as_is', $this->admin, 'Fine without');

        $this->assertSame('incomplete', $second->fresh()->verification_condition);
        $this->assertSame('received', $second->fresh()->receipt_status);
    }

    public function test_mark_lost_raises_a_loss_write_off(): void
    {
        $movement = $this->approvedAssignment(Employee::factory()->create(), Employee::factory()->create());
        app(MovementService::class)->verify($movement, $this->admin, 'missing', 'Gone');

        app(MovementService::class)->resolveShort($movement->fresh(), 'lost', $this->admin, 'Cannot be found');

        $this->assertSame('pending_approval', AssetLossRequest::where('asset_id', $movement->asset_id)->sole()->status);
    }
}
