<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetType;
use App\Models\AuditCampaign;
use App\Models\AuditType;
use App\Models\Branch;
use App\Models\Building;
use App\Models\Company;
use App\Models\Department;
use App\Models\DepreciationMethod;
use App\Models\Designation;
use App\Models\DisposalType;
use App\Models\Employee;
use App\Models\Floor;
use App\Models\Kit;
use App\Models\Location;
use App\Models\MaintenanceType;
use App\Models\MovementType;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns raw activity-log attribute changes into human-readable labels and values —
 * e.g. `custodian_id: 5 → 8` becomes `Custodian: Asha Rao → Vikram Shah`. Used by the asset
 * History tab and the global /admin/activity-log viewer so foreign keys show names, never ids.
 */
class ActivityAttributePresenter
{
    /**
     * Foreign-key field => [model class, display attribute]. Prefixed variants (to_, from_,
     * expected_, previous_, found_) fall back to their base key automatically — see resolve().
     */
    private const RELATIONS = [
        'company_id'             => [Company::class, 'name'],
        'operating_company_id'   => [Company::class, 'name'],
        'parent_company_id'      => [Company::class, 'name'],
        'authority_company_id'   => [Company::class, 'name'],
        'category_id'            => [AssetCategory::class, 'name'],
        'asset_type_id'          => [AssetType::class, 'name'],
        'status_id'              => [AssetStatus::class, 'name'],
        'location_id'            => [Location::class, 'name'],
        'building_id'            => [Building::class, 'name'],
        'floor_id'               => [Floor::class, 'name'],
        'room_id'                => [Room::class, 'name'],
        'custodian_id'           => [Employee::class, 'name'],
        'manager_employee_id'    => [Employee::class, 'name'],
        'employee_id'            => [Employee::class, 'name'],
        'department_id'          => [Department::class, 'name'],
        'branch_id'              => [Branch::class, 'name'],
        'designation_id'         => [Designation::class, 'name'],
        'depreciation_method_id' => [DepreciationMethod::class, 'name'],
        'maintenance_type_id'    => [MaintenanceType::class, 'name'],
        'disposal_type_id'       => [DisposalType::class, 'name'],
        'movement_type_id'       => [MovementType::class, 'name'],
        'audit_type_id'          => [AuditType::class, 'name'],
        'kit_id'                 => [Kit::class, 'name'],
        'campaign_id'            => [AuditCampaign::class, 'name'],
        'asset_id'               => [Asset::class, 'asset'],
        // People (users = logins)
        'user_id'                => [User::class, 'name'],
        'created_by'             => [User::class, 'name'],
        'updated_by'             => [User::class, 'name'],
        'verified_by'            => [User::class, 'name'],
        'requested_by'           => [User::class, 'name'],
        'approved_by'            => [User::class, 'name'],
        'submitted_by'           => [User::class, 'name'],
        'logged_by'              => [User::class, 'name'],
        'written_off_by'         => [User::class, 'name'],
        'scrapped_by'            => [User::class, 'name'],
        'cancelled_by'           => [User::class, 'name'],
        'received_by'            => [User::class, 'name'],
        'resolved_by'            => [User::class, 'name'],
        'short_reported_by'      => [User::class, 'name'],
        'approver_user_id'       => [User::class, 'name'],
        'uploaded_by'            => [User::class, 'name'],
    ];

    private const PREFIXES = ['to_', 'from_', 'expected_', 'previous_', 'found_', 'original_'];

    /** Fields never worth showing in a human diff. */
    private const HIDDEN = ['password', 'remember_token', 'updated_at', 'created_at', 'deleted_at'];

    /** Request-scoped id→name cache so a history list with repeated ids stays cheap. */
    private static array $cache = [];

    /** "custodian_id" => "Custodian", "asset_type_id" => "Asset Type". */
    public static function label(string $field): string
    {
        $base = Str::endsWith($field, '_id') ? Str::beforeLast($field, '_id') : $field;

        return Str::headline($base);
    }

