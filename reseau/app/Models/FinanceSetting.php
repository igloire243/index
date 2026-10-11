<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceSetting extends Model
{
    protected $hidden = ['id', 'user_id'];

    protected $fillable = ['base_currency', 'rates'];

    protected function casts(): array
    {
        return ['rates' => 'array'];
    }
}
