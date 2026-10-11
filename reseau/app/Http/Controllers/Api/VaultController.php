<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureVaultUnlocked;
use App\Models\DossierFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class VaultController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 900;

    public function status(Request $request): JsonResponse
    {
        $until = (int) $request->session()->get(EnsureVaultUnlocked::SESSION_KEY, 0);

        return response()->json([
            'unlocked' => $until > now()->timestamp,
            'expires_in' => max(0, $until - now()->timestamp),
            'used_bytes' => (int) DossierFile::where('user_id', $request->user()->id)->sum('size'),
            'quota_bytes' => (int) config('vault.quota_mb') * 1_048_576,
            'max_file_bytes' => (int) config('vault.max_file_mb') * 1_048_576,
        ]);
    }

    /** Déverrouille le coffre avec le mot de passe du compte (une session volée ne suffit pas à l'ouvrir). */
    public function unlock(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'max:255']]);
        $key = 'vault|'.$request->user()->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json(['message' => 'Trop de tentatives. Réessaie plus tard.'], 429)->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        if (! Hash::check($data['password'], $request->user()->password)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'Mot de passe incorrect.'], 422);
        }

        RateLimiter::clear($key);
        $request->session()->put(EnsureVaultUnlocked::SESSION_KEY, now()->addMinutes((int) config('vault.unlock_minutes'))->timestamp);

        return $this->status($request);
    }

    public function lock(Request $request): JsonResponse
    {
        $request->session()->forget(EnsureVaultUnlocked::SESSION_KEY);

        return $this->status($request);
    }
}
