<?php

namespace Tests\Feature\Nhai;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Location;
use App\Models\PlazaTakeover;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Handover / Takeover opens on a dashboard of the toll plazas that work with NHAI: per plaza the
 * live NHAI-owned and our-own asset counts, a category breakdown, not-working count, latest
 * takeover / handover, and a Details link into that plaza's NHAI asset list.
 */
class NhaiDashboardTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $admin;

    private Company $nhai;

    private Company $ours;

    private Location $plaza;

    private AssetCategory $chairs;

    private AssetCategory $cameras;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seed(ReferenceDataSeeder::class);
        $this->admin = $this->createUserWithRole('Super Admin');
        $this->nhai = Company::where('code', 'NHAI')->firstOrFail();
        $this->ours = Company::factory()->create(['name' => 'Our Toll Co', 'party_type' => 'internal']);
        $this->plaza = Location::create(['name' => 'Anupalle TP', 'code' => 'ANU', 'is_active' => true, 'is_toll_plaza' => true, 'is_nhai_plaza' => true, 'chainage' => '3+445']);
        $this->chairs = AssetCategory::factory()->create(['name' => 'Chairs']);
        $this->cameras = AssetCategory::factory()->create(['name' => 'Cameras']);
    }

    private function asset(Company $owner, AssetCategory $category, array $extra = []): Asset
    {
        return Asset::factory()->create($extra + [
            'company_id' => $owner->id, 'category_id' => $category->id, 'location_id' => $this->plaza->id,
            'status_id' => AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#999', 'is_system' => true, 'is_active' => true])->id,
        ]);
    }

    public function test_only_nhai_plazas_are_listed_with_split_counts_and_breakdown(): void
    {
        Location::create(['name' => 'Ordinary Office', 'code' => 'OFF', 'is_active' => true]);
        Location::create(['name' => 'Private Plaza', 'code' => 'PRV', 'is_active' => true, 'is_toll_plaza' => true]);

        $this->asset($this->nhai, $this->chairs);
        $this->asset($this->nhai, $this->chairs, ['condition' => 'not_functional']);
        $this->asset($this->nhai, $this->cameras, ['condition' => 'partially_functional']);
        $this->asset($this->ours, $this->cameras);
        // Not counted: returned to NHAI (retired) and deleted.
        $this->asset($this->nhai, $this->chairs, ['status_id' => AssetStatus::firstOrCreate(['code' => 'RETURNED'], ['name' => 'Returned', 'color' => '#999', 'is_system' => true, 'is_active' => true])->id]);
        $this->asset($this->nhai, $this->chairs)->delete();

        $page = $this->actingAs($this->admin)->get(route('nhai.index'))->assertOk();
        $page->assertSee('Handover / Takeover')->assertSee('Anupalle TP')
            ->assertDontSee('Ordinary Office')->assertDontSee('Private Plaza');

        $row = $page->viewData('dashboard')['rows']->sole();
        $this->assertSame(3, $row['nhai']);
        $this->assertSame(1, $row['ours']);
        $this->assertSame(2, $row['not_working']);
        $byName = $row['categories']->keyBy('name');
        $this->assertSame(['nhai' => 2, 'ours' => 0], array_intersect_key($byName['Chairs'], ['nhai' => 0, 'ours' => 0]));
        $this->assertSame(['nhai' => 1, 'ours' => 1], array_intersect_key($byName['Cameras'], ['nhai' => 0, 'ours' => 0]));

        $page->assertSee(route('nhai.index', ['tab' => 'assets', 'location_id' => $this->plaza->id])); // escaped: &amp; in the HTML
    }

    public function test_latest_takeover_is_shown_and_details_filters_to_the_plaza(): void
    {
        $takeover = PlazaTakeover::create([
            'reference_no' => 'TK-ANU-01', 'kind' => 'nhai', 'location_id' => $this->plaza->id,
            'from_company_id' => $this->nhai->id, 'company_id' => $this->ours->id,
            'takeover_date' => '2026-04-01', 'status' => 'approved', 'requested_by' => $this->admin->id,
        ]);
        $this->asset($this->nhai, $this->chairs, ['name' => 'ANU-CHAIR']);
        $other = Location::create(['name' => 'Chittoor TP', 'code' => 'CHT', 'is_active' => true, 'is_toll_plaza' => true, 'is_nhai_plaza' => true]);
        Asset::factory()->create(['name' => 'CHT-CHAIR', 'company_id' => $this->nhai->id, 'location_id' => $other->id]);

        $this->actingAs($this->admin)->get(route('nhai.index'))->assertOk()->assertSee($takeover->reference_no);

        $this->actingAs($this->admin)->get(route('nhai.index', ['tab' => 'assets', 'location_id' => $this->plaza->id]))
            ->assertOk()->assertSee('NHAI assets — Anupalle TP')->assertSee('ANU-CHAIR')->assertDontSee('CHT-CHAIR');

        // Without a plaza: every NHAI asset.
        $this->actingAs($this->admin)->get(route('nhai.index', ['tab' => 'assets']))
            ->assertOk()->assertSee('all toll plazas')->assertSee('CHT-CHAIR');
    }

    public function test_add_toll_plaza_from_the_dashboard_sets_both_flags_and_returns_there(): void
    {
        $this->actingAs($this->admin)->post(route('admin.locations.store'), [
            'name' => 'New Plaza', 'code' => 'NEWP', 'is_toll_plaza' => 1, 'is_nhai_plaza' => 1, 'return_to' => 'nhai',
        ])->assertRedirect(route('nhai.index', ['tab' => 'plazas']));

        $new = Location::where('code', 'NEWP')->sole();
        $this->assertTrue($new->is_toll_plaza);
        $this->assertTrue($new->is_nhai_plaza);

        // "Works with NHAI" means nothing unless it is a toll plaza.
        $this->actingAs($this->admin)->post(route('admin.locations.store'), [
            'name' => 'Store Room', 'code' => 'STR', 'is_nhai_plaza' => 1,
        ])->assertRedirect(route('admin.locations.index'));
        $this->assertFalse(Location::where('code', 'STR')->sole()->is_nhai_plaza);

        $this->actingAs($this->admin)->get(route('nhai.index'))->assertOk()->assertSee('+ Add toll plaza')->assertSee('Works with NHAI');
    }

    public function test_a_restricted_user_only_counts_assets_of_their_companies(): void
    {
        $theirs = Company::factory()->create(['party_type' => 'internal']);
        $this->asset($this->ours, $this->chairs);
        $this->asset($theirs, $this->chairs);

        $restricted = User::factory()->restrictedTo([$this->ours->id])->create();
        $restricted->givePermissionTo('nhai.view');

        $row = $this->actingAs($restricted)->get(route('nhai.index'))->assertOk()->viewData('dashboard')['rows']->sole();
        $this->assertSame(1, $row['ours']);
    }

    public function test_sidebar_reads_handover_takeover_and_start_handover_preselects_the_plaza(): void
    {
        $this->actingAs($this->admin)->get(route('nhai.index'))->assertOk()->assertSee('Toll Plazas');

        $this->actingAs($this->admin)->get(route('nhai.handovers.create', ['location_id' => $this->plaza->id]))
            ->assertOk()->assertSee('value="' . $this->plaza->id . '" selected', false);
    }
}
