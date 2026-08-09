<?php

namespace Tests\Feature\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class SettingControllerTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    public function test_edit_requires_auth(): void
    {
        $this->get(route('admin.settings.tags.edit'))->assertRedirect('/login');
    }

    public function test_edit_requires_settings_manage_permission(): void
    {
        $user = $this->createUserWithRole('Asset Manager'); // has settings.manage per plan
        $this->actingAs($user)
             ->get(route('admin.settings.tags.edit'))
             ->assertOk();

        $noPerm = $this->createUserWithRole('Auditor');
        $this->actingAs($noPerm)
             ->get(route('admin.settings.tags.edit'))
             ->assertForbidden();
    }

    public function test_update_changes_the_global_setting(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)
             ->patch(route('admin.settings.tags.update'), ['tag_code_type' => 'barcode'])
             ->assertRedirect(route('admin.settings.tags.edit'))
             ->assertSessionHas('success');

        $this->assertSame('barcode', Setting::get('tag_code_type'));
    }

    public function test_update_validates_the_code_type(): void
    {
        $admin = $this->createUserWithRole('Super Admin');

        $this->actingAs($admin)
             ->patch(route('admin.settings.tags.update'), ['tag_code_type' => 'nope'])
             ->assertSessionHasErrors('tag_code_type');
    }
}
