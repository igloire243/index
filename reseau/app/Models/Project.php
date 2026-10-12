<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUSES = ['idea', 'in_progress', 'done', 'abandoned'];

    protected $hidden = ['user_id', 'reminder_sent_for'];

    protected $fillable = ['contact_id', 'dossier_id', 'title', 'domain', 'description', 'status', 'target_date', 'completed_at', 'reminder_sent_for'];

    protected function casts(): array
    {
        return ['target_date' => 'date:Y-m-d', 'completed_at' => 'datetime', 'reminder_sent_for' => 'date:Y-m-d'];
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('position');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // Jamais chargée dans les réponses JSON du projet : le titre d'un dossier du Coffre ne doit fuiter que si le
    // coffre est déverrouillé, ce que cette seule clé étrangère (exposée, elle) ne permet pas de contourner.
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }
}
