<?php

namespace Tests\Feature\Assets;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetFieldValue;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\CategoryField;
use App\Models\Company;
use App\Models\User;
use App\Services\DynamicFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Serial numbers and "Must be unique" custom fields (MAC ID, IMEI…) are unique across every
 * asset in every company — case-insensitively, ignoring soft-deleted assets, and including
 * fields inherited from a parent category.
 */
class UniqueAttributesTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private User $admin;

    private AssetCategory $parent;

    private AssetCategory $child;

    private CategoryField $mac;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUserWithRole('Super Admin');
        $this->parent = AssetCategory::factory()->create();
        $this->child = AssetCategory::factory()->create(['parent_id' => $this->parent->id]);
        $this->mac = CategoryField::create([
            'category_id' => $this->parent->id, 'field_key' => 'mac_id', 'label' => 'MAC ID',
            'field_type' => 'text', 'is_required' => false, 'display_order' => 1, 'is_active' => true, 'is_unique' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Router',
            'company_id'    => Company::factory()->create()->id,
            'category_id'   => $this->child->id,
            'asset_type_id' => AssetType::firstOrCreate(['code' => 'HW'], ['name' => 'Hardware', 'is_active' => true])->id,
            'status_id'     => AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true])->id,
        ], $overrides);
    }

    public function test_duplicate_serial_is_rejected_case_insensitively(): void
    {
        Asset::factory()->create(['serial_number' => 'SN-100']);

        $this->actingAs($this->admin)->post(route('assets.store'), $this->payload(['serial_number' => 'sn-100 ']))
            ->assertSessionHasErrors('serial_number');
    }

    public function test_placeholder_serials_never_clash_and_are_stored_blank(): void
    {
        // Legacy rows that still hold "n/a" (as on the live install before the cleanup).
        $legacy = Asset::factory()->create(['serial_number' => 'n/a', 'category_id' => $this->child->id]);
        Asset::factory()->create(['serial_number' => 'N/A']);

        // Editing one of them is not refused as a duplicate…
        $this->actingAs($this->admin)->put(route('assets.update', $legacy), $this->payload([
            'serial_number' => 'n/a', 'name' => 'Renamed', 'company_id' => $legacy->company_id, 'status_id' => $legacy->status_id,
        ]))->assertSessionDoesntHaveErrors('serial_number');
        // …and saving it stores no serial at all.
        $this->assertNull($legacy->fresh()->serial_number);

        // A new asset typed with a placeholder is accepted and stored blank too.
        $this->actingAs($this->admin)->post(route('assets.store'), $this->payload(['name' => 'No-serial chair', 'serial_number' => ' NA ']))
            ->assertSessionDoesntHaveErrors('serial_number');
        $this->assertNull(Asset::where('name', 'No-serial chair')->value('serial_number'));
    }

    public function test_cleanup_command_blanks_placeholders_and_keeps_real_duplicates_reported(): void
    {
        Asset::factory()->count(3)->create(['serial_number' => 'n/a']);
        Asset::factory()->create(['serial_number' => '-']);
        Asset::factory()->count(2)->create(['serial_number' => 'REAL-DUP']);

        $this->artisan('assets:serial-duplicates', ['--clean-placeholders' => true])
            ->expectsOutputToContain('Blanked 4 placeholder serial(s).')
            ->expectsOutputToContain('real-dup');

        $this->assertSame(0, Asset::whereIn('serial_number', ['n/a', '-'])->count());
        $this->assertSame(2, Asset::where('serial_number', 'REAL-DUP')->count());
    }

    public function test_soft_deleted_assets_do_not_block_a_serial(): void
    {
        Asset::factory()->create(['serial_number' => 'SN-200'])->delete();

        $this->actingAs($this->admin)->post(route('assets.store'), $this->payload(['serial_number' => 'SN-200']))
            ->assertSessionDoesntHaveErrors('serial_number');
    }

    public function test_updating_an_asset_keeps_its_own_serial_and_tag(): void
    {
        $asset = Asset::factory()->create(['serial_number' => 'SN-300', 'asset_tag' => 'AST-9000', 'category_id' => $this->child->id]);

        $this->actingAs($this->admin)->put(route('assets.update', $asset), $this->payload([
            'serial_number' => 'SN-300', 'name' => 'Renamed', 'company_id' => $asset->company_id,
        ]))->assertSessionDoesntHaveErrors(['serial_number', 'asset_tag']);
    }

    public function test_unique_field_inherited_from_parent_spans_child_categories(): void
    {
        $existing = Asset::factory()->create(['category_id' => $this->parent->id]);
        AssetFieldValue::create(['asset_id' => $existing->id, 'category_field_id' => $this->mac->id, 'value_text' => 'AA:BB:CC']);

        $this->actingAs($this->admin)->post(route('assets.store'), $this->payload(['fields' => ['mac_id' => 'aa:bb:cc']]))
            ->assertSessionHasErrors('fields.mac_id');
    }

    public function test_unique_check_ignores_the_asset_being_edited(): void
    {
        $asset = Asset::factory()->create(['category_id' => $this->child->id]);
        AssetFieldValue::create(['asset_id' => $asset->id, 'category_field_id' => $this->mac->id, 'value_text' => 'DD:EE']);

        $resolved = app(DynamicFieldService::class)->resolveForCategory($this->child->id);

        $this->assertSame(['mac_id' => 'DD:EE'], app(DynamicFieldService::class)->validate(['mac_id' => 'DD:EE'], $resolved, $asset->id));
    }

    public function test_cannot_make_a_field_unique_while_duplicates_exist(): void
    {
        $field = CategoryField::create([
            'category_id' => $this->parent->id, 'field_key' => 'imei', 'label' => 'IMEI',
            'field_type' => 'text', 'is_required' => false, 'display_order' => 2, 'is_active' => true,
        ]);
        foreach ([1, 2] as $_) {
            AssetFieldValue::create(['asset_id' => Asset::factory()->create()->id, 'category_field_id' => $field->id, 'value_text' => '3550001']);
        }

        $this->actingAs($this->admin)->put(route('admin.categories.fields.update', [$this->parent, $field]), [
            'field_key' => 'imei', 'label' => 'IMEI', 'field_type' => 'text', 'is_unique' => '1',
        ])->assertSessionHasErrors('is_unique');

        $this->assertFalse($field->fresh()->is_unique);
    }

    public function test_serial_duplicates_command_reports_and_refuses_to_enforce(): void
    {
        Asset::factory()->create(['serial_number' => 'DUP']);
        Asset::factory()->create(['serial_number' => 'dup']);
        Asset::factory()->create(['serial_number' => 'NA']);

        $this->artisan('assets:serial-duplicates --clean-placeholders --enforce')
            ->expectsOutputToContain('Blanked 1 placeholder')
            ->expectsOutputToContain('dup ×2')
            ->assertFailed();
    }
}
