<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_the_cv_starts_empty(): void
    {
        $this->getJson('/api/cv')->assertOk()->assertJson(['skills' => [], 'languages' => [], 'experiences' => [], 'achievements' => []]);
    }

    /* ---------- Compétences / connaissances / talents ---------- */

    public function test_a_skill_is_created_and_shown_in_the_cv(): void
    {
        $s = $this->postJson('/api/skills', ['type' => 'competence', 'name' => 'Plomberie', 'level' => 4, 'notes' => 'Autodidacte'])
            ->assertCreated()->json();

        $this->assertSame('competence', $s['type']);
        $this->assertArrayNotHasKey('user_id', $s);
        $this->getJson('/api/cv')->assertJsonFragment(['name' => 'Plomberie']);
    }

    public function test_skill_validation(): void
    {
        $this->postJson('/api/skills', ['type' => 'inconnu', 'name' => 'X'])->assertStatus(422);
        $this->postJson('/api/skills', ['type' => 'talent', 'name' => '', 'level' => 9])->assertStatus(422);
    }

    public function test_a_skill_is_updated_and_deleted(): void
    {
        $s = $this->postJson('/api/skills', ['type' => 'talent', 'name' => 'Chant'])->assertCreated()->json();

        $this->putJson("/api/skills/{$s['id']}", ['type' => 'talent', 'name' => 'Chant lyrique'])->assertOk()->assertJsonFragment(['name' => 'Chant lyrique']);
        $this->deleteJson("/api/skills/{$s['id']}")->assertOk();
        $this->getJson('/api/cv')->assertJson(['skills' => []]);
    }

    /* ---------- Langues ---------- */

    public function test_a_language_is_created_updated_and_deleted(): void
    {
        $l = $this->postJson('/api/languages', ['name' => 'Anglais', 'level' => 'courant'])->assertCreated()->json();

        $this->putJson("/api/languages/{$l['id']}", ['name' => 'Anglais', 'level' => 'natif'])->assertOk()->assertJsonFragment(['level' => 'natif']);
        $this->deleteJson("/api/languages/{$l['id']}")->assertOk();
        $this->getJson('/api/cv')->assertJson(['languages' => []]);
    }

    public function test_language_level_is_restricted_to_known_values(): void
    {
        $this->postJson('/api/languages', ['name' => 'Swahili', 'level' => 'excellent'])->assertStatus(422);
    }

    /* ---------- Expériences et réalisations ---------- */

    public function test_an_experience_is_created_with_its_achievements(): void
    {
        $e = $this->postJson('/api/experiences', [
            'title' => 'Chauffeur', 'organization' => 'Akabservice', 'start_date' => '2022-01-01', 'current' => true,
        ])->assertCreated()->json();

        $this->assertTrue($e['current']);
        $this->assertNull($e['end_date']);
        $this->assertSame([], $e['achievements']);

        $a = $this->postJson('/api/achievements', ['experience_id' => $e['id'], 'title' => 'Employé du mois', 'date' => '2022-06-01'])->assertCreated()->json();
        $this->assertSame($e['id'], $a['experience_id']);

        $fetched = $this->getJson('/api/cv')->json('experiences.0');
        $this->assertCount(1, $fetched['achievements']);
    }

    public function test_marking_an_experience_current_clears_its_end_date(): void
    {
        $e = $this->postJson('/api/experiences', ['title' => 'Vendeur', 'start_date' => '2020-01-01', 'end_date' => '2021-01-01'])->assertCreated()->json();

        $updated = $this->putJson("/api/experiences/{$e['id']}", ['title' => 'Vendeur', 'start_date' => '2020-01-01', 'current' => true])->assertOk()->json();
        $this->assertNull($updated['end_date']);
    }

    public function test_experience_dates_are_validated(): void
    {
        $this->postJson('/api/experiences', ['title' => 'X', 'start_date' => '2022-01-01', 'end_date' => '2020-01-01'])->assertStatus(422);
    }

    public function test_an_achievement_without_experience_is_a_standalone_realisation(): void
    {
        $this->postJson('/api/achievements', ['title' => 'Certification ISO', 'date' => '2023-01-01'])->assertCreated();

        $this->getJson('/api/cv')->assertJsonFragment(['title' => 'Certification ISO']);
    }

    public function test_an_achievement_cannot_be_linked_to_someone_elses_experience(): void
    {
        $other = User::factory()->create();
        $theirExperience = $other->experiences()->create(['title' => 'Secrète']);

        $this->postJson('/api/achievements', ['experience_id' => $theirExperience->id, 'title' => 'X'])->assertStatus(422);
    }

    /* ---------- Isolation entre utilisateurs ---------- */

    public function test_cv_data_is_isolated_between_users(): void
    {
        $this->postJson('/api/skills', ['type' => 'competence', 'name' => 'À moi']);
        $this->postJson('/api/languages', ['name' => 'Français', 'level' => 'natif']);
        $theirExperience = $this->postJson('/api/experiences', ['title' => 'À moi aussi'])->json();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->getJson('/api/cv')->assertJson(['skills' => [], 'languages' => [], 'experiences' => []]);
        $this->putJson("/api/experiences/{$theirExperience['id']}", ['title' => 'Volé'])->assertStatus(404);
    }

    /* ---------- Export PDF ---------- */

    public function test_the_cv_exports_as_a_downloadable_pdf(): void
    {
        $this->postJson('/api/skills', ['type' => 'competence', 'name' => 'Menuiserie']);
        $this->postJson('/api/languages', ['name' => 'Lingala', 'level' => 'natif']);
        $this->postJson('/api/experiences', ['title' => 'Artisan', 'start_date' => '2019-01-01', 'current' => true]);

        $res = $this->get('/api/cv/export')->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $res->getContent());
    }
}
