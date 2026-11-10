<?php

namespace Tests\Feature\Nhai;

use App\Models\ApprovalRequest;
use App\Models\Asset;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\DisposalRequest;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\PlazaHandover;
use App\Models\User;
use App\Services\HandoverService;
use App\Services\MovementService;
use App\Services\WorkflowService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Contract-end handover: NHAI's assets are returned (no money); ours are sold (price → gain/
 * loss), given to NHAI (written off, the default) or moved to another plaza (In Transit).
 * One approval; the assets are locked while it is pending.
 */
class HandoverTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $manager;

    private User $approver;

    private Company $ours;

    private Company $nhai;

    private Location $plaza;

    private Location $otherPlaza;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(ReferenceDataSeeder::class);

        foreach (['AVAILABLE' => 'Available', 'ASSIGNED' => 'Assigned'] as $code => $name) {
            AssetStatus::firstOrCreate(['code' => $code], ['name' => $name, 'color' => '#999', 'is_system' => true, 'is_active' => true]);
        }

        $this->manager = $this->createUserWithRole('Asset Manager');
        $this->approver = $this->createUserWithRole('Approver');
        $this->ours = Company::factory()->create(['party_type' => 'internal']);
        $this->nhai = Company::where('code', 'NHAI')->firstOrFail();
        $this->plaza = Location::create(['name' => 'Anupalle TP', 'code' => 'ANU', 'is_active' => true, 'is_toll_plaza' => true]);
        $this->otherPlaza = Location::create(['name' => 'Chittoor TP', 'code' => 'CHT', 'is_active' => true, 'is_toll_plaza' => true]);
    }

    private function asset(string $name, bool $nhai = false): Asset
    {
        return Asset::factory()->create([
            'name'                 => $name,
            'company_id'           => $nhai ? $this->nhai->id : $this->ours->id,
            'operating_company_id' => $nhai ? $this->ours->id : null,
            'location_id'          => $this->plaza->id,
            'status_id'            => AssetStatus::where('code', 'AVAILABLE')->value('id'),
            'purchase_cost'        => $nhai ? null : 10000,
        ]);
    }

    private function draft(): PlazaHandover
    {
        return app(HandoverService::class)->createDraft([
            'location_id'   => $this->plaza->id,
            'company_id'    => $this->ours->id,
            'handover_date' => '2027-03-31',
        ], $this->manager);
    }

    private function approveFully(PlazaHandover $handover): void
    {
        $request = ApprovalRequest::findOrFail($handover->fresh()->approval_request_id);
        app(WorkflowService::class)->approve($request, $this->approver);
        app(WorkflowService::class)->approve($request->fresh(), $this->manager);
    }

    public function test_draft_lists_plaza_assets_with_suggested_actions(): void
    {
        $this->asset('NHAI-FAN', nhai: true);
        $this->asset('OUR-LAPTOP');
        Asset::factory()->create(['location_id' => $this->otherPlaza->id, 'company_id' => $this->ours->id]); // elsewhere

        $lines = $this->draft()->lines()->with('asset')->get()->keyBy(fn ($l) => $l->asset->name);

        $this->assertCount(2, $lines);
        $this->assertSame('return_to_nhai', $lines['NHAI-FAN']->action);
        $this->assertSame('give_to_nhai', $lines['OUR-LAPTOP']->action, 'write-off to NHAI is the suggestion');
    }

    public function test_nhai_assets_can_only_be_returned_and_nhai_is_never_paid(): void
    {
        $this->asset('NHAI-FAN', nhai: true);
        $ourAsset = $this->asset('OUR-LAPTOP');
        $handover = $this->draft();
        $service = app(HandoverService::class);
        $nhaiLine = $handover->lines()->whereHas('asset', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $this->nhai->id))->first();
        $ourLine = $handover->lines()->where('asset_id', $ourAsset->id)->first();

        $this->expectException(ValidationException::class);
        $service->updateLines($handover, [$nhaiLine->id => ['action' => 'sell', 'sale_price' => 100]]);
    }

    public function test_selling_to_nhai_is_refused(): void
    {
        $ourAsset = $this->asset('OUR-LAPTOP');
        $handover = $this->draft();
        $line = $handover->lines()->where('asset_id', $ourAsset->id)->first();

        $this->expectException(ValidationException::class);
        app(HandoverService::class)->updateLines($handover, [$line->id => ['action' => 'sell', 'sale_price' => 500, 'buyer_company_id' => $this->nhai->id]]);
    }

    public function test_approved_handover_applies_every_action(): void
    {
        $fan = $this->asset('NHAI-FAN', nhai: true);
        $sold = $this->asset('SOLD-PC');
        $given = $this->asset('GIVEN-UPS');
        $moved = $this->asset('MOVED-PRINTER');
        $buyer = Company::factory()->create(['party_type' => 'external_agency', 'name' => 'Next Agency']);

        $handover = $this->draft();
        $service = app(HandoverService::class);
        $line = fn (Asset $a) => $handover->lines()->where('asset_id', $a->id)->value('id');

        $service->updateLines($handover, [
            $line($sold)  => ['action' => 'sell', 'sale_price' => 6000, 'buyer_company_id' => $buyer->id],
            $line($given) => ['action' => 'give_to_nhai'],
            $line($moved) => ['action' => 'move', 'to_location_id' => $this->otherPlaza->id],
        ]);
        $service->submit($handover, $this->manager);

        // Locked while pending.
        $this->assertTrue($sold->fresh()->hasPendingHandover());
        try {
            app(MovementService::class)->submit($sold->fresh(), [
                'movement_type_id' => MovementType::firstOrCreate(['code' => 'TRANSFER'], ['name' => 'Transfer', 'is_active' => true])->id,
                'to_location_id'   => $this->otherPlaza->id,
            ], $this->manager);
            $this->fail('An asset in a pending handover must be locked.');
        } catch (ValidationException) {
        }

        $this->approveFully($handover);

        $this->assertSame('approved', $handover->fresh()->status, (string) $handover->fresh()->apply_error);
        $this->assertSame('RETURNED', Asset::withoutGlobalScopes()->find($fan->id)->status->code);
        $this->assertNull(Asset::withoutGlobalScopes()->find($fan->id)->operating_company_id);

        $this->assertSame('SOLD_EXT', $sold->fresh()->status->code);
        $sale = DisposalRequest::where('asset_id', $sold->id)->firstOrFail();
        $this->assertEquals(6000, $sale->disposal_value);
        $this->assertSame('scrapped', $sale->status);

        $this->assertSame('HANDED_OVER', $given->fresh()->status->code);
        $this->assertEquals(0, DisposalRequest::where('asset_id', $given->id)->value('disposal_value'));

        $this->assertSame('IN_TRANSIT', $moved->fresh()->status->code, 'a moved asset is received at the other plaza');
        $this->assertSame($this->plaza->id, $moved->fresh()->location_id);
    }

    public function test_submission_needs_complete_sale_details(): void
    {
        $sold = $this->asset('SOLD-PC');
        $handover = $this->draft();
        app(HandoverService::class)->updateLines($handover, [$handover->lines()->where('asset_id', $sold->id)->value('id') => ['action' => 'sell']]);

        $this->expectException(ValidationException::class);
        app(HandoverService::class)->submit($handover->fresh(), $this->manager);
    }

    public function test_screens_and_certificate_render(): void
    {
        $this->asset('NHAI-FAN', nhai: true);
        $handover = $this->draft();

        $this->actingAs($this->manager)->get(route('nhai.handovers.create'))->assertOk();
        $this->actingAs($this->manager)->get(route('nhai.handovers.show', $handover))->assertOk()->assertSee('NHAI-FAN');
        $this->actingAs($this->manager)->get(route('nhai.handovers.certificate', $handover))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->manager)->get(route('nhai.index', ['tab' => 'assets']))->assertOk()->assertSee('NHAI-FAN');
    }
}
