<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = ['name', 'category', 'status', 'value', 'currency', 'acquired_on', 'purchase_price', 'location', 'notes'];

    protected function casts(): array
    {
        return ['value' => 'float', 'purchase_price' => 'float', 'acquired_on' => 'date:Y-m-d'];
    }
}
