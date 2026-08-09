<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'report_type',
        'filters',
        'row_count',
        'file_name',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'filters'    => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * $userId is optional and defaults to the current web session (auth()->id()) — pass it
     * explicitly when recording from a queued job, where there is no authenticated session.
     */
    public static function record(string $reportType, array $filters = [], ?int $rowCount = null, ?string $fileName = null, ?int $userId = null): self
    {
        return self::create([
            'user_id'     => $userId ?? auth()->id(),
            'report_type' => $reportType,
            'filters'     => $filters ?: null,
            'row_count'   => $rowCount,
            'file_name'   => $fileName,
            'created_at'  => now(),
        ]);
    }
}
