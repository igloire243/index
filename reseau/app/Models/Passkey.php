<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Passkey extends Model
{
    protected $fillable = ['name', 'credential_id', 'public_key', 'sign_count', 'last_used_at'];

    protected $hidden = ['user_id', 'credential_id', 'public_key', 'sign_count'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime', 'sign_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
