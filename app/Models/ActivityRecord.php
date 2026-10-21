<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityRecord extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'scoring_date' => 'date:Y-m-d',
            'user_entered' => 'boolean',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
