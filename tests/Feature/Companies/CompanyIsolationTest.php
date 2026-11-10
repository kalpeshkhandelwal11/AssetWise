<?php

namespace Tests\Feature\Companies;

use App\Jobs\GenerateReportExport;
use App\Models\AmcContract;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Company;
use App\Models\DisposalRequest;
use App\Models\MovementType;
use App\Models\User;
use App\Models\WarrantyRecord;
use App\Services\ReportService;
use App\Services\Reports\ReportPdfExporter;
use App\Services\NotificationService;
use App\Support\CompanyVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Company isolation: a user assigned to company A must never see company B's assets or
 * anything hanging off them — through lists, detail pages, reports (including both legs of
 * the AMC & Warranty UNION), queued exports, or the scan route. Inter-company transfers stay
 * visible to both sides; unrestricted users (all_companies / companies.view_all) see all.
 */
class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private Company $a;

    private Company $b;

    private Asset $assetA;

    private Asset $assetB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();

        $this->a = Company::factory()->create(['name' => 'Alpha Co']);
        $this->b = Company::factory()->create(['name' => 'Bravo Co']);
        $this->assetA = Asset::factory()->create(['company_id' => $this->a->id, 'name' => 'ALPHA-LAPTOP']);
        $this->assetB = Asset::factory()->create(['company_id' => $this->b->id, 'name' => 'BRAVO-LAPTOP']);
    }

    private function userOfA(string $role = 'Asset Manager'): User
    {
        $user = User::factory()->restrictedTo($this->a->id)->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_asset_list_shows_only_own_company(): void
    {
        $this->actingAs($this->userOfA())->get(route('assets.index'))
            ->assertOk()
            ->assertSee('ALPHA-LAPTOP')
            ->assertDontSee('BRAVO-LAPTOP');
    }

    public function test_other_company_asset_detail_is_not_found(): void
    {
        $user = $this->userOfA();

        $this->actingAs($user)->get(route('assets.show', $this->assetA))->assertOk();
        $this->actingAs($user)->get(route('assets.show', $this->assetB))->assertNotFound();
        $this->actingAs($user)->get(route('assets.edit', $this->assetB))->assertNotFound();
    }

    public function test_unrestricted_users_see_every_company(): void
    {
        $all = User::factory()->create(['all_companies' => true]);
        $all->assignRole('Asset Manager');

        $this->actingAs($all)->get(route('assets.index'))
            ->assertSee('ALPHA-LAPTOP')->assertSee('BRAVO-LAPTOP');

        $superAdmin = User::factory()->restrictedTo([])->create();
        $superAdmin->assignRole('Super Admin'); // holds companies.view_all

        $this->actingAs($superAdmin)->get(route('assets.index'))
            ->assertSee('ALPHA-LAPTOP')->assertSee('BRAVO-LAPTOP');
    }

    public function test_user_with_no_companies_sees_nothing(): void
    {
        $user = User::factory()->restrictedTo([])->create();
        $user->assignRole('Asset Manager');

        $this->actingAs($user)->get(route('assets.index'))
            ->assertOk()->assertDontSee('ALPHA-LAPTOP')->assertDontSee('BRAVO-LAPTOP');
    }

    public function test_dependent_records_follow_their_asset(): void
    {
        DisposalRequest::factory()->create(['asset_id' => $this->assetA->id]);
        DisposalRequest::factory()->create(['asset_id' => $this->assetB->id]);

        CompanyVisibility::actingAs($this->userOfA(), function () {
            $this->assertSame([$this->assetA->id], DisposalRequest::pluck('asset_id')->all());
        });
    }

    public function test_inter_company_transfer_is_visible_to_both_sides(): void
    {
        $ict = MovementType::firstOrCreate(['code' => 'INTER_COMPANY_TRANSFER'], ['name' => 'Inter-Company Transfer', 'is_active' => true]);

        // B's asset is being transferred to A: A must see the movement even though the
        // asset itself is still owned (and therefore hidden) by B.
        $movement = AssetMovement::factory()->create([
            'asset_id'         => $this->assetB->id,
            'movement_type_id' => $ict->id,
            'from_company_id'  => $this->b->id,
            'to_company_id'    => $this->a->id,
        ]);
        AssetMovement::factory()->create(['asset_id' => $this->assetB->id]); // B-internal, hidden

        CompanyVisibility::actingAs($this->userOfA(), function () use ($movement) {
            $this->assertSame([$movement->id], AssetMovement::pluck('id')->all());
        });
    }

    public function test_amc_warranty_report_filters_both_union_legs(): void
    {
        AmcContract::factory()->create(['asset_id' => $this->assetA->id, 'vendor' => 'ALPHA-AMC']);
        WarrantyRecord::factory()->create(['asset_id' => $this->assetA->id, 'provider' => 'ALPHA-WARRANTY']);
        AmcContract::factory()->create(['asset_id' => $this->assetB->id, 'vendor' => 'BRAVO-AMC']);
        WarrantyRecord::factory()->create(['asset_id' => $this->assetB->id, 'provider' => 'BRAVO-WARRANTY']);

        $this->actingAs($this->userOfA())
            ->get(route('reports.show', ['type' => 'amc_warranty']))
            ->assertOk()
            ->assertSee('ALPHA-AMC')->assertSee('ALPHA-WARRANTY')
            ->assertDontSee('BRAVO-AMC')->assertDontSee('BRAVO-WARRANTY');
    }

    public function test_queued_report_export_is_scoped_to_the_requester(): void
    {
        Excel::fake();
        Excel::matchByRegex();

        $user = $this->userOfA();

        // Runs outside any web session — exactly the queue-worker situation.
        (new GenerateReportExport($user->id, 'asset_register', []))
            ->handle(app(ReportService::class), app(ReportPdfExporter::class), app(NotificationService::class));

        Excel::assertStored('/^exports\/asset_register-report-.*\.xlsx$/', 'public',
            fn ($export) => $export->rowCount() === 1);
    }

    public function test_asset_form_rejects_a_company_the_user_cannot_see(): void
    {
        $this->actingAs($this->userOfA())
            ->post(route('assets.store'), ['company_id' => $this->b->id, 'name' => 'X'])
            ->assertSessionHasErrors('company_id');
    }

    public function test_restricted_admin_cannot_grant_unseen_companies(): void
    {
        // Super Admin holds companies.view_all, so use a custom, company-scoped admin role.
        $role = \App\Models\Role::create(['name' => 'Company Admin', 'guard_name' => 'web']);
        $role->givePermissionTo(['users.view', 'users.create']);
        $restrictedAdmin = User::factory()->restrictedTo($this->a->id)->create();
        $restrictedAdmin->assignRole('Company Admin');

        $this->actingAs($restrictedAdmin)->post(route('admin.users.store'), [
            'name'           => 'New Person',
            'email'          => 'new@example.test',
            'password'       => 'NewPass@9876',
            'roles'          => ['Viewer'],
            'company_access' => 'selected',
            'companies'      => [$this->b->id],
        ])->assertSessionHasErrors('companies.0');

        $this->actingAs($restrictedAdmin)->post(route('admin.users.store'), [
            'name'           => 'New Person',
            'email'          => 'new@example.test',
            'password'       => 'NewPass@9876',
            'roles'          => ['Viewer'],
            'company_access' => 'all',
        ])->assertSessionHasErrors('company_access');
    }

    public function test_admin_assigns_companies_to_a_user(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name'           => 'Scoped Person',
            'email'          => 'scoped@example.test',
            'password'       => 'NewPass@9876',
            'roles'          => ['Viewer'],
            'company_access' => 'selected',
            'companies'      => [$this->a->id],
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'scoped@example.test')->firstOrFail();
        $this->assertFalse($user->all_companies);
        $this->assertSame([$this->a->id], $user->visibleCompanyIds());
    }
}
