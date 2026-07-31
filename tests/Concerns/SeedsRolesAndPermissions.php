<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\PermissionRegistrar;

trait SeedsRolesAndPermissions
{
    protected function seedRolesAndPermissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    protected function createUserWithRole(string $role, array $overrides = []): User
    {
        $this->seedRolesAndPermissions();
        $user = User::factory()->create($overrides);
        $user->assignRole($role);
        return $user;
    }
}
