<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Debt extends Model
{
    public const DIRECTIONS = ['owed_to_me', 'i_owe'];

    protected $hidden = ['user_id'];

    protected $fillable = ['direction', 'counterparty', 'contact_id', 'amount', 'currency', 'reason', 'opened_on', 'due_on', 'notes', 'settled_at'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'opened_on' => 'date:Y-m-d', 'due_on' => 'date:Y-m-d', 'settled_at' => 'datetime'];
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class)->orderBy('date')->orderBy('id');
    }

    public function paid(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function remaining(): float
    {
        return round(max($this->amount - $this->paid(), 0), 2);
    }
}
