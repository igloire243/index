<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\VaultCrypto;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentMergeTest extends TestCase
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

    private function dossier(): int
    {
        return $this->postJson('/api/dossiers', ['title' => 'Mes documents'])->assertCreated()->json('id');
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    private function pdfBytes(string $text = 'Un document'): string
    {
        return Pdf::loadHTML('<p>'.$text.'</p>')->output();
    }

    private function upload(int $dossierId, string $bytes, string $name, string $mime): int
    {
        $id = $this->postJson("/api/dossiers/$dossierId/files", ['name' => $name, 'size' => strlen($bytes), 'mime' => $mime])->assertCreated()->json('id');
        foreach (str_split($bytes, VaultCrypto::CHUNK) as $i => $piece) {
            $this->call('PUT', "/api/dossier-files/$id/chunks/$i", [], [], [], ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $piece)->assertOk();
        }
        $this->postJson("/api/dossier-files/$id/complete")->assertOk();

        return $id;
    }

    public function test_merges_an_image_and_a_pdf_into_one_downloadable_pdf(): void
    {
        $d = $this->dossier();
        $image = $this->upload($d, $this->png(), 'carte.png', 'image/png');
        $pdf = $this->upload($d, $this->pdfBytes('Second document'), 'releve.pdf', 'application/pdf');

        $res = $this->postJson('/api/documents/fusionner', ['file_ids' => [$image, $pdf]])->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');

        $content = $res->getContent();
        $this->assertStringStartsWith('%PDF', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_refuses_an_unsupported_file_type(): void
    {
        $d = $this->dossier();
        $doc = $this->upload($d, str_repeat('x', 200), 'notes.txt', 'text/plain');

        $this->postJson('/api/documents/fusionner', ['file_ids' => [$doc]])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => '« notes.txt » ne peut pas être fusionné : seuls les PDF et les images JPEG/PNG le peuvent.']);
    }

    public function test_refuses_a_file_that_does_not_belong_to_the_user(): void
    {
        $d = $this->dossier();
        $mine = $this->upload($d, $this->png(), 'carte.png', 'image/png');

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();

        $this->postJson('/api/documents/fusionner', ['file_ids' => [$mine]])->assertStatus(404);
    }

    public function test_requires_the_vault_to_be_unlocked(): void
    {
        $d = $this->dossier();
        $image = $this->upload($d, $this->png(), 'carte.png', 'image/png');
        $this->postJson('/api/vault/lock')->assertOk();

        $this->postJson('/api/documents/fusionner', ['file_ids' => [$image]])->assertStatus(403);
    }

    public function test_at_least_one_file_is_required(): void
    {
        $this->postJson('/api/documents/fusionner', ['file_ids' => []])->assertStatus(422);
    }
}
