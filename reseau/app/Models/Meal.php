<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    public const SLOTS = ['breakfast', 'lunch', 'snack', 'dinner'];

    protected $hidden = ['user_id'];

    protected $fillable = ['date', 'slot', 'description', 'done_at'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'done_at' => 'datetime'];
    }
}
