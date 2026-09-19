<?php

namespace App\Support;

use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\Branch;
use App\Models\Building;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Location;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Turns raw activity-log attribute changes into human-readable labels and values —
 * e.g. `custodian_id: 5 → 8` becomes `Custodian: Asha Rao → Vikram Shah`. Used by the
 * asset History tab so foreign-key columns show names instead of bare ids.
 */
class ActivityAttributePresenter
{
    /** Foreign-key field => [model class, display attribute]. */
    private const RELATIONS = [
        'company_id'         => [Company::class, 'name'],
        'to_company_id'      => [Company::class, 'name'],
        'from_company_id'    => [Company::class, 'name'],
        'category_id'        => [AssetCategory::class, 'name'],
        'asset_type_id'      => [AssetType::class, 'name'],
        'status_id'          => [AssetStatus::class, 'name'],
        'to_status_id'       => [AssetStatus::class, 'name'],
        'previous_status_id' => [AssetStatus::class, 'name'],
        'location_id'        => [Location::class, 'name'],
        'to_location_id'     => [Location::class, 'name'],
        'from_location_id'   => [Location::class, 'name'],
        'building_id'        => [Building::class, 'name'],
        'floor_id'           => [Floor::class, 'name'],
        'room_id'            => [Room::class, 'name'],
        'custodian_id'       => [Employee::class, 'name'],
        'to_custodian_id'    => [Employee::class, 'name'],
        'from_custodian_id'  => [Employee::class, 'name'],
        'department_id'      => [Department::class, 'name'],
        'to_department_id'   => [Department::class, 'name'],
        'from_department_id' => [Department::class, 'name'],
        'branch_id'          => [Branch::class, 'name'],
        'created_by'         => [User::class, 'name'],
        'updated_by'         => [User::class, 'name'],
        'verified_by'        => [User::class, 'name'],
    ];

    /** Request-scoped id→name cache so a history list with repeated ids stays cheap. */
    private static array $cache = [];

    /** "custodian_id" => "Custodian", "asset_type_id" => "Asset Type". */
    public static function label(string $field): string
    {
        $base = Str::endsWith($field, '_id') ? Str::beforeLast($field, '_id') : $field;

        return Str::headline($base);
    }

    /** Resolve a raw stored value to a display string, mapping known FKs to their name. */
    public static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (isset(self::RELATIONS[$field])) {
            [$model, $attr] = self::RELATIONS[$field];
            $key = $model . ':' . $value;

            if (! array_key_exists($key, self::$cache)) {
                self::$cache[$key] = optional($model::find($value))->{$attr};
            }

            if (self::$cache[$key] !== null) {
                return (string) self::$cache[$key];
            }
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }
}
