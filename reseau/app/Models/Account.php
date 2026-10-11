<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = ['name', 'type', 'institution', 'location', 'source', 'currency', 'balance', 'notes', 'archived_at'];

    protected function casts(): array
    {
        return ['balance' => 'float', 'archived_at' => 'datetime'];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AccountMovement::class);
    }
}
