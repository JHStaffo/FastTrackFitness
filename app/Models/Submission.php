<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    public const CATEGORY_LABELS = [
        'self_care' => 'Self-care visit',
        'in_body' => 'InBody scan',
        'eye_doctor' => 'Eye doctor',
        'dentist' => 'Dentist',
        'primary_care' => 'Primary care',
        'weigh_in' => 'Weekly weigh-in',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['event_date' => 'date:Y-m-d', 'reviewed_at' => 'datetime'];
    }
}
