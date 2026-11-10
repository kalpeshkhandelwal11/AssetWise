<?php

namespace Tests\Feature\Assets;

use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\WarrantyRecord;
use App\Services\AssetCoverageSync;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * A warranty / AMC expiry typed at onboarding must become a real record — one source of truth
 * for the alerts, the AMC & Warranty lists and the report.
 */
class AssetCoverageSyncTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function available(): AssetStatus
    {
        return AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true]);
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'name'          => 'Coverage Laptop',
            'company_id'    => Company::factory()->create()->id,
            'category_id'   => AssetCategory::factory()->create()->id,
            'asset_type_id' => AssetType::firstOrCreate(['code' => 'HW'], ['name' => 'HW', 'is_active' => true])->id,
            'status_id'     => $this->available()->id,
        ];
    }

    private function editPayload(Asset $asset, array $extra = []): array
    {
        return $extra + [
            'name'          => $asset->name,
            'company_id'    => $asset->company_id,
            'category_id'   => $asset->category_id,
            'asset_type_id' => $asset->asset_type_id,
            'status_id'     => $asset->status_id,
        ];
    }

    public function test_dates_entered_on_the_create_form_become_warranty_and_amc_records(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $warranty = now()->addYear()->toDateString();
        $amc = now()->addMonths(6)->toDateString();

        $this->actingAs($manager)->post(route('assets.store'), $this->payload([
            'purchase_date'   => now()->subMonth()->toDateString(),
            'vendor'          => 'Dell India',
            'manufacturer'    => 'Dell',
            'warranty_expiry' => $warranty,
            'amc_expiry'      => $amc,
        ]))->assertRedirect();

        $asset = Asset::where('name', 'Coverage Laptop')->firstOrFail();

        $record = WarrantyRecord::where('asset_id', $asset->id)->sole();
        $this->assertSame($warranty, $record->end_date->toDateString());
        $this->assertSame('Dell', $record->provider);
        $this->assertSame(now()->subMonth()->toDateString(), $record->start_date->toDateString());

        $contract = AmcContract::where('asset_id', $asset->id)->sole();
        $this->assertSame($amc, $contract->end_date->toDateString());
        $this->assertSame('Dell India', $contract->vendor);

        // The cached column still matches the record — one date, not two.
        $this->assertSame($warranty, $asset->warranty_expiry->toDateString());
        $this->assertSame($amc, $asset->amc_expiry->toDateString());

        // …and both now show in the module lists.
        $this->actingAs($manager)->get(route('warranty.index'))->assertOk()->assertSee('Coverage Laptop');
        $this->actingAs($manager)->get(route('amc.index'))->assertOk()->assertSee('Coverage Laptop');
    }

    public function test_no_dates_means_no_records(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');

        $this->actingAs($manager)->post(route('assets.store'), $this->payload())->assertRedirect();

        $this->assertSame(0, WarrantyRecord::count());
        $this->assertSame(0, AmcContract::count());
    }

    public function test_adding_a_date_on_edit_creates_the_first_record(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create(['warranty_expiry' => null, 'amc_expiry' => null]);
        $date = now()->addYears(2)->toDateString();

        $this->actingAs($manager)->put(route('assets.update', $asset), $this->editPayload($asset, ['warranty_expiry' => $date]))
            ->assertSessionHasNoErrors();

        $this->assertSame($date, WarrantyRecord::where('asset_id', $asset->id)->sole()->end_date->toDateString());
    }

    public function test_once_records_exist_the_form_date_cannot_overwrite_them(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $asset = Asset::factory()->create();
        $record = WarrantyRecord::factory()->create(['asset_id' => $asset->id, 'end_date' => now()->addYear()]);
        $asset->update(['warranty_expiry' => $record->end_date]);

        $this->actingAs($manager)->put(route('assets.update', $asset), $this->editPayload($asset, [
            'warranty_expiry' => now()->addDays(3)->toDateString(),
        ]))->assertSessionHasNoErrors();

        $this->assertSame(now()->addYear()->toDateString(), $asset->fresh()->warranty_expiry->toDateString());
        $this->assertSame(1, WarrantyRecord::where('asset_id', $asset->id)->count());

        // The edit form shows it read-only, pointing at the records.
        $this->actingAs($manager)->get(route('assets.edit', $asset))
            ->assertOk()->assertSee("From this asset's warranty records", false);
    }

    public function test_a_later_contract_adds_history_instead_of_replacing_the_onboarding_date(): void
    {
        $manager = $this->createUserWithRole('Asset Manager');
        $this->actingAs($manager)->post(route('assets.store'), $this->payload([
            'amc_expiry' => now()->addMonths(3)->toDateString(),
        ]));
        $asset = Asset::where('name', 'Coverage Laptop')->firstOrFail();

        $this->actingAs($manager)->post(route('assets.amc.store', $asset), [
            'vendor'     => 'Renewal Co',
            'start_date' => now()->addMonths(3)->toDateString(),
            'end_date'   => now()->addMonths(15)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, AmcContract::where('asset_id', $asset->id)->count());
        $this->assertSame(now()->addMonths(15)->toDateString(), $asset->fresh()->amc_expiry->toDateString());
    }

    public function test_service_path_used_by_import_and_takeover_creates_records(): void
    {
        $actor = $this->createUserWithRole('Asset Manager');

        // No purchase date: AMC start must still be on/before its end date.
        $asset = app(AssetService::class)->create($this->payload([
            'amc_expiry' => now()->addDays(10)->toDateString(),
        ]), $actor);

        $contract = AmcContract::where('asset_id', $asset->id)->sole();
        $this->assertTrue($contract->start_date->lte($contract->end_date));
        $this->assertSame(AssetCoverageSync::NOTE, $contract->coverage);
        $this->assertSame($actor->id, $contract->created_by);
    }

    public function test_backfill_command_creates_missing_records_once(): void
    {
        $owner = $this->createUserWithRole('Asset Manager');
        // A legacy asset: date on the column, no records (written directly, as before the fix).
        $legacy = Asset::factory()->create([
            'warranty_expiry' => now()->addYear(), 'amc_expiry' => null, 'created_by' => $owner->id,
        ]);

        $this->artisan('assets:backfill-coverage', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, WarrantyRecord::count());

        $this->artisan('assets:backfill-coverage')->assertSuccessful();
        $this->assertSame(1, WarrantyRecord::where('asset_id', $legacy->id)->count());

        $this->artisan('assets:backfill-coverage')
            ->expectsOutputToContain('already has its warranty / AMC record')
            ->assertSuccessful();
        $this->assertSame(1, WarrantyRecord::count());
    }
}
