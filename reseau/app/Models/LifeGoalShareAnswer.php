<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifeGoalShareAnswer extends Model
{
    protected $fillable = ['share_id', 'life_goal_id', 'status', 'target_date', 'completed_at'];

    protected function casts(): array
    {
        return ['target_date' => 'date:Y-m-d', 'completed_at' => 'date:Y-m-d'];
    }

    public function share(): BelongsTo
    {
        return $this->belongsTo(LifeGoalShare::class, 'share_id');
    }

    public function lifeGoal(): BelongsTo
    {
        return $this->belongsTo(LifeGoal::class);
    }
}
