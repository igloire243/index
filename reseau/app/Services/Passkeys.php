<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

/**
 * Connexion par clé d'accès (WebAuthn / « passkey »).
 *
 * La clé secrète ne quitte jamais le téléphone. C'est le système (Face ID, empreinte, code de l'appareil) qui
 * vérifie la personne ; le serveur ne reçoit qu'une signature et ne stocke que la clé publique. Aucune donnée
 * biométrique n'est donc envoyée ni conservée par l'application.
 */
class Passkeys
{
    public const REGISTER_KEY = 'passkey.register_challenge';

    public const LOGIN_KEY = 'passkey.login_challenge';

    public function rpId(Request $request): string
    {
        return config('reseau.webauthn.rp_id') ?: $request->getHost();
    }

    public function webauthn(Request $request): WebAuthn
    {
        return new WebAuthn(config('app.name', 'Réseau'), $this->rpId($request), null, true);
    }

    /** Identifiant opaque et stable de l'utilisateur côté appareil (ne révèle ni l'e-mail ni l'identifiant interne). */
    public function userHandle(User $user): string
    {
        return hash_hmac('sha256', 'reseau-user-'.$user->id, (string) config('app.key'), true);
    }

    public static function b64urlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/').str_repeat('=', (4 - strlen($data) % 4) % 4), true);
    }

    public static function b64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /** Mémorise le défi dans la session : il ne sert qu'une fois. */
    public function remember(Request $request, string $key, ByteBuffer $challenge): void
    {
        $request->session()->put($key, base64_encode($challenge->getBinaryString()));
    }

    public function pull(Request $request, string $key): ?string
    {
        $value = $request->session()->pull($key);

        return is_string($value) ? (base64_decode($value, true) ?: null) : null;
    }

    /**
     * Vérifie que l'origine annoncée par le navigateur est bien celle du site (schéma, domaine et port),
     * en plus des contrôles de la bibliothèque.
     */
    public function originMatches(Request $request, string $clientDataJson): bool
    {
        $origin = json_decode($clientDataJson)->origin ?? null;
        if (! is_string($origin)) {
            return false;
        }

        // L'origine vue par le serveur, et celle déclarée dans APP_URL (utile derrière un tunnel ou un proxy
        // qui réécrit l'adresse). Toute autre origine est refusée.
        $allowed = [$request->getSchemeAndHttpHost()];
        $app = parse_url((string) config('app.url'));
        if (! empty($app['scheme']) && ! empty($app['host'])) {
            $allowed[] = $app['scheme'].'://'.$app['host'].(isset($app['port']) ? ':'.$app['port'] : '');
        }

        return in_array($origin, array_map('strtolower', $allowed), true);
    }

    public function failureMessage(WebAuthnException $e): string
    {
        report($e);

        return 'Vérification impossible. Réessaie, ou connecte-toi avec ton mot de passe.';
    }
}
