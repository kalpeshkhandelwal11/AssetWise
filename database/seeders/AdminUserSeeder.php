<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@assetwise.test'],
            [
                'name'                 => 'System Administrator',
                'password'             => Hash::make('Admin@1234'),
                'must_change_password' => true,   // force reset on first login
                'is_active'            => true,
                'email_verified_at'    => now(),
            ]
        );

        // Super Admin alone is NOT enough to run the app end to end. Approver eligibility is
        // matched on Spatie *role* (WorkflowService::matchesStep -> $user->hasRole(...)), not
        // on permissions, and WorkflowSeeder routes the default chains to Approver / Asset
        // Manager / Super Admin. With only the Super Admin role, this — the sole seeded user —
        // could not action any step of the transfer or tag_replacement workflows, so every
        // movement, disposal and tag replacement submitted on a fresh install would sit
        // pending forever. Granting the operational roles here keeps the seed self-consistent
        // without weakening the engine's role-based separation of duties.
        $admin->assignRole(['Super Admin', 'Asset Manager', 'Approver']);
    }
}
