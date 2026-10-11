<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_create_list_update_and_delete(): void
    {
        $id = $this->postJson('/api/contacts', [
            'name' => 'Aline Mbuyi', 'phone' => '+243 99 000 0000', 'domains' => ['santé', 'Santé', ' transport ', ''],
            'role' => 'Médecin', 'lat' => -4.33, 'lng' => 15.3, 'favor' => 'owe', 'last_contact' => '2026-09-01',
        ])->assertCreated()->assertJsonPath('domains', ['santé', 'transport'])->assertJsonMissingPath('user_id')->json('id');

        $this->getJson('/api/contacts')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Aline Mbuyi');

        $this->putJson("/api/contacts/$id", ['name' => 'Aline M.', 'domains' => ['santé'], 'lat' => null, 'lng' => null])
            ->assertOk()->assertJsonPath('name', 'Aline M.')->assertJsonPath('lat', null);

        $this->deleteJson("/api/contacts/$id")->assertOk();
        $this->getJson('/api/contacts')->assertJsonCount(0);
    }

    public function test_validation(): void
    {
        $this->postJson('/api/contacts', ['name' => ''])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/contacts', ['name' => 'X', 'lat' => 120, 'lng' => 0])->assertStatus(422)->assertJsonValidationErrors('lat');
        $this->postJson('/api/contacts', ['name' => 'X', 'favor' => 'autre'])->assertStatus(422)->assertJsonValidationErrors('favor');
        $this->postJson('/api/contacts', ['name' => 'X', 'last_contact' => '01/09/2026'])->assertStatus(422)->assertJsonValidationErrors('last_contact');
    }

    public function test_a_single_coordinate_is_dropped(): void
    {
        $this->postJson('/api/contacts', ['name' => 'X', 'lat' => 10])->assertCreated()->assertJsonPath('lat', null)->assertJsonPath('lng', null);
    }

    public function test_users_cannot_see_or_touch_each_others_contacts(): void
    {
        $other = User::factory()->create();
        $theirs = $other->contacts()->create(['name' => 'Secret', 'domains' => []]);

        $this->getJson('/api/contacts')->assertJsonCount(0);
        $this->putJson("/api/contacts/{$theirs->id}", ['name' => 'Piraté'])->assertNotFound();
        $this->deleteJson("/api/contacts/{$theirs->id}")->assertNotFound();
        $this->assertDatabaseHas('contacts', ['id' => $theirs->id, 'name' => 'Secret']);
    }

    public function test_mass_assignment_cannot_change_the_owner(): void
    {
        $other = User::factory()->create();

        $id = $this->postJson('/api/contacts', ['name' => 'X', 'user_id' => $other->id])->assertCreated()->json('id');

        $this->assertDatabaseHas('contacts', ['id' => $id, 'user_id' => $this->user->id]);
    }

    public function test_import_skips_duplicates_and_invalid_rows_and_accepts_legacy_keys(): void
    {
        $this->user->contacts()->create(['name' => 'Paul Test', 'phone' => '+33 6 11 22 33 44', 'domains' => []]);

        $this->postJson('/api/contacts/import', ['contacts' => [
            ['name' => 'PAUL TEST', 'phone' => '+33611223344'],                            // doublon : même nom, mêmes chiffres
            ['name' => 'Jeanne', 'domains' => 'droit, finance', 'last' => '2026-01-02'],   // anciens noms de champs
            ['phone' => '123'],                                                            // sans nom : ignoré
            'pas un tableau',
        ]])->assertOk()->assertJsonPath('added', 1)->assertJsonPath('skipped', 3);

        $jeanne = Contact::where('name', 'Jeanne')->firstOrFail();
        $this->assertSame(['droit', 'finance'], $jeanne->domains);
        $this->assertSame('2026-01-02', $jeanne->last_contact->toDateString());
        $this->assertSame($this->user->id, $jeanne->user_id);

        // Ré-importer la même personne n'ajoute plus rien ; un homonyme avec un autre numéro est ajouté.
        $this->postJson('/api/contacts/import', ['contacts' => [['name' => 'Jeanne']]])->assertOk()->assertJsonPath('added', 0);
        $this->postJson('/api/contacts/import', ['contacts' => [['name' => 'Jeanne', 'phone' => '+243 81 000 0000']]])->assertOk()->assertJsonPath('added', 1);
    }

    public function test_delete_all_only_affects_the_current_user(): void
    {
        $other = User::factory()->create();
        $other->contacts()->create(['name' => 'Autre', 'domains' => []]);
        $this->user->contacts()->create(['name' => 'Moi', 'domains' => []]);

        $this->deleteJson('/api/contacts')->assertOk()->assertJsonPath('deleted', 1);

        $this->assertDatabaseHas('contacts', ['name' => 'Autre']);
        $this->assertDatabaseMissing('contacts', ['name' => 'Moi']);
    }
}
