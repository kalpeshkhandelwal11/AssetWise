<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds the employee master. The admin user is linked to an employee row so the seeded
 * account can hold assets and receive custodian notifications; a couple of login-less
 * employees exercise the "custodian that is not a user" path.
 */
class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('code', 'DEFAULT')->first();

        // Link the seeded admin to an employee record.
        $admin = User::where('email', 'admin@assetwise.test')->first();
        if ($admin) {
            Employee::firstOrCreate(
                ['user_id' => $admin->id],
                [
                    'name'          => $admin->name,
                    'employee_code' => 'EMP-ADMIN',
                    'email'         => $admin->email,
                    'company_id'    => $company?->id,
                    'is_active'     => true,
                ]
            );
        }

        // A few people who hold assets but never log in.
        $people = [
            ['name' => 'Ravi Kumar',   'employee_code' => 'EMP-1001', 'email' => 'ravi.kumar@example.com'],
            ['name' => 'Anita Sharma', 'employee_code' => 'EMP-1002', 'email' => 'anita.sharma@example.com'],
            ['name' => 'John Mathew',  'employee_code' => 'EMP-1003', 'email' => null],
        ];

        foreach ($people as $person) {
            Employee::firstOrCreate(
                ['employee_code' => $person['employee_code']],
                array_merge($person, ['company_id' => $company?->id, 'is_active' => true])
            );
        }
    }
}
