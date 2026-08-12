<?php

namespace App\Models\Reports;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only hydration target for ReportService::buildAmcWarrantyQuery()'s UNION of
 * amc_contracts and warranty_records. Exists purely so a warranty row doesn't hydrate as
 * an App\Models\AmcContract (a lie that would mislead readers of the export's map() and
 * would silently inherit any cast later added to the real AmcContract model).
 *
 * $table is nominal — every query against this model supplies its own selectRaw()/union(),
 * so the "real" table never matters at read time.
 */
class CoverageContract extends Model
{
    protected $table = 'amc_contracts';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
            'cost'       => 'decimal:2',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
