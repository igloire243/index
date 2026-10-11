<?php

namespace Tests\Feature;

use App\Models\DossierFile;
use App\Models\User;
use App\Services\VaultExport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ExportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('vault');
        RateLimiter::clear('vault|1|127.0.0.1');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    /** Déchiffre comme le ferait l'outil `public/dechiffrer.html` (mêmes algorithmes), pour vérifier le format produit. */
    private function decrypt(string $raw, string $password): string
    {
        $this->assertSame(VaultExport::MAGIC, substr($raw, 0, 5));
        $salt = substr($raw, 5, 16);
        $iv = substr($raw, 21, 12);
        $rest = substr($raw, 33);
        $cipher = substr($rest, 0, -16);
        $tag = substr($rest, -16);

        $key = hash_pbkdf2('sha256', $password, $salt, 310000, 32, true);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        $this->assertNotFalse($plain, 'déchiffrement raté : mauvais mot de passe ou format incohérent');

        return $plain;
    }

    public function test_requires_the_vault_to_be_unlocked(): void
    {
        $this->postJson('/api/vault/lock')->assertOk();
        $this->postJson('/api/export', ['password' => 'un-mot-de-passe-long'])->assertStatus(403);
    }

    public function test_password_is_validated(): void
    {
        $this->postJson('/api/export', ['password' => 'court'])->assertStatus(422);
        $this->postJson('/api/export', [])->assertStatus(422);
    }

    public function test_the_archive_contains_dossiers_and_secrets_and_decrypts_correctly(): void
    {
        $d = $this->postJson('/api/dossiers', ['title' => 'Vérifications', 'summary' => 'Confidentiel'])->json();
        $bytes = $this->png();
        $init = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'preuve.png', 'size' => strlen($bytes), 'mime' => 'image/png'])->json();
        $this->call('PUT', "/api/dossier-files/{$init['id']}/chunks/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $bytes)->assertOk();
        $this->postJson("/api/dossier-files/{$init['id']}/complete")->assertOk();
        $this->postJson('/api/secrets', ['type' => 'site', 'title' => 'Facebook', 'username' => 'moi@example.com', 'secret' => 'Tr0ub4dor&3'])->assertCreated();

        $res = $this->postJson('/api/export', ['password' => 'un-mot-de-passe-long'])->assertOk();
        $this->assertSame('application/octet-stream', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertMatchesRegularExpression('/reseau-export-\d{4}-\d{2}-\d{2}\.enc/', $res->headers->get('Content-Disposition'));

        $zipBytes = $this->decrypt($res->getContent(), 'un-mot-de-passe-long');

        $zipPath = tempnam(sys_get_temp_dir(), 'test-export');
        file_put_contents($zipPath, $zipBytes);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);

        $dossiers = json_decode($zip->getFromName('dossiers.json'), true);
        $this->assertSame('Vérifications', $dossiers[0]['titre']);
        $this->assertSame('Confidentiel', $dossiers[0]['resume']);
        $this->assertSame('preuve.png', $dossiers[0]['fichiers'][0]['nom']);

        $secrets = json_decode($zip->getFromName('mots-de-passe.json'), true);
        $this->assertSame('Facebook', $secrets[0]['titre']);
        $this->assertSame('Tr0ub4dor&3', $secrets[0]['secret']);

        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[] = $zip->getNameIndex($i);
        }
        $match = array_values(array_filter($entries, fn ($n) => str_ends_with($n, 'preuve.png')));
        $this->assertNotEmpty($match, 'le fichier du dossier est dans l’archive');
        $this->assertSame($bytes, $zip->getFromName($match[0]));

        $zip->close();
        unlink($zipPath);
    }

    public function test_a_wrong_password_does_not_decrypt(): void
    {
        $this->postJson('/api/dossiers', ['title' => 'X'])->assertCreated();
        $res = $this->postJson('/api/export', ['password' => 'un-mot-de-passe-long'])->assertOk();

        $salt = substr($res->getContent(), 5, 16);
        $iv = substr($res->getContent(), 21, 12);
        $rest = substr($res->getContent(), 33);
        $key = hash_pbkdf2('sha256', 'un-autre-mot-de-passe', $salt, 310000, 32, true);
        $plain = openssl_decrypt(substr($rest, 0, -16), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, substr($rest, -16));
        $this->assertFalse($plain);
    }

    public function test_an_empty_vault_still_produces_a_valid_archive(): void
    {
        $res = $this->postJson('/api/export', ['password' => 'un-mot-de-passe-long'])->assertOk();
        $zipBytes = $this->decrypt($res->getContent(), 'un-mot-de-passe-long');
        $this->assertStringStartsWith("PK\x03\x04", $zipBytes);
    }

    public function test_a_huge_vault_is_refused_before_building_anything(): void
    {
        $d = $this->postJson('/api/dossiers', ['title' => 'Gros dossier'])->json();
        DossierFile::create([
            'dossier_id' => $d['id'], 'user_id' => $this->user->id, 'uuid' => (string) Str::uuid(),
            'name' => 'video.mp4', 'mime' => 'video/mp4', 'kind' => 'video', 'size' => 800 * 1024 * 1024,
            'total_chunks' => 1, 'received' => [0], 'encrypted_key' => 'x', 'status' => 'ready',
        ]);

        $this->postJson('/api/export', ['password' => 'un-mot-de-passe-long'])->assertStatus(422);
    }

    public function test_isolation_between_users(): void
    {
        $this->postJson('/api/dossiers', ['title' => 'Confidentiel de moi'])->assertCreated();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
        $res = $this->postJson('/api/export', ['password' => 'un-mot-de-passe-long'])->assertOk();

        $zipBytes = $this->decrypt($res->getContent(), 'un-mot-de-passe-long');
        $this->assertStringNotContainsString('Confidentiel de moi', $zipBytes);
    }
}
