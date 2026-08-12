<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase, SeedsRolesAndPermissions;

    private function admin(): User
    {
        return $this->createUserWithRole('Super Admin');
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('admin.users.index'))->assertRedirect('/login');
    }

    public function test_index_requires_permission(): void
    {
        $viewer = $this->createUserWithRole('Viewer');

        $this->actingAs($viewer)
             ->get(route('admin.users.index'))
             ->assertForbidden();
    }

    public function test_store_creates_user_with_role(): void
    {
        $this->actingAs($this->admin())
             ->post(route('admin.users.store'), [
                 'name'     => 'Dana Dept',
                 'email'    => 'dana@assetwise.test',
                 'password' => 'NewPass@9876',
                 'roles'    => ['Department User'],
                 'is_active' => '1',
             ])
             ->assertRedirect(route('admin.users.index'))
             ->assertSessionHas('success');

        $user = User::where('email', 'dana@assetwise.test')->firstOrFail();
        $this->assertTrue($user->hasRole('Department User'));
        $this->assertTrue(Hash::check('NewPass@9876', $user->password));
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
             ->patch(route('admin.users.toggle', $admin))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_cannot_deactivate_last_active_super_admin(): void
    {
        $admin = $this->admin();

        // Needs users.edit but must NOT be a second Super Admin, or the target
        // would no longer be the *last* active one — the scenario under test.
        $userManagerRole = Role::create(['name' => 'User Manager', 'guard_name' => 'web']);
        $userManagerRole->syncPermissions(['users.view', 'users.edit']);
        $actor = User::factory()->create();
        $actor->assignRole('User Manager');

        $this->actingAs($actor)
             ->patch(route('admin.users.toggle', $admin))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_can_deactivate_a_second_super_admin(): void
    {
        $admin = $this->admin();
        $secondAdmin = User::factory()->create();
        $secondAdmin->assignRole('Super Admin');

        $this->actingAs($admin)
             ->patch(route('admin.users.toggle', $secondAdmin))
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertFalse($secondAdmin->fresh()->is_active);
    }

    public function test_cannot_deactivate_user_who_is_custodian_of_active_assets(): void
    {
        $admin = $this->admin();
        $custodian = $this->createUserWithRole('Department User');
        $employee = Employee::factory()->create(['user_id' => $custodian->id]);
        Asset::factory()->create(['custodian_id' => $employee->id, 'is_active' => true]);

        $this->actingAs($admin)
             ->patch(route('admin.users.toggle', $custodian))
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($custodian->fresh()->is_active);
    }

    public function test_can_deactivate_user_after_assets_reassigned(): void
    {
        $admin = $this->admin();
        $custodian = $this->createUserWithRole('Department User');
        $employee = Employee::factory()->create(['user_id' => $custodian->id]);
        $asset = Asset::factory()->create(['custodian_id' => $employee->id, 'is_active' => true]);

        $asset->update(['custodian_id' => null]);

        $this->actingAs($admin)
             ->patch(route('admin.users.toggle', $custodian))
             ->assertRedirect()
             ->assertSessionHas('success');

        $this->assertFalse($custodian->fresh()->is_active);
    }

    public function test_admin_cannot_change_own_roles(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
             ->put(route('admin.users.update', $admin), [
                 'name'  => $admin->name,
                 'email' => $admin->email,
                 'roles' => ['Viewer'],
                 'is_active' => '1',
             ])
             ->assertRedirect()
             ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->hasRole('Super Admin'));
    }

    public function test_role_change_is_written_to_activity_log(): void
    {
        $admin = $this->admin();
        $target = $this->createUserWithRole('Viewer');

        $this->actingAs($admin)
             ->put(route('admin.users.update', $target), [
                 'name'      => $target->name,
                 'email'     => $target->email,
                 'roles'     => ['Asset Manager'],
                 'is_active' => '1',
             ])
             ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($target->fresh()->hasRole('Asset Manager'));

        $this->assertDatabaseHas('activity_log', [
            'log_name'     => 'user',
            'subject_type' => User::class,
            'subject_id'   => $target->id,
            'description'  => 'roles updated',
        ]);
    }

    public function test_activity_log_never_records_the_password_hash(): void
    {
        $admin = $this->admin();
        $target = $this->createUserWithRole('Viewer');

        $this->actingAs($admin)
             ->put(route('admin.users.update', $target), [
                 'name'      => $target->name,
                 'email'     => $target->email,
                 'password'  => 'NewPass@9876',
                 'roles'     => ['Viewer'],
                 'is_active' => '1',
             ])
             ->assertRedirect(route('admin.users.index'));

        $target->refresh();
        $this->assertTrue(Hash::check('NewPass@9876', $target->password));

        $activities = Activity::where('subject_type', User::class)
            ->where('subject_id', $target->id)
            ->get();

        $this->assertNotEmpty($activities);

        foreach ($activities as $activity) {
            $properties = $activity->properties->toArray();
            $this->assertArrayNotHasKey('password', $properties['attributes'] ?? []);
            $this->assertArrayNotHasKey('password', $properties['old'] ?? []);
            $this->assertStringNotContainsString($target->password, json_encode($properties));
        }
    }

    public function test_reset_password_forces_change_at_next_login(): void
    {
        $admin = $this->admin();
        $target = $this->createUserWithRole('Viewer', ['must_change_password' => false]);

        $this->actingAs($admin)
             ->patch(route('admin.users.reset-password', $target), [
                 'password' => 'NewPass@9876',
             ])
             ->assertRedirect()
             ->assertSessionHas('success');

        $target->refresh();
        $this->assertTrue(Hash::check('NewPass@9876', $target->password));
        $this->assertTrue($target->must_change_password);
    }

    public function test_viewer_cannot_create_users(): void
    {
        $viewer = $this->createUserWithRole('Viewer');

        $this->actingAs($viewer)
             ->post(route('admin.users.store'), [
                 'name'     => 'Nope',
                 'email'    => 'nope@assetwise.test',
                 'password' => 'NewPass@9876',
                 'roles'    => ['Viewer'],
             ])
             ->assertForbidden();
    }
}
