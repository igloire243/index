<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUSES = ['idea', 'in_progress', 'done', 'abandoned'];

    protected $hidden = ['user_id'];

    protected $fillable = ['contact_id', 'title', 'description', 'status', 'target_date', 'completed_at'];

    protected function casts(): array
    {
        return ['target_date' => 'date:Y-m-d', 'completed_at' => 'datetime'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('position');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
