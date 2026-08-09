<?php

namespace App\Services\Tags;

use App\Models\Asset;
use App\Models\Tag;

/**
 * Readonly DTO returned by TagService::resolveScan(), mirroring M04's ResolvedField
 * pattern. $status drives the scan route's response: assigned -> asset detail,
 * available -> assign prompt, inactive -> retired message (+ link to the asset's
 * current tag when one exists).
 */
final class ScanResult
{
    public function __construct(
        public readonly string $status,
        public readonly Tag $tag,
        public readonly ?Asset $asset = null,
        public readonly ?Tag $currentTag = null,
    ) {
    }
}
