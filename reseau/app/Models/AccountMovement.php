<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountMovement extends Model
{
    protected $fillable = ['account_id', 'date', 'amount', 'note', 'transfer_id'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'amount' => 'float'];
    }
}
