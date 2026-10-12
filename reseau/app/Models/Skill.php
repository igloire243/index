<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    public const TYPES = ['competence', 'connaissance', 'talent'];

    protected $hidden = ['user_id'];

    protected $fillable = ['type', 'name', 'level', 'notes'];

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }
}
