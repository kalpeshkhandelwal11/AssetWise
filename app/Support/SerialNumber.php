<?php

namespace App\Support;

/**
 * One definition of "no serial number". People type placeholders such as "N/A" or "-" when an
 * item has no serial; those are not serials, so they are stored as NULL and never count as a
 * duplicate. Used by the asset form (AssetService), bulk import, the unique-serial rule and the
 * `assets:serial-duplicates --clean-placeholders` cleanup — keep them all on this list.
 */
final class SerialNumber
{
    public const PLACEHOLDERS = ['', 'na', 'n/a', 'n.a', 'n.a.', '-', '--', '---', 'nil', 'none', 'null', '0', 'not available'];

    public static function isPlaceholder(?string $value): bool
    {
        return in_array(mb_strtolower(trim((string) $value)), self::PLACEHOLDERS, true);
    }

    /** The serial to store: trimmed, or null when blank / a placeholder. */
    public static function normalize(?string $value): ?string
    {
        return self::isPlaceholder($value) ? null : trim((string) $value);
    }
}
