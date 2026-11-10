<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetStatus extends Model
{
    /**
     * Reached only through a workflow (creation approval, movement receipt, disposal,
     * handover…) — never picked by hand on the asset form or set by import.
     */
    public const WORKFLOW_ONLY = ['DRAFT', 'IN_TRANSIT', 'DISPOSED', 'RETURNED', 'HANDED_OVER', 'SOLD_EXT', 'ICT'];

    /**
     * End-of-life statuses — the asset has left the active register. LOST is terminal (a
     * written-off loss) but deliberately NOT workflow-only: if the asset turns up, an admin
     * can edit it back to Available.
     */
    public const TERMINAL = ['DISPOSED', 'RETURNED', 'HANDED_OVER', 'SOLD_EXT', 'LOST'];

    protected $fillable = ['name', 'code', 'color', 'is_system', 'is_active'];

    /** Statuses a person may pick on the asset form; keeps $currentId so an edit still shows it. */
    public function scopeManuallySelectable(\Illuminate\Database\Eloquent\Builder $query, ?int $currentId = null): void
    {
        $query->where(fn ($q) => $q->whereNotIn('code', self::WORKFLOW_ONLY)
            ->when($currentId, fn ($w) => $w->orWhere('id', $currentId)));
    }

    /** @return int[] */
    public static function workflowOnlyIds(): array
    {
        return static::whereIn('code', self::WORKFLOW_ONLY)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
