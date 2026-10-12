<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experience extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = ['title', 'organization', 'location', 'start_date', 'end_date', 'current', 'description'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'current' => 'boolean',
        ];
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class)->orderByDesc('date');
    }
}
