<?php

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AuditType;
use App\Models\Branch;
use App\Models\Building;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\DisposalType;
use App\Models\Floor;
use App\Models\Location;
use App\Models\MaintenanceType;
use App\Models\MovementType;
use App\Models\Priority;
use App\Models\Room;
use Illuminate\Database\Seeder;

class SharedMastersSeeder extends Seeder
{
    public function run(): void
    {
        // Companies
        Company::firstOrCreate(['code' => 'DEFAULT'], [
            'name'    => 'Default Company',
            'address' => '123 Main Street',
            'city'    => 'Mumbai',
            'country' => 'India',
            'contact_name'  => 'Admin',
            'contact_email' => 'admin@assetwise.test',
            'is_active' => true,
        ]);

        // Asset statuses (system = cannot be deleted/deactivated)
        $statuses = [
            ['name' => 'Available',       'code' => 'AVAILABLE',      'color' => '#22c55e', 'is_system' => true],
            ['name' => 'Assigned',        'code' => 'ASSIGNED',       'color' => '#3b82f6', 'is_system' => true],
            ['name' => 'In Maintenance',  'code' => 'MAINTENANCE',    'color' => '#f59e0b', 'is_system' => true],
            ['name' => 'Disposed',        'code' => 'DISPOSED',       'color' => '#ef4444', 'is_system' => true],
            ['name' => 'Under Audit',     'code' => 'AUDIT',          'color' => '#8b5cf6', 'is_system' => false],
            ['name' => 'Lost',            'code' => 'LOST',           'color' => '#6b7280', 'is_system' => false],
            ['name' => 'Inter-Company Transfer', 'code' => 'ICT',     'color' => '#06b6d4', 'is_system' => false],
        ];

        foreach ($statuses as $status) {
            AssetStatus::firstOrCreate(['code' => $status['code']], $status);
        }

        // Asset types
        $types = ['Hardware', 'Software', 'Furniture', 'Vehicle', 'Equipment', 'Consumable', 'Other'];
        foreach ($types as $type) {
            AssetType::firstOrCreate(
                ['code' => strtoupper(str_replace(' ', '_', $type))],
                ['name' => $type, 'is_active' => true]
            );
        }

        // Priorities
        $priorities = ['Low', 'Medium', 'High', 'Critical'];
        foreach ($priorities as $priority) {
            Priority::firstOrCreate(
                ['code' => strtoupper($priority)],
                ['name' => $priority, 'is_active' => true]
            );
        }

        // Movement types
        $movementTypes = [
            'Assignment',
            'Custodian Change',
            'Transfer',
            'Inter-Company Transfer',
            'Return',
            'Lost',
            'Stolen',
            'Temporary Loan',
        ];
        foreach ($movementTypes as $mt) {
            MovementType::firstOrCreate(
                ['code' => strtoupper(str_replace([' ', '-'], '_', $mt))],
                ['name' => $mt, 'is_active' => true]
            );
        }

        // Audit types
        foreach (['Physical', 'Surprise', 'Periodic', 'Compliance'] as $at) {
            AuditType::firstOrCreate(
                ['code' => strtoupper($at)],
                ['name' => $at, 'is_active' => true]
            );
        }

        // Disposal types
        foreach (['Sell', 'Scrap', 'Donate', 'Write-off', 'Return to Vendor'] as $dt) {
            DisposalType::firstOrCreate(
                ['code' => strtoupper(str_replace([' ', '-'], '_', $dt))],
                ['name' => $dt, 'is_active' => true]
            );
        }

        // Maintenance types
        foreach (['Preventive', 'Corrective', 'Emergency', 'Warranty Claim', 'AMC'] as $mt) {
            MaintenanceType::firstOrCreate(
                ['code' => strtoupper(str_replace(' ', '_', $mt))],
                ['name' => $mt, 'is_active' => true]
            );
        }

        // Sample location hierarchy
        $location = Location::firstOrCreate(['code' => 'HQ'], [
            'name'    => 'Head Office',
            'address' => '123 Main Street, Mumbai',
            'is_active' => true,
        ]);

        $building = Building::firstOrCreate(
            ['location_id' => $location->id, 'code' => 'MAIN'],
            ['name' => 'Main Building', 'is_active' => true]
        );

        $floor = Floor::firstOrCreate(
            ['building_id' => $building->id, 'code' => 'GF'],
            ['name' => 'Ground Floor', 'is_active' => true]
        );

        Room::firstOrCreate(
            ['floor_id' => $floor->id, 'code' => 'STORAGE'],
            ['name' => 'Storage Room', 'is_active' => true]
        );

        Room::firstOrCreate(
            ['floor_id' => $floor->id, 'code' => 'IT_LAB'],
            ['name' => 'IT Lab', 'is_active' => true]
        );

        // Departments
        foreach (['Information Technology', 'Finance', 'Human Resources', 'Operations', 'Administration'] as $dept) {
            Department::firstOrCreate(
                ['code' => strtoupper(str_replace(' ', '_', $dept))],
                ['name' => $dept, 'is_active' => true]
            );
        }

        // Branches
        foreach (['Head Office', 'North Branch', 'South Branch'] as $branch) {
            Branch::firstOrCreate(
                ['code' => strtoupper(str_replace(' ', '_', $branch))],
                ['name' => $branch, 'is_active' => true]
            );
        }

        // Designations
        foreach (['Manager', 'Supervisor', 'Analyst', 'Executive', 'Officer', 'Coordinator', 'Assistant'] as $designation) {
            Designation::firstOrCreate(
                ['code' => strtoupper(str_replace(' ', '_', $designation))],
                ['name' => $designation, 'is_active' => true]
            );
        }

        // Sample asset category tree
        $itEquipment = AssetCategory::firstOrCreate(['code' => 'IT_EQUIPMENT'], [
            'name' => 'IT Equipment', 'is_active' => true, 'sort_order' => 1,
        ]);
        AssetCategory::firstOrCreate(['code' => 'LAPTOPS'], [
            'parent_id' => $itEquipment->id, 'name' => 'Laptops', 'is_active' => true, 'sort_order' => 1,
        ]);
        AssetCategory::firstOrCreate(['code' => 'DESKTOPS'], [
            'parent_id' => $itEquipment->id, 'name' => 'Desktops', 'is_active' => true, 'sort_order' => 2,
        ]);
        AssetCategory::firstOrCreate(['code' => 'FURNITURE'], [
            'name' => 'Furniture', 'is_active' => true, 'sort_order' => 2,
        ]);
    }
}
