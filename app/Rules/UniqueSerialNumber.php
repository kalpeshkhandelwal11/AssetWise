<?php

namespace App\Rules;

use App\Models\Asset;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * assets.serial_number is unique system-wide (blank allowed), compared case- and
 * whitespace-insensitively so "sn-01" and "SN-01 " clash on SQLite exactly as on MySQL's _ci
 * collation. Company isolation is bypassed: uniqueness must hold across every company, but
 * the message only names the clashing asset when the actor can see it.
 */
class UniqueSerialNumber implements ValidationRule
{
    public function __construct(private readonly ?int $ignoreAssetId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $serial = trim((string) $value);

        if ($serial === '') {
            return;
        }

        $clash = Asset::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(TRIM(serial_number)) = ?', [mb_strtolower($serial)])
            ->when($this->ignoreAssetId, fn ($q) => $q->where('id', '!=', $this->ignoreAssetId))
            ->first(['id', 'asset_tag', 'name', 'company_id', 'operating_company_id']);

        if (! $clash) {
            return;
        }

        $visible = \App\Support\CompanyVisibility::canSee($clash->company_id)
            || \App\Support\CompanyVisibility::canSee($clash->operating_company_id);

        $fail('Serial number "'.$serial.'" is already used by '
            .($visible ? 'asset '.($clash->asset_tag ?: $clash->name) : 'another asset').'.');
    }
}
