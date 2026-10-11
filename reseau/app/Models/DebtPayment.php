<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DebtPayment extends Model
{
    protected $hidden = ['debt_id', 'movement_id'];

    protected $fillable = ['debt_id', 'date', 'amount', 'account_id', 'movement_id', 'note'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'amount' => 'float'];
    }
}
