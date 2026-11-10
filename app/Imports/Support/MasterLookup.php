<?php

namespace App\Imports\Support;

use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use Illuminate\Support\Str;

/**
 * Resolves the human-readable names/codes people type into spreadsheets back to master
 * ids — so templates and imports never ask anyone for a database id.
 *
 * Matching is case-insensitive and whitespace-collapsed: first an exact code, then an exact
 * name. Employees additionally accept "Name (EMP001)". Results are preloaded once per
 * import, so a 2,000-row file costs one query per master, not one per cell.
 */
class MasterLookup
{
    /** entity => [model, code column, label used in messages] */
    private const ENTITIES = [
        'company'    => [Company::class, 'code', 'company'],
        'category'   => [AssetCategory::class, 'code', 'category'],
        'asset_type' => [AssetType::class, 'code', 'asset type'],
        'status'     => [AssetStatus::class, 'code', 'status'],
        'location'   => [Location::class, 'code', 'location'],
        'department' => [Department::class, 'code', 'department'],
        'branch'     => [Branch::class, 'code', 'branch'],
        'custodian'  => [Employee::class, 'employee_code', 'custodian'],
    ];

    /** @var array<string, array{byCode: array<string, array>, byName: array<string, array<int, array>>}> */
    private array $maps = [];

    public static function normalise(?string $value): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', (string) $value)));
    }

    /**
     * @return array{id: ?int, error: ?string}  id null + error null means "blank cell".
     */
    public function resolve(string $entity, mixed $raw): array
    {
        $value = trim((string) $raw);

        if ($value === '') {
            return ['id' => null, 'error' => null];
        }

        [, , $label] = self::ENTITIES[$entity];
        $map = $this->map($entity);
        $key = self::normalise($value);

        // "Ravi Kumar (EMP001)" — the code in brackets wins.
        if ($entity === 'custodian' && preg_match('/\(([^()]+)\)\s*$/', $value, $m)) {
            $key = self::normalise($m[1]);
        }

        $hit = $map['byCode'][$key] ?? null;

        if (! $hit) {
            $candidates = $map['byName'][$key] ?? [];

            if (count($candidates) > 1) {
                $hint = $entity === 'custodian' ? ' — use the employee code' : ' — use the code';

                return ['id' => null, 'error' => sprintf('Ambiguous %s "%s" (%d matches)%s.', $label, $value, count($candidates), $hint)];
            }

            $hit = $candidates[0] ?? null;
        }

        if (! $hit) {
            return ['id' => null, 'error' => sprintf('Unknown %s "%s".', $label, $value)];
        }

        if (! $hit['active']) {
            return ['id' => null, 'error' => sprintf('%s "%s" is inactive.', Str::ucfirst($label), $value)];
        }

        return ['id' => $hit['id'], 'error' => null];
    }

    /** Display value for templates/exports: name, or "Name (CODE)" for employees. */
    public static function displayEmployee(?Employee $employee): string
    {
        return $employee ? sprintf('%s (%s)', $employee->name, $employee->employee_code) : '';
    }

    private function map(string $entity): array
    {
        if (isset($this->maps[$entity])) {
            return $this->maps[$entity];
        }

        [$model, $codeColumn] = self::ENTITIES[$entity];

        $query = $model::query()->withoutGlobalScopes();

        // Company isolation: a restricted importer may only reference their own companies
        // (and employees of them, plus unattached employees).
        if ($entity === 'company') {
            $query = Company::query()->visible();
        } elseif ($entity === 'custodian') {
            $query = Employee::query()->visible();
        }

        $byCode = [];
        $byName = [];

        foreach ($query->get() as $row) {
            $entry = ['id' => (int) $row->id, 'active' => (bool) ($row->is_active ?? true)];

            if (filled($row->{$codeColumn})) {
                $byCode[self::normalise($row->{$codeColumn})] = $entry;
            }

            if (filled($row->name)) {
                $byName[self::normalise($row->name)][] = $entry;
            }
        }

        return $this->maps[$entity] = ['byCode' => $byCode, 'byName' => $byName];
    }
}
