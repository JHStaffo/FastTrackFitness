<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ManualEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['scoring_date' => 'date:Y-m-d', 'water_oz' => 'float', 'meal_plan_confirmed' => 'boolean'];
    }
}
