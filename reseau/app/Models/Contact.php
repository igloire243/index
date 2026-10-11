<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = [
        'name', 'phone', 'email', 'domains', 'role', 'place', 'lat', 'lng',
        'how', 'anecdote', 'notes', 'favor', 'last_contact',
    ];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'last_contact' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
