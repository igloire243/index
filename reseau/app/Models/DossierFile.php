<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DossierFile extends Model
{
    public function dossier(): BelongsTo
    {
        return $this->belongsTo(Dossier::class);
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DossierFileShare::class);
    }

    protected $hidden = ['user_id', 'uuid', 'encrypted_key', 'received', 'total_chunks'];

    protected $fillable = ['dossier_id', 'user_id', 'uuid', 'name', 'mime', 'kind', 'size', 'total_chunks', 'received', 'encrypted_key', 'status', 'caption'];

    protected function casts(): array
    {
        return ['received' => 'array', 'size' => 'integer', 'caption' => 'encrypted'];
    }
}
