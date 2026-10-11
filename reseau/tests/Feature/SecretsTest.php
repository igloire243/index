<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecretsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function unlock(): void
    {
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
    }

    private function make(array $o = []): array
    {
        return $this->postJson('/api/secrets', $o + ['type' => 'site', 'title' => 'Facebook', 'username' => 'moi@example.com', 'secret' => 'Tr0ub4dor&3', 'url' => 'https://facebook.com'])->assertCreated()->json();
    }

    public function test_the_vault_must_be_unlocked(): void
    {
        $this->getJson('/api/secrets')->assertStatus(403)->assertJson(['code' => 'vault_locked']);
        $this->postJson('/api/secrets', ['type' => 'pin', 'title' => 'X'])->assertStatus(403);
        $this->getJson('/api/secrets/1')->assertStatus(403);
    }

    public function test_a_secret_is_stored_encrypted_and_the_list_never_exposes_the_secret(): void
    {
        $this->unlock();
        $s = $this->make(['notes' => 'question : premier animal', 'fields' => [['label' => 'Code de secours', 'value' => '9988-7766', 'hidden' => true]]]);

        $this->assertSame('Tr0ub4dor&3', $s['secret']);
        $this->assertSame('9988-7766', $s['fields'][0]['value']);
        $this->assertArrayNotHasKey('user_id', $s);

        $raw = DB::table('secrets')->where('id', $s['id'])->value('payload');
        foreach (['Facebook', 'Tr0ub4dor', 'moi@example.com', '9988', 'animal', 'facebook.com'] as $clear) {
            $this->assertStringNotContainsString($clear, $raw);
        }

        $list = $this->getJson('/api/secrets')->assertOk()->assertJsonCount(1);
        $this->assertSame('Facebook', $list->json('0.title'));
        $this->assertSame('moi@example.com', $list->json('0.username'));
        $this->assertStringNotContainsString('Tr0ub4dor', $list->getContent());
        $this->assertStringNotContainsString('9988', $list->getContent());
        $this->assertStringNotContainsString('animal', $list->getContent());
        $this->assertStringContainsString('no-store', $list->headers->get('Cache-Control'));

        $this->getJson("/api/secrets/{$s['id']}")->assertOk()->assertJsonPath('secret', 'Tr0ub4dor&3')->assertJsonPath('notes', 'question : premier animal');
    }

    public function test_all_kinds_are_accepted_and_list_is_sorted_by_title(): void
    {
        $this->unlock();
        $this->make(['type' => 'pin', 'title' => 'Code carte Rawbank', 'secret' => '4821']);
        $this->make(['type' => 'pattern', 'title' => 'Schéma téléphone', 'secret' => '1-5-9-6-3']);
        $this->make(['type' => 'wifi', 'title' => 'Wifi maison', 'secret' => 'motdepasse']);
        $this->make(['type' => 'bank', 'title' => 'Banque en ligne']);

        $titles = array_column($this->getJson('/api/secrets')->json(), 'title');
        $this->assertSame(['Banque en ligne', 'Code carte Rawbank', 'Schéma téléphone', 'Wifi maison'], $titles);
    }

    public function test_update_and_favorite(): void
    {
        $this->unlock();
        $s = $this->make();
        $r = $this->putJson("/api/secrets/{$s['id']}", ['type' => 'site', 'title' => 'Facebook (pro)', 'secret' => 'nouveau', 'favorite' => true])->assertOk();
        $this->assertSame('nouveau', $r->json('secret'));
        $this->assertTrue($r->json('favorite'));
        $this->assertNull($r->json('username'));

        // Sans « favorite » dans la requête, l'état est conservé.
        $this->putJson("/api/secrets/{$s['id']}", ['type' => 'site', 'title' => 'Facebook (pro)', 'secret' => 'nouveau'])->assertOk()->assertJsonPath('favorite', true);
    }

    public function test_validation(): void
    {
        $this->unlock();
        $this->postJson('/api/secrets', ['type' => 'site', 'title' => ''])->assertStatus(422)->assertJsonValidationErrors('title');
        $this->postJson('/api/secrets', ['type' => 'inconnu', 'title' => 'X'])->assertStatus(422)->assertJsonValidationErrors('type');
        $this->postJson('/api/secrets', ['type' => 'site', 'title' => 'X', 'fields' => [['value' => 'sans libellé']]])->assertStatus(422);
        $tooMany = array_fill(0, 21, ['label' => 'a', 'value' => 'b']);
        $this->postJson('/api/secrets', ['type' => 'site', 'title' => 'X', 'fields' => $tooMany])->assertStatus(422);
    }

    public function test_delete_and_isolation_between_users(): void
    {
        $this->unlock();
        $mine = $this->make();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->unlock();
        $this->getJson('/api/secrets')->assertJsonCount(0);
        $this->getJson("/api/secrets/{$mine['id']}")->assertNotFound();
        $this->putJson("/api/secrets/{$mine['id']}", ['type' => 'site', 'title' => 'Piraté'])->assertNotFound();
        $this->deleteJson("/api/secrets/{$mine['id']}")->assertNotFound();

        $this->actingAs($this->user);
        $this->unlock();
        $this->deleteJson("/api/secrets/{$mine['id']}")->assertOk();
        $this->assertDatabaseCount('secrets', 0);
    }

    public function test_locking_the_vault_hides_secrets_again(): void
    {
        $this->unlock();
        $s = $this->make();
        $this->postJson('/api/vault/lock')->assertOk();
        $this->getJson("/api/secrets/{$s['id']}")->assertStatus(403);
    }
}
