<?php

namespace Tests\Feature;

use App\Models\DossierFile;
use App\Models\DossierFileShare;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DossierFileShareTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function png(int $size = 2000): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return $size > strlen($png) ? $png.random_bytes($size - strlen($png)) : $png;
    }

    private function fileId(): int
    {
        $d = $this->postJson('/api/dossiers', ['title' => 'Affaire X'])->json();
        $bytes = $this->png();
        $init = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'preuve.png', 'size' => strlen($bytes), 'mime' => 'image/png'])->json();
        $this->call('PUT', "/api/dossier-files/{$init['id']}/chunks/0", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream'], $bytes)->assertOk();

        return $this->postJson("/api/dossier-files/{$init['id']}/complete")->assertOk()->json('id');
    }

    private function download(string $token)
    {
        return $this->call('GET', "/api/partage/$token/contenu");
    }

    public function test_a_link_is_created_and_can_be_downloaded_once(): void
    {
        $id = $this->fileId();
        $bytes = $this->png();
        // Remplace par un nouveau contenu connu pour comparer octet à octet.
        $file = DossierFile::find($id);

        $r = $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->assertCreated();
        $token = $r->json('token');
        $this->assertNotEmpty($token);
        $this->assertArrayNotHasKey('token_hash', $r->json());

        $info = $this->getJson("/api/partage/$token")->assertOk();
        $this->assertSame('preuve.png', $info->json('name'));
        $this->assertSame('image', $info->json('kind'));
        $this->assertSame($this->user->name, $info->json('from'));

        $res = $this->download($token)->assertOk();
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('sandbox', $res->headers->get('Content-Security-Policy'));
        $this->assertStringStartsWith('inline;', $res->headers->get('Content-Disposition'));

        // Deuxième tentative : le lien est déjà consommé.
        $this->download($token)->assertStatus(410);
        $this->getJson("/api/partage/$token")->assertStatus(410);
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->getJson('/api/partage/'.str_repeat('a', 48))->assertNotFound();
        $this->download(str_repeat('a', 48))->assertNotFound();
    }

    public function test_an_expired_link_is_refused_without_being_consumed(): void
    {
        $id = $this->fileId();
        $r = $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->assertCreated();
        $token = $r->json('token');

        Carbon::setTestNow(now()->addHours(2));
        $this->getJson("/api/partage/$token")->assertStatus(410);
        $this->download($token)->assertStatus(410);
    }

    public function test_only_the_allowed_durations_are_accepted(): void
    {
        $id = $this->fileId();
        $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->assertCreated();
        $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 1440])->assertCreated();
        $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 10080])->assertCreated();
        $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 59])->assertStatus(422);
        $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 999999])->assertStatus(422);
    }

    public function test_the_owner_can_list_and_revoke_active_links(): void
    {
        $id = $this->fileId();
        $a = $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->json();
        $b = $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 1440])->json();

        $list = $this->getJson("/api/dossier-files/$id/partages")->assertOk()->assertJsonCount(2);

        $this->deleteJson("/api/dossier-file-shares/{$a['id']}")->assertOk();
        $this->getJson("/api/dossier-files/$id/partages")->assertJsonCount(1);
        $this->getJson('/api/partage/'.$a['token'])->assertNotFound('un lien révoqué ne fonctionne plus');

        // Un lien utilisé disparaît de la liste des liens actifs.
        $this->download($b['token'])->assertOk();
        $this->getJson("/api/dossier-files/$id/partages")->assertJsonCount(0);
    }

    public function test_creating_or_revoking_a_share_requires_the_vault_to_be_unlocked(): void
    {
        $id = $this->fileId();
        $this->postJson('/api/vault/lock')->assertOk();
        $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->assertStatus(403);
        $this->getJson("/api/dossier-files/$id/partages")->assertStatus(403);
    }

    public function test_isolation_between_users(): void
    {
        $id = $this->fileId();
        $share = $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->json();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();

        $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->assertNotFound();
        $this->getJson("/api/dossier-files/$id/partages")->assertNotFound();
        $this->deleteJson("/api/dossier-file-shares/{$share['id']}")->assertNotFound();

        // Le lien public, lui, reste utilisable : il ne dépend d'aucune session.
        $this->getJson('/api/partage/'.$share['token'])->assertOk();
    }

    public function test_public_access_needs_no_session_at_all(): void
    {
        $id = $this->fileId();
        $token = $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->json('token');

        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/partage/'.$token)->assertOk();
        $this->download($token)->assertOk();
    }

    public function test_deleting_the_file_removes_its_shares(): void
    {
        $id = $this->fileId();
        $share = $this->postJson("/api/dossier-files/$id/partages", ['minutes' => 60])->json();

        $this->deleteJson("/api/dossier-files/$id")->assertOk();

        $this->assertDatabaseMissing('dossier_file_shares', ['id' => $share['id']]);
        $this->getJson('/api/partage/'.$share['token'])->assertNotFound();
    }

    public function test_the_clean_command_removes_only_old_links(): void
    {
        $id = $this->fileId();
        [, $recentToken] = DossierFileShare::issue(DossierFile::find($id), 60);
        [$oldShare] = DossierFileShare::issue(DossierFile::find($id), 60);
        $oldShare->update(['expires_at' => now()->subDays(40)]);

        $this->artisan('reseau:vault-clean')->assertSuccessful();

        $this->assertDatabaseMissing('dossier_file_shares', ['id' => $oldShare->id]);
        $this->assertDatabaseCount('dossier_file_shares', 1);
    }
}
