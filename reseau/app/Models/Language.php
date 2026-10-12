<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    public const LEVELS = ['notions', 'intermediaire', 'courant', 'natif'];

    protected $hidden = ['user_id'];

    protected $fillable = ['name', 'level'];
}
