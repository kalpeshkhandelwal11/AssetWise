<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TagBatch extends Model
{
    use HasFactory;

    protected $fillable = ['quantity', 'code_type', 'created_by'];

    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
