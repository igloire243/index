<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Secret extends Model
{
    protected $hidden = ['user_id'];

    protected $fillable = ['type', 'favorite', 'payload'];

    protected function casts(): array
    {
        return ['favorite' => 'boolean', 'payload' => 'encrypted:array'];
    }

    /** Ce que la liste montre : tout sauf le secret lui-même et les champs masqués. */
    public function summary(): array
    {
        $p = $this->payload ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'favorite' => $this->favorite,
            'title' => $p['title'] ?? '',
            'username' => $p['username'] ?? null,
            'url' => $p['url'] ?? null,
            'updated_at' => $this->updated_at,
        ];
    }

    /** La fiche complète, secrets compris : uniquement quand on ouvre une fiche. */
    public function full(): array
    {
        $p = $this->payload ?? [];

        return $this->summary() + [
            'secret' => $p['secret'] ?? null,
            'notes' => $p['notes'] ?? null,
            'fields' => $p['fields'] ?? [],
        ];
    }
}
