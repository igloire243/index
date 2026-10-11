<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Les routes du coffre exigent un déverrouillage récent (mot de passe), renouvelé à chaque utilisation. */
class EnsureVaultUnlocked
{
    public const SESSION_KEY = 'vault.unlocked_until';

    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->session()->get(self::SESSION_KEY, 0) <= now()->timestamp) {
            $request->session()->forget(self::SESSION_KEY);

            return response()->json(['message' => 'Le coffre est verrouillé.', 'code' => 'vault_locked'], 403);
        }
        $request->session()->put(self::SESSION_KEY, now()->addMinutes((int) config('vault.unlock_minutes'))->timestamp);

        return $next($request);
    }
}
