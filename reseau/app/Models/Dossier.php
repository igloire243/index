<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dossier extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = ['contact_id', 'subject', 'title', 'summary'];

    protected function casts(): array
    {
        return ['summary' => 'encrypted'];
    }

    public function files(): HasMany
    {
        return $this->hasMany(DossierFile::class)->where('status', 'ready')->orderBy('id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
