<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Lien de partage à usage unique vers un fichier du Coffre : pas de session, pas de verrou du coffre — le jeton
 * (dans l'URL) est la seule preuve d'accès, jamais stocké en clair (seul son hachage l'est, comme un mot de passe).
 */
class DossierFileShare extends Model
{
    protected $hidden = ['user_id', 'token_hash'];

    protected $fillable = ['dossier_file_id', 'user_id', 'token_hash', 'expires_at', 'viewed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'viewed_at' => 'datetime'];
    }

    public function dossierFile(): BelongsTo
    {
        return $this->belongsTo(DossierFile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Crée un lien et renvoie [le modèle, le jeton en clair (à donner une seule fois à l'appelant)]. */
    public static function issue(DossierFile $file, int $minutes): array
    {
        $token = Str::random(48);
        $share = static::create([
            'dossier_file_id' => $file->id,
            'user_id' => $file->user_id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return [$share, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))->first();
    }

    public function expired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function used(): bool
    {
        return $this->viewed_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('viewed_at')->where('expires_at', '>', now());
    }
}
