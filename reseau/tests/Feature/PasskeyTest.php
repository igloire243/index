<?php

namespace Tests\Feature;

use App\Models\Passkey;
use App\Models\User;
use App\Services\Passkeys;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSSLAsymmetricKey;
use Tests\TestCase;

/**
 * Teste la vérification WebAuthn côté serveur avec un authentificateur simulé : de vraies clés ES256 et de vraies
 * signatures, exactement ce que ferait un téléphone avec Face ID ou une empreinte.
 */
class PasskeyTest extends TestCase
{
    use RefreshDatabase;

    private const RP = 'localhost';

    private const UP = 0x01;

    private const UV = 0x04;

    private const AT = 0x40;

    private User $user;

    private OpenSSLAsymmetricKey $key;

    private string $pem;

    private string $x;

    private string $y;

    private string $credId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['email' => 'moi@example.com']);
        [$this->key, $this->pem, $this->x, $this->y] = $this->newKey();
        $this->credId = random_bytes(16);
    }

    /** @return array{0: OpenSSLAsymmetricKey, 1: string, 2: string, 3: string} */
    private function newKey(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($key);

        return [$key, $d['key'], $d['ec']['x'], $d['ec']['y']];
    }

    private function origin(): string
    {
        return rtrim(url('/'), '/'); // schéma, domaine et port tels que le serveur les voit
    }

    private function b64(string $s): string
    {
        return Passkeys::b64urlEncode($s);
    }

    private function enroll(int $signCount = 0, ?User $user = null): Passkey
    {
        return ($user ?? $this->user)->passkeys()->create([
            'name' => 'Mon téléphone', 'credential_id' => $this->b64($this->credId), 'public_key' => $this->pem, 'sign_count' => $signCount,
        ]);
    }

    /** Une réponse « get » telle que la produirait le téléphone. */
    private function assertion(string $challenge, array $o = []): array
    {
        $o += ['key' => $this->key, 'rp' => self::RP, 'origin' => $this->origin(), 'flags' => self::UP | self::UV, 'counter' => 1, 'type' => 'webauthn.get', 'handle' => null, 'id' => $this->b64($this->credId)];
        $clientData = json_encode(['type' => $o['type'], 'challenge' => $this->b64($challenge), 'origin' => $o['origin']]);
        $authData = hash('sha256', $o['rp'], true).chr($o['flags']).pack('N', $o['counter']);
        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $o['key'], OPENSSL_ALGO_SHA256);

        return [
            'id' => $o['id'],
            'clientDataJSON' => $this->b64($clientData),
            'authenticatorData' => $this->b64($authData),
            'signature' => $this->b64($signature),
            'userHandle' => $o['handle'],
        ];
    }

    private function attempt(array $overrides = [], ?string $sessionChallenge = null, ?string $signedChallenge = null)
    {
        $challenge = $signedChallenge ?? random_bytes(32);
        $session = $sessionChallenge === null ? $challenge : $sessionChallenge;

        return $this->withSession([Passkeys::LOGIN_KEY => base64_encode($session)])
            ->postJson('/api/passkey-login', $this->assertion($challenge, $overrides));
    }

    /** Un objet d'attestation de format « none », encodé en CBOR à la main. */
    private function attestation(array $o = []): array
    {
        $o += ['rp' => self::RP, 'origin' => $this->origin(), 'flags' => self::UP | self::UV | self::AT, 'challenge' => random_bytes(32), 'type' => 'webauthn.create'];
        $cose = "\xA5\x01\x02\x03\x26\x20\x01\x21\x58\x20".$this->x."\x22\x58\x20".$this->y;
        $authData = hash('sha256', $o['rp'], true).chr($o['flags']).pack('N', 0).str_repeat("\0", 16).pack('n', strlen($this->credId)).$this->credId.$cose;
        $object = "\xA3\x63fmt\x64none\x67attStmt\xA0\x68authData\x59".pack('n', strlen($authData)).$authData;
        $clientData = json_encode(['type' => $o['type'], 'challenge' => $this->b64($o['challenge']), 'origin' => $o['origin']]);

        return ['challenge' => $o['challenge'], 'body' => ['name' => 'Mon téléphone', 'clientDataJSON' => $this->b64($clientData), 'attestationObject' => $this->b64($object)]];
    }

    private function register(array $overrides = [], bool $withChallenge = true)
    {
        $att = $this->attestation($overrides);
        $request = $this->actingAs($this->user);
        if ($withChallenge) {
            $request = $request->withSession([Passkeys::REGISTER_KEY => base64_encode($att['challenge'])]);
        }

        return $request->postJson('/api/passkeys', $att['body']);
    }

    /* ---------- Connexion ---------- */

    public function test_login_options_ask_for_user_verification_without_naming_anyone(): void
    {
        $this->enroll();

        $r = $this->postJson('/api/passkey-login/options')->assertOk();

        $this->assertSame(self::RP, $r->json('publicKey.rpId'));
        $this->assertSame('required', $r->json('publicKey.userVerification'));
        $this->assertNotEmpty($r->json('publicKey.challenge'));
        $this->assertNull($r->json('publicKey.allowCredentials'), 'aucun identifiant n’est annoncé : rien n’est révélé sur les comptes');
    }

    public function test_valid_assertion_logs_the_owner_in(): void
    {
        $this->enroll();

        $this->attempt(['counter' => 7, 'handle' => $this->b64(app(Passkeys::class)->userHandle($this->user))])
            ->assertOk()->assertJsonPath('email', 'moi@example.com');

        $this->getJson('/api/me')->assertOk()->assertJsonPath('id', $this->user->id);
        $passkey = Passkey::firstOrFail();
        $this->assertSame(7, $passkey->sign_count);
        $this->assertNotNull($passkey->last_used_at);
    }

    public function test_the_challenge_is_single_use(): void
    {
        $this->enroll();
        $challenge = random_bytes(32);
        $assertion = $this->assertion($challenge);

        $this->withSession([Passkeys::LOGIN_KEY => base64_encode($challenge)])->postJson('/api/passkey-login', $assertion)->assertOk();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/passkey-login', $assertion)->assertStatus(422); // rejouer la même réponse échoue
    }

    public function test_login_without_a_prior_challenge_fails(): void
    {
        $this->enroll();

        $this->postJson('/api/passkey-login', $this->assertion(random_bytes(32)))->assertStatus(422);
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_forged_or_invalid_assertions_are_rejected(): void
    {
        $this->enroll(5);
        [$otherKey] = $this->newKey();
        $cases = [
            'défi différent' => fn () => $this->attempt([], sessionChallenge: random_bytes(32)),
            'mauvaise origine' => fn () => $this->attempt(['origin' => 'https://evil.example']),
            'site différent (rpId)' => fn () => $this->attempt(['rp' => 'evil.example']),
            'vérification de la personne absente' => fn () => $this->attempt(['flags' => self::UP]),
            'signature d’une autre clé' => fn () => $this->attempt(['key' => $otherKey]),
            'identifiant inconnu' => fn () => $this->attempt(['id' => $this->b64(random_bytes(16))]),
            'compteur non croissant (clé clonée)' => fn () => $this->attempt(['counter' => 5]),
            'mauvais type' => fn () => $this->attempt(['type' => 'webauthn.create']),
            'propriétaire différent' => fn () => $this->attempt(['handle' => $this->b64(random_bytes(32))]),
        ];

        foreach ($cases as $label => $run) {
            $run()->assertStatus(422, $label);
            $this->getJson('/api/me')->assertUnauthorized();
        }
    }

    public function test_repeated_failures_lock_the_endpoint(): void
    {
        $this->enroll();
        for ($i = 0; $i < 10; $i++) {
            $this->attempt(['origin' => 'https://evil.example'])->assertStatus(422);
        }

        $this->attempt()->assertStatus(429)->assertHeader('Retry-After'); // même une réponse valide est refusée
    }

    public function test_an_assertion_for_one_users_key_never_logs_in_another(): void
    {
        $other = User::factory()->create();
        $this->enroll(user: $other);

        $this->attempt()->assertOk()->assertJsonPath('id', $other->id);
        $this->assertNotSame($this->user->id, $other->id);
    }

    /* ---------- Enregistrement ---------- */

    public function test_the_origin_declared_in_app_url_is_accepted_behind_a_tunnel_and_others_are_not(): void
    {
        config(['app.url' => 'https://reseau.example.com', 'reseau.webauthn.rp_id' => 'reseau.example.com']);
        $this->enroll();

        // Le serveur voit http://localhost (le tunnel réécrit l'adresse) mais le navigateur est sur le domaine public.
        $this->attempt(['rp' => 'reseau.example.com', 'origin' => 'https://reseau.example.com'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->attempt(['rp' => 'reseau.example.com', 'origin' => 'https://autre.example.com'])->assertStatus(422);
        $this->attempt(['rp' => 'reseau.example.com', 'origin' => 'http://reseau.example.com'])->assertStatus(422); // mauvais schéma
    }

    public function test_registration_options_target_a_built_in_discoverable_verified_authenticator(): void
    {
        $this->enroll();
        $r = $this->actingAs($this->user)->postJson('/api/passkeys/options')->assertOk();

        $this->assertSame(self::RP, $r->json('publicKey.rp.id'));
        $this->assertSame('moi@example.com', $r->json('publicKey.user.name'));
        $this->assertSame('required', $r->json('publicKey.authenticatorSelection.residentKey'));
        $this->assertSame('required', $r->json('publicKey.authenticatorSelection.userVerification'));
        $this->assertSame('platform', $r->json('publicKey.authenticatorSelection.authenticatorAttachment'));
        $this->assertCount(1, $r->json('publicKey.excludeCredentials'));
    }

    public function test_a_device_can_be_registered_and_then_used_to_log_in(): void
    {
        $this->register()->assertCreated()->assertJsonPath('name', 'Mon téléphone');

        $stored = Passkey::firstOrFail();
        $this->assertSame($this->b64($this->credId), $stored->getRawOriginal('credential_id'));
        $this->assertStringContainsString('BEGIN PUBLIC KEY', $stored->public_key);
        $this->assertSame($this->user->id, $stored->user_id);

        $this->app['auth']->forgetGuards();
        $this->attempt()->assertOk()->assertJsonPath('id', $this->user->id);
    }

    public function test_registration_is_refused_when_unsafe(): void
    {
        $this->register(['origin' => 'https://evil.example'])->assertStatus(422);
        $this->register(['flags' => self::UP | self::AT])->assertStatus(422);       // personne non vérifiée
        $this->register(['rp' => 'evil.example'])->assertStatus(422);
        $this->register(['type' => 'webauthn.get'])->assertStatus(422);
        $this->register([], withChallenge: false)->assertStatus(422);               // aucun défi en attente
        $this->assertDatabaseCount('passkeys', 0);
    }

    public function test_the_same_device_cannot_be_registered_twice(): void
    {
        $this->register()->assertCreated();
        $this->register()->assertStatus(422);
        $this->assertDatabaseCount('passkeys', 1);
    }

    public function test_registration_requires_a_logged_in_user(): void
    {
        $this->postJson('/api/passkeys/options')->assertUnauthorized();
        $this->postJson('/api/passkeys', $this->attestation()['body'])->assertUnauthorized();
        $this->getJson('/api/passkeys')->assertUnauthorized();
    }

    public function test_listing_and_deleting_only_concern_the_owner_and_hide_key_material(): void
    {
        $mine = $this->enroll();
        $other = User::factory()->create();
        $theirs = $other->passkeys()->create(['name' => 'Autre', 'credential_id' => $this->b64(random_bytes(16)), 'public_key' => $this->pem]);

        $list = $this->actingAs($this->user)->getJson('/api/passkeys')->assertOk()->assertJsonCount(1);
        $this->assertSame($mine->id, $list->json('0.id'));
        $this->assertArrayNotHasKey('public_key', $list->json('0'));
        $this->assertArrayNotHasKey('credential_id', $list->json('0'));

        $this->deleteJson("/api/passkeys/{$theirs->id}")->assertNotFound();
        $this->deleteJson("/api/passkeys/{$mine->id}")->assertOk();
        $this->assertDatabaseMissing('passkeys', ['id' => $mine->id]);
        $this->assertDatabaseHas('passkeys', ['id' => $theirs->id]);
    }

    public function test_deleting_a_user_deletes_their_passkeys(): void
    {
        $this->enroll();
        $this->user->delete();

        $this->assertDatabaseCount('passkeys', 0);
    }
}
