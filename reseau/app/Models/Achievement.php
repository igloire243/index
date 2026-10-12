<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = ['experience_id', 'title', 'date', 'description'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d'];
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }
}
