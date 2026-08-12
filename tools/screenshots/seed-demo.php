<?php

/**
 * Demo data for the screenshot run (idempotent). Loaded via:
 *   php artisan tinker --execute="require base_path('tools/screenshots/seed-demo.php');"
 *
 * Creates a category's custom fields, a handful of assets, and one live depreciation
 * schedule so the captured pages have realistic content. Also clears the admin's
 * force-password-change flag so the Playwright login lands straight on the dashboard.
 */

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetDepreciationSetting;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\CategoryField;
use App\Models\CategoryFieldOption;
use App\Models\Company;
use App\Models\DepreciationMethod;
use App\Models\Location;
use App\Models\User;
use App\Services\DepreciationService;

User::where('email', 'admin@assetwise.test')->update(['must_change_password' => false]);

$company = Company::first();
$loc     = Location::first();
$type    = AssetType::first();
$admin   = User::first();
$cats    = AssetCategory::orderBy('id')->get();
$primary = $cats->first();

if ($primary && $primary->fields()->count() === 0) {
    CategoryField::create([
        'category_id' => $primary->id, 'field_key' => 'ram_gb', 'label' => 'RAM (GB)',
        'field_type' => 'number', 'is_required' => true, 'display_order' => 1,
        'is_searchable' => true, 'is_active' => true,
    ]);
    $os = CategoryField::create([
        'category_id' => $primary->id, 'field_key' => 'operating_system', 'label' => 'Operating System',
        'field_type' => 'dropdown', 'is_required' => false, 'display_order' => 2,
        'is_searchable' => true, 'is_active' => true,
    ]);
    foreach (['Windows 11', 'macOS', 'Ubuntu 24.04'] as $i => $opt) {
        CategoryFieldOption::create([
            'category_field_id' => $os->id,
            'option_value'      => strtolower(str_replace([' ', '.'], ['_', ''], $opt)),
            'option_label'      => $opt,
            'sort_order'        => $i,
            'is_active'         => true,
        ]);
    }
}

$available = AssetStatus::where('code', 'AVAILABLE')->first() ?? AssetStatus::first();
$assigned  = AssetStatus::where('code', 'ASSIGNED')->first() ?? $available;

$rows = [
    ['MacBook Pro 16"',        'AW-0001', 0, $assigned,  $admin->id, 240000],
    ['Dell Latitude 7440',     'AW-0002', 0, $available, null,       95000],
    ['HP LaserJet Pro M404',   'AW-0003', 1, $available, null,       32000],
    ['Herman Miller Aeron',    'AW-0004', 2, $assigned,  $admin->id, 110000],
    ['Cisco Catalyst 24-Port', 'AW-0005', 3, $available, null,       180000],
    ['iPhone 15 Pro',          'AW-0006', 0, $assigned,  $admin->id, 130000],
];

if (Asset::count() === 0) {
    foreach ($rows as [$name, $tag, $catIdx, $status, $cust, $cost]) {
        $cat = $cats->get($catIdx) ?? $primary;
        Asset::create([
            'asset_tag' => $tag, 'name' => $name, 'company_id' => $company->id,
            'category_id' => $cat->id, 'asset_type_id' => $type->id, 'status_id' => $status->id,
            'location_id' => $loc?->id, 'custodian_id' => $cust, 'manufacturer' => 'Acme Corp',
            'purchase_date' => now()->subMonths(rand(4, 30))->toDateString(), 'purchase_cost' => $cost,
            'vendor' => 'TechSupply Ltd', 'is_active' => true, 'created_by' => $admin->id,
        ]);
    }
}

$first = Asset::orderBy('id')->first();
$sl    = DepreciationMethod::where('code', 'straight_line')->first();
if ($first && $sl && ! $first->activeDepreciationSetting()) {
    $setting = AssetDepreciationSetting::create([
        'asset_id' => $first->id, 'depreciation_method_id' => $sl->id, 'useful_life_months' => 60,
        'salvage_value' => round($first->purchase_cost * 0.05, 2), 'start_date' => $first->purchase_date,
        'cost_basis' => $first->purchase_cost, 'accumulated_depreciation' => 0,
        'current_book_value' => $first->purchase_cost, 'is_active' => true,
    ]);
    app(DepreciationService::class)->generateSchedule($setting);
    app(DepreciationService::class)->postDuePeriods();
}

echo 'demo ready: assets=' . Asset::count() . ' fields=' . CategoryField::count() . PHP_EOL;
