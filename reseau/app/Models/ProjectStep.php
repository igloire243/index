<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectStep extends Model
{
    protected $fillable = ['title', 'done', 'done_at', 'position'];

    protected function casts(): array
    {
        return ['done' => 'boolean', 'done_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
