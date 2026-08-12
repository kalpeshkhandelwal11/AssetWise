<?php

namespace App\Services\Reports;

/**
 * Single source of truth for report types — drives the selector UI, route validation,
 * and ReportController's dispatch. "Coming soon" entries (enabled => false) have no
 * backing query/export/view because their data doesn't exist yet: Kit Assignment History
 * needs M17.
 */
class ReportRegistry
{
    private const REPORTS = [
        'asset_register' => [
            'label'       => 'Asset Register',
            'description' => 'Core and category-specific fields for every asset, with company context.',
            'enabled'     => true,
        ],
        'movement' => [
            'label'       => 'Movement Report',
            'description' => 'Assignment, return, transfer, custodian change, and inter-company movements.',
            'enabled'     => true,
        ],
        'intercompany_transfer' => [
            'label'       => 'Inter-Company Transfer Report',
            'description' => 'Transfers between companies with approver and completion details.',
            'enabled'     => true,
        ],
        'disposal' => [
            'label'       => 'Disposal Report',
            'description' => 'Disposal requests through write-off and scrap.',
            'enabled'     => true,
        ],
        'aging' => [
            'label'       => 'Asset Aging',
            'description' => 'Asset age buckets from purchase date.',
            'enabled'     => true,
        ],
        'utilization' => [
            'label'       => 'Utilization',
            'description' => 'Assigned vs. available assets, by company.',
            'enabled'     => true,
        ],
        'audit_compliance' => [
            'label'       => 'Audit / Compliance',
            'description' => 'Asset status transition trail.',
            'enabled'     => true,
        ],
        'audit_campaign' => [
            'label'       => 'Audit Campaign',
            'description' => 'Verification findings (verified/missing/damaged) across audit campaigns.',
            'enabled'     => true,
        ],
        'depreciation_schedule' => [
            'label'       => 'Depreciation Schedule',
            'description' => 'Per-period depreciation, accumulated depreciation, and book value by asset.',
            'enabled'     => true,
        ],
        'maintenance' => [
            'label'       => 'Maintenance Report',
            'description' => 'Service history: preventive and corrective maintenance, vendor, and repair cost.',
            'enabled'     => true,
        ],
        'amc_warranty' => [
            'label'       => 'AMC & Warranty',
            'description' => 'AMC contracts and warranty records with coverage window and expiry status.',
            'enabled'     => true,
        ],
        'kit_assignment_history' => [
            'label'       => 'Kit Assignment History',
            'description' => 'Coming soon — depends on Module M17 (Kitting).',
            'enabled'     => false,
        ],
    ];

    /** @return array<string, array{label: string, description: string, enabled: bool}> */
    public static function all(): array
    {
        return self::REPORTS;
    }

    public static function exists(string $type): bool
    {
        return isset(self::REPORTS[$type]);
    }

    public static function isEnabled(string $type): bool
    {
        return self::REPORTS[$type]['enabled'] ?? false;
    }

    public static function label(string $type): string
    {
        return self::REPORTS[$type]['label'] ?? $type;
    }

    /** Aborts 404 for both unknown and "coming soon" (disabled) report types. */
    public static function assertEnabled(string $type): void
    {
        abort_unless(self::isEnabled($type), 404);
    }
}
