<?php

namespace Tests\Feature\Movement;

use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Asset;
use App\Models\AssetLossRequest;
use App\Models\AssetMovement;
use App\Models\AssetStatus;
use App\Models\DisposalRequest;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\User;
use App\Services\MovementService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Short received: a batch member not scanned is reported missing; a single asset can arrive
 * incomplete. An admin resolves each — received later / accept as-is / return to sender /
 * mark lost (which needs the asset_loss approval, then writes the asset off).
 */
class MovementShortReceiptTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $manager;

    private User $approver;

    private Location $plaza;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class); // IN_TRANSIT + asset_loss workflow

        $transfer = ApprovalWorkflow::factory()->module('transfer')->create();
        ApprovalStep::factory()->forRole('Approver', 1)->create(['workflow_id' => $transfer->id]);

        foreach (['AVAILABLE' => 'Available', 'ASSIGNED' => 'Assigned', 'LOST' => 'Lost (Written Off)'] as $code => $name) {
            AssetStatus::firstOrCreate(['code' => $code], ['name' => $name, 'color' => '#999', 'is_system' => true, 'is_active' => true]);
        }

        $this->manager = $this->createUserWithRole('Asset Manager');
        $this->approver = $this->createUserWithRole('Approver');
        $this->plaza = Location::create(['name' => 'Plaza B', 'code' => 'PB', 'is_active' => true]);
    }

    /** @return Collection<int, AssetMovement> */
    private function dispatchBatch(int $count): Collection
    {
        $available = AssetStatus::where('code', 'AVAILABLE')->value('id');
        $assets = Asset::factory()->count($count)->create(['status_id' => $available]);
        $type = MovementType::firstOrCreate(['code' => 'TRANSFER'], ['name' => 'Transfer', 'is_active' => true]);

        $batch = app(MovementService::class)->submitBatch(
            Asset::whereIn('id', $assets->pluck('id'))->get(),
            ['movement_type_id' => $type->id, 'to_location_id' => $this->plaza->id],
            $this->manager,
        );
        app(WorkflowService::class)->approve($batch->approvalRequest, $this->approver);

        return $batch->fresh()->movements()->orderBy('id')->get();
    }

    public function test_finishing_a_batch_reports_unscanned_members_short(): void
    {
        [$first, $second, $third] = $this->dispatchBatch(3)->all();
        $service = app(MovementService::class);

        $service->receive($first, $this->manager, ['condition' => 'ok']);
        $this->assertSame('partial', $first->batch->fresh()->receipt_status);

        $count = $service->finishBatchReceipt($first->batch, $this->manager);

        $this->assertSame(2, $count);
        $this->assertSame('short', $second->fresh()->receipt_status);
        $this->assertSame('missing', $second->fresh()->short_type);
        $this->assertSame('IN_TRANSIT', Asset::find($third->asset_id)->status->code, 'short assets stay in transit');
        $this->assertSame($this->plaza->id, Asset::find($first->asset_id)->location_id);
    }

    public function test_incomplete_receipt_needs_a_note_and_opens_an_issue(): void
    {
        [$movement] = $this->dispatchBatch(1)->all();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(MovementService::class)->receive($movement, $this->manager, ['condition' => 'incomplete']);
    }

    public function test_received_later_lands_the_move_and_closes_the_batch(): void
    {
        [$movement] = $this->dispatchBatch(1)->all();
        $service = app(MovementService::class);
        $service->receive($movement, $this->manager, ['condition' => 'incomplete', 'notes' => 'No charger']);

        $this->actingAs($this->manager)->get(route('movements.short.index'))->assertOk()->assertSee('No charger');

        $this->actingAs($this->manager)->post(route('movements.short.resolve', $movement), [
            'resolution' => 'received_later', 'resolution_notes' => 'Charger arrived next day',
        ])->assertSessionHasNoErrors();

        $movement->refresh();
        $this->assertSame('received', $movement->receipt_status);
        $this->assertSame('received_later', $movement->resolution);
        $this->assertSame($this->plaza->id, Asset::find($movement->asset_id)->location_id);
        $this->assertSame('closed', $movement->batch->fresh()->receipt_status);
    }

    public function test_accept_as_is_keeps_the_incomplete_condition(): void
    {
        [$movement] = $this->dispatchBatch(1)->all();
        $service = app(MovementService::class);
        $service->receive($movement, $this->manager, ['condition' => 'incomplete', 'notes' => 'Mouse missing']);

        $service->resolveShort($movement->fresh(), 'accepted_as_is', $this->manager, 'Mouse not needed');

        $this->assertSame('incomplete', $movement->fresh()->verification_condition);
        $this->assertSame('Received (incomplete)', $movement->fresh()->display_status);
        $this->assertSame('AVAILABLE', Asset::find($movement->asset_id)->status->code);
    }

    public function test_return_to_sender_restores_the_previous_state(): void
    {
        [$movement] = $this->dispatchBatch(1)->all();
        $service = app(MovementService::class);
        $originalLocation = Asset::find($movement->asset_id)->location_id;
        $service->finishBatchReceipt($movement->batch, $this->manager);

        $service->resolveShort($movement->fresh(), 'returned_to_sender', $this->manager, 'Never left the store');

        $asset = Asset::find($movement->asset_id);
        $this->assertSame('AVAILABLE', $asset->status->code);
        $this->assertSame($originalLocation, $asset->location_id);
        $this->assertSame('returned', $movement->fresh()->receipt_status);
    }

    public function test_mark_lost_needs_approval_then_writes_the_asset_off(): void
    {
        [$movement] = $this->dispatchBatch(1)->all();
        $service = app(MovementService::class);
        $service->finishBatchReceipt($movement->batch, $this->manager);

        $service->resolveShort($movement->fresh(), 'lost', $this->manager, 'Fell off the truck');

        $loss = AssetLossRequest::firstOrFail();
        $this->assertSame('pending_approval', $loss->status);
        $this->assertSame('short', $movement->fresh()->receipt_status, 'stays open until the write-off is approved');

        // Two-level asset_loss workflow (Approver → Asset Manager).
        $request = ApprovalRequest::findOrFail($loss->approval_request_id);
        app(WorkflowService::class)->approve($request, $this->approver);
        app(WorkflowService::class)->approve($request->fresh(), $this->manager);

        $this->assertSame('approved', $loss->fresh()->status);
        $this->assertSame('LOST', Asset::find($movement->asset_id)->status->code);
        $this->assertSame('lost', $movement->fresh()->receipt_status);

        $disposal = DisposalRequest::findOrFail($loss->fresh()->disposal_request_id);
        $this->assertSame('scrapped', $disposal->status);
        $this->assertEquals(0, $disposal->disposal_value);
    }

    public function test_lost_movement_type_goes_through_the_loss_write_off(): void
    {
        $asset = Asset::factory()->create(['status_id' => AssetStatus::where('code', 'AVAILABLE')->value('id')]);
        $lost = MovementType::firstOrCreate(['code' => 'LOST'], ['name' => 'Lost', 'is_active' => true]);

        $movement = app(MovementService::class)->submit($asset, ['movement_type_id' => $lost->id, 'notes' => 'Left on a bus'], $this->manager);

        $loss = AssetLossRequest::where('asset_id', $asset->id)->firstOrFail();
        $this->assertSame($loss->approval_request_id, $movement->approval_request_id);
        $this->assertSame('asset_loss', ApprovalRequest::find($loss->approval_request_id)->workflow->module);

        $request = ApprovalRequest::findOrFail($loss->approval_request_id);
        app(WorkflowService::class)->reject($request, $this->approver, 'Found it');

        $this->assertSame('rejected', $loss->fresh()->status);
        $this->assertSame('rejected', $movement->fresh()->status);
        $this->assertSame('AVAILABLE', $asset->fresh()->status->code);
    }
}
