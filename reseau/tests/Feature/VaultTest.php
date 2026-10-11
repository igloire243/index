<?php

namespace Tests\Feature;

use App\Models\DossierFile;
use App\Models\User;
use App\Services\VaultCrypto;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VaultTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function unlock(): void
    {
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
    }

    private function dossier(array $o = []): array
    {
        return $this->postJson('/api/dossiers', $o + ['title' => 'Affaire X'])->assertCreated()->json();
    }

    /** Un vrai PNG 1×1, éventuellement complété par du remplissage pour atteindre $size octets. */
    private function png(int $size = 0): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        return $size > strlen($png) ? $png.random_bytes($size - strlen($png)) : $png;
    }

    /** Envoie un fichier complet par morceaux et renvoie la fiche terminée. */
    private function upload(int $dossierId, string $bytes, string $name = 'photo.png', string $mime = 'image/png'): array
    {
        $init = $this->postJson("/api/dossiers/$dossierId/files", ['name' => $name, 'size' => strlen($bytes), 'mime' => $mime])->assertCreated();
        $id = $init->json('id');
        foreach (str_split($bytes, VaultCrypto::CHUNK) as $i => $piece) {
            $this->sendChunk($id, $i, $piece)->assertOk();
        }

        return $this->postJson("/api/dossier-files/$id/complete")->assertOk()->json();
    }

    private function sendChunk(int $id, int $index, string $bytes)
    {
        return $this->call('PUT', "/api/dossier-files/$id/chunks/$index", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $bytes);
    }

    private function download(int $id, array $headers = [])
    {
        $server = ['HTTP_ACCEPT' => '*/*'];
        foreach ($headers as $k => $v) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $k))] = $v;
        }

        return $this->call('GET', "/api/dossier-files/$id/content", [], [], [], $server);
    }

    /* ---------- Verrou ---------- */

    public function test_everything_requires_authentication_and_an_unlocked_vault(): void
    {
        $this->getJson('/api/dossiers')->assertStatus(403)->assertJson(['code' => 'vault_locked']);
        $this->postJson('/api/dossiers', ['title' => 'X'])->assertStatus(403);
        $this->getJson('/api/dossier-files/1/content')->assertStatus(403);

        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/vault')->assertUnauthorized();
    }

    public function test_status_reports_locked_then_unlocked(): void
    {
        $this->getJson('/api/vault')->assertOk()->assertJson(['unlocked' => false, 'expires_in' => 0]);
        $this->unlock();
        $s = $this->getJson('/api/vault')->assertOk()->json();
        $this->assertTrue($s['unlocked']);
        $this->assertGreaterThan(0, $s['expires_in']);
        $this->assertSame(0, $s['used_bytes']);
        $this->getJson('/api/dossiers')->assertOk();
    }

    public function test_wrong_password_is_refused_and_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/vault/unlock', ['password' => 'nope'])->assertStatus(422);
        }
        // Même le bon mot de passe est refusé tant que le blocage dure.
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertStatus(429);
        $this->getJson('/api/dossiers')->assertStatus(403);
    }

    public function test_a_correct_password_resets_the_failure_counter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/vault/unlock', ['password' => 'nope'])->assertStatus(422);
        }
        $this->unlock();
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/vault/unlock', ['password' => 'nope'])->assertStatus(422);
        }
    }

    public function test_the_vault_locks_after_inactivity_and_activity_extends_it(): void
    {
        Carbon::setTestNow('2026-10-12 08:00:00');
        $this->unlock();

        Carbon::setTestNow('2026-10-12 08:10:00');
        $this->getJson('/api/dossiers')->assertOk();           // renouvelle jusqu'à 08:25

        Carbon::setTestNow('2026-10-12 08:22:00');
        $this->getJson('/api/dossiers')->assertOk();           // toujours ouvert grâce au renouvellement

        Carbon::setTestNow('2026-10-12 08:40:00');
        $this->getJson('/api/dossiers')->assertStatus(403)->assertJson(['code' => 'vault_locked']);
        $this->getJson('/api/vault')->assertJson(['unlocked' => false]);
    }

    public function test_locking_closes_the_vault_immediately(): void
    {
        $this->unlock();
        $this->postJson('/api/vault/lock')->assertOk()->assertJson(['unlocked' => false]);
        $this->getJson('/api/dossiers')->assertStatus(403);
    }

    /* ---------- Dossiers ---------- */

    public function test_a_dossier_is_created_listed_updated_and_its_summary_is_encrypted_at_rest(): void
    {
        $this->unlock();
        $contact = $this->postJson('/api/contacts', ['name' => 'Paul Mbala'])->json('id');

        $d = $this->dossier(['contact_id' => $contact, 'summary' => 'Secret très secret', 'title' => 'Vérifications']);
        $this->assertSame('Paul Mbala', $d['subject'], 'le sujet reprend le nom du contact');
        $this->assertSame('Secret très secret', $d['summary']);
        $this->assertArrayNotHasKey('user_id', $d);

        $raw = \DB::table('dossiers')->where('id', $d['id'])->value('summary');
        $this->assertStringNotContainsString('Secret', $raw);

        $this->putJson("/api/dossiers/{$d['id']}", ['title' => 'Renommé', 'summary' => 'Autre'])->assertOk()->assertJson(['title' => 'Renommé', 'summary' => 'Autre']);
        $list = $this->getJson('/api/dossiers')->assertOk()->assertJsonCount(1);
        $this->assertSame(0, $list->json('0.files_count'));
        $this->assertArrayNotHasKey('summary', $list->json('0'), 'la liste ne contient pas le résumé');

        $this->getJson("/api/dossiers?contact_id=$contact")->assertJsonCount(1);
        $this->getJson('/api/dossiers?contact_id=999')->assertJsonCount(0);
    }

    public function test_dossier_validation(): void
    {
        $this->unlock();
        $this->postJson('/api/dossiers', ['title' => ''])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->postJson('/api/dossiers', ['title' => 'X', 'contact_id' => 12345])->assertStatus(422)->assertJsonValidationErrors('contact_id');
    }

    public function test_other_users_data_is_invisible_and_untouchable(): void
    {
        $this->unlock();
        $mine = $this->dossier();
        $file = $this->upload($mine['id'], $this->png());

        $other = User::factory()->create();
        $theirContact = $this->actingAs($other)->postJson('/api/contacts', ['name' => 'Autre'])->json('id');
        $this->actingAs($other);
        $this->unlock();

        $this->getJson('/api/dossiers')->assertJsonCount(0);
        $this->getJson("/api/dossiers/{$mine['id']}")->assertNotFound();
        $this->putJson("/api/dossiers/{$mine['id']}", ['title' => 'Piraté'])->assertNotFound();
        $this->deleteJson("/api/dossiers/{$mine['id']}")->assertNotFound();
        $this->postJson("/api/dossiers/{$mine['id']}/files", ['name' => 'a.png', 'size' => 10])->assertNotFound();
        $this->download($file['id'])->assertNotFound();
        $this->putJson("/api/dossier-files/{$file['id']}", ['caption' => 'x'])->assertNotFound();
        $this->deleteJson("/api/dossier-files/{$file['id']}")->assertNotFound();
        $this->sendChunk($file['id'], 0, 'x')->assertNotFound();

        // Un dossier ne peut pas être rattaché au contact de quelqu'un d'autre.
        $this->actingAs($this->user);
        $this->unlock();
        $this->postJson('/api/dossiers', ['title' => 'X', 'contact_id' => $theirContact])->assertStatus(422);
        $this->assertDatabaseCount('dossier_files', 1);
    }

    /* ---------- Envoi par morceaux ---------- */

    public function test_a_small_file_roundtrips_and_is_encrypted_on_disk(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $bytes = $this->png();
        $f = $this->upload($d['id'], $bytes, 'preuve.png', 'image/png');

        $this->assertSame('image', $f['kind']);
        $this->assertSame('image/png', $f['mime']);
        $this->assertSame('ready', $f['status']);
        $this->assertArrayNotHasKey('encrypted_key', $f);
        $this->assertArrayNotHasKey('uuid', $f);

        $model = DossierFile::findOrFail($f['id']);
        $path = $model->user_id.'/'.$model->uuid.'.bin';
        Storage::disk('vault')->assertExists($path);
        $onDisk = Storage::disk('vault')->get($path);
        $this->assertSame(VaultCrypto::cipherSize(strlen($bytes)), strlen($onDisk));
        $this->assertStringNotContainsString(substr($bytes, 0, 16), $onDisk);
        $this->assertStringNotContainsString('PNG', $onDisk);

        $res = $this->download($f['id'])->assertOk();
        $this->assertSame($bytes, $res->streamedContent());
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertStringContainsString('sandbox', $res->headers->get('Content-Security-Policy'));
        $this->assertSame('image/png', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline;', $res->headers->get('Content-Disposition'));
        $this->assertSame('bytes', $res->headers->get('Accept-Ranges'));
    }

    public function test_a_multi_chunk_file_roundtrips_with_out_of_order_chunks_and_ranges(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $bytes = $this->png(VaultCrypto::CHUNK * 2 + 12345);

        $id = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'grand.png', 'size' => strlen($bytes), 'mime' => 'image/png'])->assertCreated()->assertJson(['total_chunks' => 3, 'chunk_size' => VaultCrypto::CHUNK])->json('id');
        $pieces = str_split($bytes, VaultCrypto::CHUNK);

        $this->sendChunk($id, 0, $pieces[0])->assertOk();         // le type est vérifié sur le 1er morceau
        $this->sendChunk($id, 2, $pieces[2])->assertOk();
        $this->postJson("/api/dossier-files/$id/complete")->assertStatus(422);   // il manque le morceau 1
        $this->sendChunk($id, 1, $pieces[1])->assertOk();
        $this->sendChunk($id, 1, $pieces[1])->assertOk()->assertJson(['received' => 3]); // renvoi sans effet néfaste
        $this->postJson("/api/dossier-files/$id/complete")->assertOk();

        $this->assertSame($bytes, $this->download($id)->streamedContent());

        // Plage à cheval sur deux morceaux.
        $start = VaultCrypto::CHUNK - 10;
        $r = $this->download($id, ['Range' => 'bytes='.$start.'-'.($start + 99)])->assertStatus(206);
        $this->assertSame(substr($bytes, $start, 100), $r->streamedContent());
        $r->assertHeader('Content-Range', 'bytes '.$start.'-'.($start + 99).'/'.strlen($bytes));
        $this->assertSame('100', $r->headers->get('Content-Length'));

        // Plage ouverte et plage « derniers octets ».
        $this->assertSame(substr($bytes, -50), $this->download($id, ['Range' => 'bytes=-50'])->assertStatus(206)->streamedContent());
        $this->assertSame(substr($bytes, strlen($bytes) - 7), $this->download($id, ['Range' => 'bytes='.(strlen($bytes) - 7).'-'])->assertStatus(206)->streamedContent());
        // Fin au-delà de la taille : tronquée.
        $this->assertSame(substr($bytes, -3), $this->download($id, ['Range' => 'bytes='.(strlen($bytes) - 3).'-99999999'])->assertStatus(206)->streamedContent());

        $this->download($id, ['Range' => 'bytes='.strlen($bytes).'-'])->assertStatus(416);
        $this->download($id, ['Range' => 'bytes=9-3'])->assertStatus(416);
        $this->download($id, ['Range' => 'items=0-1'])->assertStatus(416);
        $this->download($id, ['Range' => 'bytes=-'])->assertStatus(416);
    }

    public function test_a_chunk_of_the_wrong_length_is_refused(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $bytes = $this->png(2000);
        $id = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'a.png', 'size' => strlen($bytes)])->json('id');

        $this->sendChunk($id, 0, substr($bytes, 0, 1999))->assertStatus(422);
        $this->sendChunk($id, 0, $bytes.'x')->assertStatus(422);
        $this->sendChunk($id, 1, $bytes)->assertStatus(422);     // aucun morceau n°1
        $this->sendChunk($id, -1, $bytes)->assertNotFound();
        $this->assertSame([], DossierFile::find($id)->received);
    }

    public function test_incomplete_or_truncated_uploads_cannot_be_completed_or_downloaded(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $id = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'a.png', 'size' => 2000])->json('id');

        $this->postJson("/api/dossier-files/$id/complete")->assertStatus(422);
        $this->download($id)->assertNotFound();
        $this->getJson("/api/dossiers/{$d['id']}")->assertJsonCount(0, 'files');
        $this->getJson('/api/dossiers')->assertJson([['files_count' => 0]]);
    }

    public function test_a_chunk_cannot_be_sent_after_the_file_is_complete(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $bytes = $this->png();
        $f = $this->upload($d['id'], $bytes);
        $this->sendChunk($f['id'], 0, $bytes)->assertStatus(409);
        $this->postJson("/api/dossier-files/{$f['id']}/complete")->assertOk();     // idempotent
        $this->assertSame($bytes, $this->download($f['id'])->streamedContent());
    }

    #[DataProvider('forbiddenContents')]
    public function test_disallowed_content_is_rejected_whatever_its_name_or_declared_type(string $content, string $name, string $mime): void
    {
        $this->unlock();
        $d = $this->dossier();
        $id = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => $name, 'size' => strlen($content), 'mime' => $mime])->json('id');
        $this->sendChunk($id, 0, $content)->assertStatus(422);

        $this->assertDatabaseMissing('dossier_files', ['id' => $id]);
        $this->assertSame([], Storage::disk('vault')->allFiles());
    }

    public static function forbiddenContents(): array
    {
        return [
            'html déguisé en image' => ['<!DOCTYPE html><html><script>alert(1)</script></html>', 'photo.png', 'image/png'],
            'svg' => ['<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'dessin.svg', 'image/svg+xml'],
            'php' => ["<?php system(\$_GET['c']);", 'x.png', 'image/png'],
            'script shell' => ["#!/bin/sh\nrm -rf /\n", 'x.mp4', 'video/mp4'],
            'exécutable' => ["MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00".str_repeat("\0", 64), 'x.pdf', 'application/pdf'],
        ];
    }

    public function test_a_polyglot_declared_as_audio_is_not_trusted(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $html = '<html><body><script>1</script></body></html>';
        $id = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'a.webm', 'size' => strlen($html), 'mime' => 'audio/webm'])->json('id');
        $this->sendChunk($id, 0, $html)->assertStatus(422);
    }

    public function test_a_declared_type_cannot_override_the_real_one(): void
    {
        $this->unlock();
        $d = $this->dossier();
        // Un PNG annoncé comme PDF : on retient le type réel.
        $f = $this->upload($d['id'], $this->png(), 'rapport.pdf', 'application/pdf');
        $this->assertSame('image/png', $f['mime']);
        $this->assertSame('image', $f['kind']);
    }

    public function test_documents_are_always_downloaded_never_displayed(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $pdf = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        $f = $this->upload($d['id'], $pdf, 'dossier.pdf', 'application/pdf');
        $this->assertSame('document', $f['kind']);

        $res = $this->download($f['id'])->assertOk();
        $this->assertSame('application/octet-stream', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment;', $res->headers->get('Content-Disposition'));
        $this->assertSame($pdf, $res->streamedContent());
    }

    public function test_voice_recordings_in_a_video_container_are_kept_as_audio(): void
    {
        $this->unlock();
        $d = $this->dossier();
        // Un fichier Ogg/Opus minimal est reconnu comme audio ou application/ogg ; WebM comme video/webm.
        $webm = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02".str_repeat("\0", 200);
        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->buffer($webm);
        if (! in_array($sniffed, config('vault.audio_containers'), true)) {
            $this->markTestSkipped("finfo détecte « $sniffed » pour l'échantillon WebM.");
        }
        $f = $this->upload($d['id'], $webm, 'memo.webm', 'audio/webm');
        $this->assertSame('audio', $f['kind']);
        $this->assertSame('audio/webm', $f['mime']);
        $this->assertSame('audio/webm', $this->download($f['id'])->headers->get('Content-Type'));

        // Sans déclaration audio, le même contenu reste une vidéo.
        $g = $this->upload($d['id'], $webm, 'clip.webm', 'video/webm');
        $this->assertSame('video', $g['kind']);
    }

    public function test_size_and_quota_limits(): void
    {
        $this->unlock();
        $d = $this->dossier();

        config(['vault.max_file_mb' => 1]);
        $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'a.png', 'size' => 1_048_577])->assertStatus(422)->assertJsonValidationErrors('size');
        $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'a.png', 'size' => 0])->assertStatus(422);
        $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => '', 'size' => 5])->assertStatus(422);

        config(['vault.max_file_mb' => 500, 'vault.quota_mb' => 1]);
        $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'a.png', 'size' => 600_000])->assertCreated();
        $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'b.png', 'size' => 600_000])->assertStatus(422);
        $this->assertGreaterThan(0, $this->getJson('/api/vault')->json('used_bytes'));
    }

    public function test_file_names_are_sanitised(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $f = $this->upload($d['id'], $this->png(), "../../etc/pass\\wd\r\n.png");
        $this->assertStringNotContainsString('/', $f['name']);
        $this->assertStringNotContainsString('\\', $f['name']);
        $this->assertStringNotContainsString("\n", $f['name']);

        $res = $this->download($f['id'])->assertOk();
        $this->assertStringNotContainsString("\n", $res->headers->get('Content-Disposition'));
        $this->putJson("/api/dossier-files/{$f['id']}", ['name' => '   '])->assertStatus(422);
    }

    public function test_captions_are_encrypted_at_rest(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $f = $this->upload($d['id'], $this->png());

        $this->putJson("/api/dossier-files/{$f['id']}", ['caption' => 'Pris devant le garage'])->assertOk()->assertJson(['caption' => 'Pris devant le garage']);
        $raw = \DB::table('dossier_files')->where('id', $f['id'])->value('caption');
        $this->assertStringNotContainsString('garage', $raw);
        $this->getJson("/api/dossiers/{$d['id']}")->assertJsonPath('files.0.caption', 'Pris devant le garage');
    }

    /* ---------- Suppression ---------- */

    public function test_deleting_a_file_erases_it_from_disk(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $f = $this->upload($d['id'], $this->png());
        $this->assertCount(1, Storage::disk('vault')->allFiles());

        $this->deleteJson("/api/dossier-files/{$f['id']}")->assertOk();
        $this->assertSame([], Storage::disk('vault')->allFiles());
        $this->assertDatabaseCount('dossier_files', 0);
        $this->download($f['id'])->assertNotFound();
    }

    public function test_deleting_a_dossier_erases_all_its_files_even_unfinished_ones(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $this->upload($d['id'], $this->png());
        $this->upload($d['id'], $this->png(3000));
        $partial = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'c.png', 'size' => 5000])->json('id');
        $this->sendChunk($partial, 0, $this->png(5000))->assertOk();
        $this->assertCount(3, Storage::disk('vault')->allFiles());

        $this->deleteJson("/api/dossiers/{$d['id']}")->assertOk();
        $this->assertSame([], Storage::disk('vault')->allFiles());
        $this->assertDatabaseCount('dossier_files', 0);
        $this->assertDatabaseCount('dossiers', 0);
    }

    public function test_deleting_a_contact_keeps_the_dossier_detached(): void
    {
        $this->unlock();
        $contact = $this->postJson('/api/contacts', ['name' => 'Paul'])->json('id');
        $d = $this->dossier(['contact_id' => $contact]);

        $this->deleteJson("/api/contacts/$contact")->assertOk();
        $this->getJson("/api/dossiers/{$d['id']}")->assertOk()->assertJson(['contact_id' => null, 'subject' => 'Paul']);
    }

    public function test_the_clean_command_removes_abandoned_uploads_and_orphans_only(): void
    {
        $this->unlock();
        $d = $this->dossier();
        $keep = $this->upload($d['id'], $this->png());

        $fresh = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'a.png', 'size' => 5000])->json('id');
        $this->sendChunk($fresh, 0, $this->png(5000))->assertOk();
        $old = $this->postJson("/api/dossiers/{$d['id']}/files", ['name' => 'b.png', 'size' => 5000])->json('id');
        $this->sendChunk($old, 0, $this->png(5000))->assertOk();
        DossierFile::whereKey($old)->update(['updated_at' => now()->subDays(2)]);

        $disk = Storage::disk('vault');
        $disk->put('1/orphelin-ancien.bin', 'x');
        $disk->put('1/orphelin-recent.bin', 'y');
        touch($disk->path('1/orphelin-ancien.bin'), now()->subHours(3)->timestamp);

        $this->artisan('reseau:vault-clean')->assertSuccessful();

        $this->assertDatabaseMissing('dossier_files', ['id' => $old]);
        $this->assertDatabaseHas('dossier_files', ['id' => $fresh]);
        $this->assertDatabaseHas('dossier_files', ['id' => $keep['id']]);
        $disk->assertMissing('1/orphelin-ancien.bin');
        $disk->assertExists('1/orphelin-recent.bin');
        $this->assertCount(3, $disk->allFiles());   // fichier prêt + envoi récent + orphelin récent
    }
}