    /** Resolve a raw stored value to a display string, mapping known FKs to their name. */
    public static function value(string $field, mixed $value, ?string $subjectClass = null): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return $value === [] ? '—' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if ($relation = self::relationFor($field, $subjectClass)) {
            $name = self::lookup($relation[0], $relation[1], $value);

            if ($name !== null) {
                return $name;
            }

            // A dangling id (record deleted): say so rather than leaking a bare number.
            if (is_numeric($value)) {
                return Str::headline(class_basename($relation[0])).' (deleted)';
            }
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}([ T]\d{2}:\d{2}(:\d{2})?)?/', $value)) {
            try {
                $date = Carbon::parse($value);

                return $date->format($date->format('H:i:s') === '00:00:00' ? 'd M Y' : 'd M Y, H:i');
            } catch (\Throwable) {
                // not a date after all
            }
        }

        return (string) $value;
    }

    /**
     * Human diff rows for an activity: updated → old→new per changed field; created → the
     * initial non-empty values; deleted → the values at deletion.
     *
     * @return array<int, array{field: string, label: string, old: ?string, new: ?string}>
     */
    public static function diff(Activity $activity): array
    {
        $props = $activity->properties ?? collect();
        $attributes = (array) ($props['attributes'] ?? []);
        $old = (array) ($props['old'] ?? []);
        $subject = $activity->subject_type;

        $fields = match ($activity->event) {
            'deleted' => array_keys($old ?: $attributes),
            default   => array_keys($attributes),
        };

        $rows = [];

        foreach ($fields as $field) {
            if (in_array($field, self::HIDDEN, true)) {
                continue;
            }

            $newRaw = $activity->event === 'deleted' ? null : ($attributes[$field] ?? null);
            $oldRaw = $activity->event === 'deleted' ? ($old[$field] ?? $attributes[$field] ?? null) : ($old[$field] ?? null);

            if ($activity->event === 'created' && ($newRaw === null || $newRaw === '' || $newRaw === [])) {
                continue;
            }

            $rows[] = [
                'field' => $field,
                'label' => self::label($field),
                'old'   => $activity->event === 'created' ? null : self::value($field, $oldRaw, $subject),
                'new'   => $activity->event === 'deleted' ? null : self::value($field, $newRaw, $subject),
            ];
        }

        return $rows;
    }

    /** A readable name for the activity's subject — never "Model #12". */
    public static function subjectLabel(Activity $activity): string
    {
        $subject = $activity->subject;
        $type = $activity->subject_type ? Str::headline(class_basename($activity->subject_type)) : null;

        if ($subject instanceof Model) {
            if ($subject instanceof Asset) {
                return trim(($subject->asset_tag ? $subject->asset_tag.' — ' : '').$subject->name);
            }

            if (method_exists($subject, 'getApprovalLabel')) {
                return (string) $subject->getApprovalLabel();
            }

            foreach (['name', 'title', 'tag_number', 'reference_no', 'code', 'email'] as $attr) {
                if (filled($subject->getAttribute($attr))) {
                    return $type.': '.$subject->getAttribute($attr);
                }
            }

            if (filled($subject->getAttribute('asset_id'))) {
                return $type.' — '.self::value('asset_id', $subject->getAttribute('asset_id'));
            }

            return (string) $type;
        }

        $props = $activity->properties ?? collect();
        $name = data_get($props, 'attributes.name') ?? data_get($props, 'old.name');

        if ($name) {
            return trim($type.': '.$name.' (deleted)');
        }

        return $type ? $type.' (deleted)' : '—';
    }

    /** Warm the lookup cache for a page of activities in one query per model. */
    public static function preload(iterable $activities): void
    {
        $wanted = [];

        foreach ($activities as $activity) {
            $props = $activity->properties ?? collect();

            foreach (['attributes', 'old'] as $bucket) {
                foreach ((array) ($props[$bucket] ?? []) as $field => $value) {
                    if (! is_numeric($value)) {
                        continue;
                    }

                    if ($relation = self::relationFor($field, $activity->subject_type)) {
                        $wanted[$relation[0]][$relation[1]][(int) $value] = true;
                    }
                }
            }
        }

        foreach ($wanted as $model => $byAttr) {
            foreach ($byAttr as $attr => $ids) {
                $ids = array_keys($ids);
                $missing = array_values(array_filter($ids, fn ($id) => ! array_key_exists($model.':'.$id, self::$cache)));

                if ($missing === []) {
                    continue;
                }

                $found = self::query($model)->whereIn('id', $missing)->get()->keyBy('id');

                foreach ($missing as $id) {
                    self::$cache[$model.':'.$id] = isset($found[$id]) ? self::display($found[$id], $attr) : null;
                }
            }
        }
    }

    /** @return array{0: class-string<Model>, 1: string}|null */
    private static function relationFor(string $field, ?string $subjectClass): ?array
    {
        if (isset(self::RELATIONS[$field])) {
            return self::RELATIONS[$field];
        }

        // Self-referencing trees: parent_id points at the subject's own model.
        if ($field === 'parent_id' && $subjectClass && class_exists($subjectClass)) {
            return [$subjectClass, 'name'];
        }

        foreach (self::PREFIXES as $prefix) {
            if (Str::startsWith($field, $prefix) && isset(self::RELATIONS[Str::after($field, $prefix)])) {
                return self::RELATIONS[Str::after($field, $prefix)];
            }
        }

        return null;
    }

    private static function lookup(string $model, string $attr, mixed $value): ?string
    {
        $key = $model.':'.$value;

        if (! array_key_exists($key, self::$cache)) {
            $record = self::query($model)->find($value);
            self::$cache[$key] = $record ? self::display($record, $attr) : null;
        }

        return self::$cache[$key];
    }

    /**
     * Masters resolve without global scopes (names of companies/people are not secrets), but
     * assets keep company isolation — a hidden asset must not leak through history.
     */
    private static function query(string $model)
    {
        $query = $model === Asset::class ? $model::query() : $model::query()->withoutGlobalScopes();

        return in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)
            ? $query->withTrashed()
            : $query;
    }

    private static function display(Model $record, string $attr): ?string
    {
        if ($record instanceof Asset) {
            return trim(($record->asset_tag ? $record->asset_tag.' — ' : '').$record->name);
        }

        $value = $record->getAttribute($attr);

        return $value === null ? null : (string) $value;
    }

    /** Test helper — the cache is static and would leak across tests in one process. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
