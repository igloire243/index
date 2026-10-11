<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthGoal extends Model
{
    public const GOALS = ['lose', 'gain', 'maintain'];

    protected $hidden = ['id', 'user_id'];

    protected $fillable = ['goal', 'target_weight_kg', 'target_date'];

    protected function casts(): array
    {
        return ['target_weight_kg' => 'float', 'target_date' => 'date:Y-m-d'];
    }
}
