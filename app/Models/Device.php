<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Device extends Model
{
    public const PROVIDER_LABELS = ['apple_health' => 'Apple Health', 'fitbit' => 'Fitbit'];

    protected $guarded = [];

    protected $hidden = ['tokens'];

    protected function casts(): array
    {
        return ['tokens' => 'encrypted:array', 'last_sync_at' => 'datetime', 'disconnected_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activityRecords(): HasMany
    {
        return $this->hasMany(ActivityRecord::class);
    }
}
