<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = ['date', 'weight_kg', 'waist_cm', 'sleep_hours', 'energy', 'mood', 'notes'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'weight_kg' => 'float',
            'waist_cm' => 'float',
            'sleep_hours' => 'float',
            'energy' => 'integer',
            'mood' => 'integer',
        ];
    }
}
