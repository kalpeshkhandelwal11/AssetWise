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

        $admin->assignRole('Super Admin');
    }
}
