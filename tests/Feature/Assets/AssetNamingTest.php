<?php

namespace Tests\Feature\Assets;

use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Setting;
use App\Services\AssetNamingService;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AssetNamingTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin()
    {
        return $this->createUserWithRole('Super Admin');
    }

    private function payload(): array
    {
        return [
            'name'          => 'Asset',
            'company_id'    => Company::factory()->create()->id,
            'category_id'   => AssetCategory::factory()->create()->id,
            'asset_type_id' => AssetType::firstOrCreate(['code' => 'HW'], ['name' => 'HW', 'is_active' => true])->id,
            'status_id'     => AssetStatus::firstOrCreate(['code' => 'AVAILABLE'], ['name' => 'Available', 'color' => '#22c55e', 'is_system' => true, 'is_active' => true])->id,
        ];
    }

    public function test_series_generates_and_increments(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('assets.store'), $this->payload())->assertRedirect();
        $this->actingAs($admin)->post(route('assets.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseHas('assets', ['asset_tag' => 'AST-0001']);
        $this->assertDatabaseHas('assets', ['asset_tag' => 'AST-0002']);
        $this->assertSame('3', Setting::get('asset_naming_next'));
    }

    public function test_custom_prefix_and_padding_apply(): void
    {
        Setting::set('asset_naming_prefix', 'FA');
        Setting::set('asset_naming_padding', '5');

        $tag = app(AssetNamingService::class)->next();

        $this->assertSame('FA-00001', $tag);
    }

    public function test_disabled_series_leaves_asset_tag_null(): void
    {
        Setting::set('asset_naming_enabled', '0');

        $this->actingAs($this->admin())->post(route('assets.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseHas('assets', ['name' => 'Asset', 'asset_tag' => null]);
    }

    public function test_manual_asset_tag_is_respected(): void
    {
        $this->actingAs($this->admin())
             ->post(route('assets.store'), $this->payload() + ['asset_tag' => 'MANUAL-1'])
             ->assertRedirect();

        $this->assertDatabaseHas('assets', ['asset_tag' => 'MANUAL-1']);
        // The series counter is untouched when a tag was supplied.
        $this->assertSame('1', Setting::get('asset_naming_next', '1'));
    }

    public function test_settings_screen_updates_series(): void
    {
        $this->actingAs($this->admin())
             ->patch(route('admin.settings.asset-naming.update'), [
                 'asset_naming_enabled' => '1',
                 'asset_naming_prefix'  => 'eq',
                 'asset_naming_padding' => 6,
                 'asset_naming_next'    => 42,
             ])
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertSame('EQ', Setting::get('asset_naming_prefix'));
        $this->assertSame('42', Setting::get('asset_naming_next'));
    }
}
