<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Un lien envoyé à quelqu'un pour qu'il voie mes accomplissements et coche les siens, sans compte. Comme les
 * liens du Coffre : le jeton (dans l'URL) est la seule preuve d'accès, jamais stocké en clair.
 */
class LifeGoalShare extends Model
{
    protected $hidden = ['user_id', 'token_hash'];

    protected $fillable = ['user_id', 'token_hash', 'label', 'respondent_name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LifeGoalShareAnswer::class, 'share_id');
    }

    /** Crée un lien et renvoie [le modèle, le jeton en clair (à donner une seule fois à l'appelant)]. */
    public static function issue(User $user, ?string $label): array
    {
        $token = Str::random(48);
        $share = static::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'label' => $label]);

        return [$share, $token];
    }

    public static function findByToken(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))->first();
    }
}
