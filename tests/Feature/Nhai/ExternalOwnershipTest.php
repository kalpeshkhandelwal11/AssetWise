<?php

namespace Tests\Feature\Nhai;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\DisposalType;
use App\Models\Location;
use App\Models\MovementType;
use App\Models\User;
use App\Services\Depreciation\StraightLineCalculator;
use App\Services\DepreciationService;
use App\Services\DisposalService;
use App\Services\MovementService;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/** NHAI-owned assets: never depreciated, disposed, inter-company transferred or moved off the plaza; out of our reports. */
class ExternalOwnershipTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private Company $ours;

    private Company $nhai;

    private Asset $nhaiAsset;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(ReferenceDataSeeder::class);
        $this->manager = $this->createUserWithRole('Super Admin');

        $this->ours = Company::factory()->create(['party_type' => 'internal', 'name' => 'Our Co']);
        $this->nhai = Company::where('code', 'NHAI')->firstOrFail();
        $plaza = Location::create(['name' => 'Plaza', 'code' => 'PLZ', 'is_active' => true]);

        $this->nhaiAsset = Asset::factory()->create([
            'name' => 'NHAI-BARRIER', 'company_id' => $this->nhai->id, 'operating_company_id' => $this->ours->id,
            'location_id' => $plaza->id, 'purchase_cost' => 50000,
            'status_id' => AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#999', 'is_system' => true, 'is_active' => true])->id,
        ]);
        Asset::factory()->create(['name' => 'OUR-LAPTOP', 'company_id' => $this->ours->id]);
    }

    public function test_nhai_assets_are_never_depreciated(): void
    {
        $method = DepreciationMethod::firstOrCreate(['code' => 'straight_line'], ['name' => 'Straight Line', 'calculator_class' => StraightLineCalculator::class, 'is_active' => true]);
        $category = AssetCategory::findOrFail($this->nhaiAsset->category_id);
        $category->depreciationDefault()->create(['depreciation_method_id' => $method->id, 'useful_life_months' => 60, 'salvage_percent' => 5, 'start_basis' => 'purchase_date']);

        $this->assertNull(app(DepreciationService::class)->applyCategoryDefault($this->nhaiAsset->fresh()));
    }

    public function test_nhai_assets_cannot_be_disposed(): void
    {
        $this->expectException(ValidationException::class);
        app(DisposalService::class)->submit($this->nhaiAsset, [
            'disposal_type_id' => DisposalType::firstOrCreate(['code' => 'SCRAP'], ['name' => 'Scrap', 'is_active' => true])->id,
            'reason'           => 'x',
        ], $this->manager);
    }

    public function test_nhai_assets_cannot_change_company_or_leave_the_plaza(): void
    {
        $ict = MovementType::firstOrCreate(['code' => 'INTER_COMPANY_TRANSFER'], ['name' => 'Inter-Company Transfer', 'is_active' => true]);
        $transfer = MovementType::firstOrCreate(['code' => 'TRANSFER'], ['name' => 'Transfer', 'is_active' => true]);
        $elsewhere = Location::create(['name' => 'Elsewhere', 'code' => 'ELS', 'is_active' => true]);

        foreach ([
            ['movement_type_id' => $ict->id, 'to_company_id' => $this->ours->id],
            ['movement_type_id' => $transfer->id, 'to_location_id' => $elsewhere->id],
        ] as $data) {
            try {
                app(MovementService::class)->submit($this->nhaiAsset, $data, $this->manager);
                $this->fail('NHAI asset movement should be refused');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_our_assets_cannot_be_transferred_to_an_external_party(): void
    {
        $ict = MovementType::firstOrCreate(['code' => 'INTER_COMPANY_TRANSFER'], ['name' => 'Inter-Company Transfer', 'is_active' => true]);
        $ours = Asset::where('name', 'OUR-LAPTOP')->firstOrFail();

        $this->expectException(ValidationException::class);
        app(MovementService::class)->submit($ours, ['movement_type_id' => $ict->id, 'to_company_id' => $this->nhai->id], $this->manager);
    }

    public function test_asset_register_excludes_nhai_assets_unless_asked(): void
    {
        $this->actingAs($this->manager)->get(route('reports.show', 'asset_register'))
            ->assertOk()->assertSee('OUR-LAPTOP')->assertDontSee('NHAI-BARRIER');

        $this->actingAs($this->manager)->get(route('reports.show', ['type' => 'asset_register', 'include_external' => 1]))
            ->assertSee('NHAI-BARRIER');

        $this->actingAs($this->manager)->get(route('reports.show', ['type' => 'asset_register', 'company_id' => $this->nhai->id]))
            ->assertSee('NHAI-BARRIER');
    }

    public function test_operating_company_users_see_their_nhai_assets(): void
    {
        $user = User::factory()->restrictedTo($this->ours->id)->create();
        $user->assignRole('Asset Manager');

        $this->actingAs($user)->get(route('nhai.index'))->assertOk()->assertSee('NHAI-BARRIER');

        $stranger = User::factory()->restrictedTo(Company::factory()->create()->id)->create();
        $stranger->assignRole('Asset Manager');
        $this->actingAs($stranger)->get(route('nhai.index'))->assertOk()->assertDontSee('NHAI-BARRIER');
    }
}
