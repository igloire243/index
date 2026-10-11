<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Passkey;
use App\Services\Passkeys;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use lbuchs\WebAuthn\WebAuthnException;

class PasskeyController extends Controller
{
    private const MAX_ATTEMPTS = 10;

    private const DECAY_SECONDS = 900;

    public function __construct(private readonly Passkeys $passkeys) {}

    /* ---------- Gestion des clés (utilisateur connecté) ---------- */

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->passkeys()->latest()->get(['id', 'name', 'last_used_at', 'created_at']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->passkeys()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $webauthn = $this->passkeys->webauthn($request);

        $args = $webauthn->getCreateArgs(
            $this->passkeys->userHandle($user),
            $user->email,
            $user->name,
            60,
            true,       // clé « découvrable » : la connexion n'a pas besoin de saisir l'e-mail
            true,       // vérification obligatoire par le système : visage, empreinte ou code de l'appareil
            false,      // authentificateur intégré à l'appareil (Face ID, empreinte), pas une clé USB
            $user->passkeys()->pluck('credential_id')->map(fn ($id) => Passkeys::b64urlDecode($id))->all(),
        );
        $this->passkeys->remember($request, Passkeys::REGISTER_KEY, $webauthn->getChallenge());

        return response()->json($args);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'clientDataJSON' => ['required', 'string', 'max:4096'],
            'attestationObject' => ['required', 'string', 'max:16384'],
        ]);
        $clientData = Passkeys::b64urlDecode($data['clientDataJSON']);
        $challenge = $this->passkeys->pull($request, Passkeys::REGISTER_KEY);

        if ($challenge === null || ! $this->passkeys->originMatches($request, $clientData)) {
            return response()->json(['message' => 'Demande expirée ou invalide. Recommence.'], 422);
        }

        try {
            $result = $this->passkeys->webauthn($request)->processCreate(
                $clientData,
                Passkeys::b64urlDecode($data['attestationObject']),
                $challenge,
                true,   // vérification de la personne obligatoire
                true,
                false,  // pas de certificats racines à imposer pour un usage personnel
            );
        } catch (WebAuthnException $e) {
            return response()->json(['message' => $this->passkeys->failureMessage($e)], 422);
        }

        $credentialId = Passkeys::b64urlEncode($result->credentialId);
        if (Passkey::where('credential_id', $credentialId)->exists()) {
            return response()->json(['message' => 'Cet appareil est déjà enregistré.'], 422);
        }

        $passkey = $request->user()->passkeys()->create([
            'name' => $data['name'] ?? 'Cet appareil',
            'credential_id' => $credentialId,
            'public_key' => $result->credentialPublicKey,
            'sign_count' => $result->signatureCounter ?? 0,
        ]);

        return response()->json($passkey->only(['id', 'name', 'created_at']), 201);
    }

    /* ---------- Connexion (invité) ---------- */

    public function loginOptions(Request $request): JsonResponse
    {
        $webauthn = $this->passkeys->webauthn($request);
        $args = $webauthn->getGetArgs([], 60, true, true, true, true, true, true);
        $this->passkeys->remember($request, Passkeys::LOGIN_KEY, $webauthn->getChallenge());

        return response()->json($args);
    }

    public function login(Request $request): JsonResponse
    {
        $key = 'passkey|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return response()->json(['message' => 'Trop de tentatives. Réessaie plus tard.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        $data = $request->validate([
            'id' => ['required', 'string', 'max:512'],
            'clientDataJSON' => ['required', 'string', 'max:4096'],
            'authenticatorData' => ['required', 'string', 'max:4096'],
            'signature' => ['required', 'string', 'max:4096'],
            'userHandle' => ['nullable', 'string', 'max:256'],
        ]);

        $fail = function (string $message = 'Vérification impossible. Réessaie, ou connecte-toi avec ton mot de passe.') use ($key) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            return response()->json(['message' => $message], 422);
        };

        // Le défi est à usage unique, qu'il soit bon ou non.
        $challenge = $this->passkeys->pull($request, Passkeys::LOGIN_KEY);
        $clientData = Passkeys::b64urlDecode($data['clientDataJSON']);
        $passkey = Passkey::with('user')->where('credential_id', $data['id'])->first();

        if ($challenge === null || $passkey === null || ! $this->passkeys->originMatches($request, $clientData)) {
            return $fail();
        }
        // L'appareil indique à qui appartient la clé : cela doit correspondre au propriétaire enregistré.
        if (filled($data['userHandle'] ?? null) && ! hash_equals($this->passkeys->userHandle($passkey->user), Passkeys::b64urlDecode($data['userHandle']))) {
            return $fail();
        }

        $webauthn = $this->passkeys->webauthn($request);
        try {
            $webauthn->processGet(
                $clientData,
                Passkeys::b64urlDecode($data['authenticatorData']),
                Passkeys::b64urlDecode($data['signature']),
                $passkey->public_key,
                $challenge,
                $passkey->sign_count,
                true,   // le système doit avoir vérifié la personne (visage, empreinte ou code)
                true,
            );
        } catch (WebAuthnException $e) {
            report($e);

            return $fail();
        }

        $passkey->forceFill(['sign_count' => $webauthn->getSignatureCounter() ?? $passkey->sign_count, 'last_used_at' => now()])->save();

        RateLimiter::clear($key);
        Auth::guard('web')->login($passkey->user, true);
        $request->session()->regenerate();

        $user = $passkey->user;

        return response()->json(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'timezone' => $user->timezone]);
    }
}
