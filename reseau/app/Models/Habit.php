<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Habit extends Model
{
    public const CATEGORIES = ['nutrition', 'hydratation', 'activite', 'sommeil', 'soins', 'bien-etre', 'suivi', 'autre'];

    protected $hidden = ['user_id', 'last_reminded_on'];

    protected $fillable = [
        'program_key', 'name', 'category', 'days', 'time', 'reminder', 'notes',
        'start_date', 'end_date', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'days' => 'array',
            'reminder' => 'boolean',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'archived_at' => 'datetime',
            'last_reminded_on' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    /** Vrai si l'habitude est au programme ce jour-là (jour ISO + période d'activité). */
    public function isDueOn(CarbonInterface $date): bool
    {
        if ($this->archived_at !== null) {
            return false;
        }
        $day = $date->copy()->startOfDay();
        if ($this->start_date && $day->lt($this->start_date->copy()->startOfDay())) {
            return false;
        }
        if ($this->end_date && $day->gt($this->end_date->copy()->startOfDay())) {
            return false;
        }

        return in_array($date->dayOfWeekIso, $this->days ?? [], true);
    }
}
