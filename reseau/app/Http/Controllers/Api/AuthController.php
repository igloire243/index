<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 900;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $key = Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $wait = RateLimiter::availableIn($key);

            return response()->json(['message' => 'Trop de tentatives. Réessaie dans '.ceil($wait / 60).' min.'], 429)
                ->header('Retry-After', (string) $wait);
        }

        if (! Auth::guard('web')->attempt($credentials, true)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => 'E-mail ou mot de passe incorrect.'], 422);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate(['timezone' => ['required', 'timezone:all']]);
        $request->user()->update($data);

        return response()->json($this->userPayload($request->user()));
    }

    private function userPayload($user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'timezone' => $user->timezone];
    }
}
