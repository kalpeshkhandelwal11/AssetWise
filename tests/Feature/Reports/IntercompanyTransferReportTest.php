<?php

namespace Tests\Feature\Reports;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\MovementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class IntercompanyTransferReportTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function transferType(): MovementType
    {
        return MovementType::firstOrCreate(
            ['code' => 'INTER_COMPANY_TRANSFER'],
            ['name' => 'Inter-Company Transfer', 'is_active' => true],
        );
    }

    public function test_only_inter_company_transfer_movements_appear(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $transferAsset = Asset::factory()->create(['name' => 'Transferred Asset', 'company_id' => $companyA->id]);
        $assignedAsset = Asset::factory()->create(['name' => 'Assigned Asset', 'company_id' => $companyA->id]);

        AssetMovement::factory()->create([
            'asset_id'        => $transferAsset->id,
            'movement_type_id' => $this->transferType()->id,
            'from_company_id' => $companyA->id,
            'to_company_id'   => $companyB->id,
        ]);
        AssetMovement::factory()->create(['asset_id' => $assignedAsset->id]);

        $response = $this->actingAs($admin)->get(route('reports.show', 'intercompany_transfer'));

        $response->assertOk();
        $response->assertSee('Transferred Asset');
        $response->assertDontSee('Assigned Asset');
    }

    public function test_screen_respects_the_from_and_to_company_filters(): void
    {
        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $companyC = Company::factory()->create();

        $wanted = Asset::factory()->create(['name' => 'Wanted Transfer']);
        $unwanted = Asset::factory()->create(['name' => 'Unwanted Transfer']);

        AssetMovement::factory()->create([
            'asset_id'        => $wanted->id,
            'movement_type_id' => $this->transferType()->id,
            'from_company_id' => $companyA->id,
            'to_company_id'   => $companyB->id,
        ]);
        AssetMovement::factory()->create([
            'asset_id'        => $unwanted->id,
            'movement_type_id' => $this->transferType()->id,
            'from_company_id' => $companyC->id,
            'to_company_id'   => $companyB->id,
        ]);

        $response = $this->actingAs($admin)->get(route('reports.show', [
            'type' => 'intercompany_transfer', 'from_company_id' => $companyA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Wanted Transfer');
        $response->assertDontSee('Unwanted Transfer');
    }

    public function test_export_row_count_matches_the_on_screen_filtered_set(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $admin = $this->createUserWithRole('Super Admin');
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        AssetMovement::factory()->count(2)->create([
            'movement_type_id' => $this->transferType()->id,
            'from_company_id'  => $companyA->id,
        ]);
        AssetMovement::factory()->count(1)->create();

        $this->actingAs($admin)
            ->post(route('reports.export', 'intercompany_transfer'), [])
            ->assertOk();

        Excel::assertDownloaded(
            '/^intercompany_transfer-report-.*\.xlsx$/',
            fn ($export) => $export->rowCount() === 2
        );
    }
}
