<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LifeGoal extends Model
{
    public const STATUSES = ['none', 'planned', 'done'];

    protected $hidden = ['user_id'];

    protected $fillable = ['project_id', 'source_key', 'domain', 'age_bracket', 'title', 'status', 'target_date', 'completed_at', 'notes', 'position'];

    protected function casts(): array
    {
        return ['target_date' => 'date:Y-m-d', 'completed_at' => 'date:Y-m-d'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function shareAnswers(): HasMany
    {
        return $this->hasMany(LifeGoalShareAnswer::class);
    }
}
