<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifeGoalsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /* ---------- Catalogue ---------- */

    public function test_the_list_starts_empty(): void
    {
        $this->getJson('/api/life-goals')->assertOk()->assertJson([]);
    }

    public function test_seeding_adds_the_whole_catalog_once(): void
    {
        $catalog = config('life_goals.catalog');

        $res = $this->postJson('/api/life-goals/seed')->assertOk();
        $res->assertJsonCount(count($catalog));
        $this->assertSame('none', $res->json('0.status'));
        $this->assertArrayNotHasKey('user_id', $res->json('0'));

        // Relancer le chargement ne duplique rien.
        $this->postJson('/api/life-goals/seed')->assertOk()->assertJsonCount(count($catalog));
    }

    public function test_removing_a_seeded_item_is_not_undone_by_loading_the_list(): void
    {
        $this->postJson('/api/life-goals/seed')->assertOk();
        $goal = $this->getJson('/api/life-goals')->json('0');

        $this->deleteJson("/api/life-goals/{$goal['id']}")->assertOk();
        $this->getJson('/api/life-goals')->assertJsonCount(count(config('life_goals.catalog')) - 1);

        // Mais recharger explicitement le catalogue la ramène (comportement assumé, pas automatique).
        $this->postJson('/api/life-goals/seed')->assertOk()->assertJsonCount(count(config('life_goals.catalog')));
    }

    /* ---------- Entrées personnalisées ---------- */

    public function test_a_custom_goal_is_created_updated_and_deleted(): void
    {
        $g = $this->postJson('/api/life-goals', ['title' => 'Apprendre la poterie', 'domain' => 'loisirs', 'age_bracket' => 'un_jour'])
            ->assertCreated()->json();
        $this->assertNull($g['source_key']);

        $this->putJson("/api/life-goals/{$g['id']}", ['status' => 'planned', 'target_date' => '2027-06-01'])
            ->assertOk()->assertJson(['status' => 'planned', 'target_date' => '2027-06-01']);

        $this->deleteJson("/api/life-goals/{$g['id']}")->assertOk();
        $this->getJson('/api/life-goals')->assertJson([]);
    }

    public function test_validation_rejects_an_unknown_domain_or_bracket(): void
    {
        $this->postJson('/api/life-goals', ['title' => 'X', 'domain' => 'inconnu', 'age_bracket' => 'un_jour'])->assertStatus(422);
        $this->postJson('/api/life-goals', ['title' => 'X', 'domain' => 'loisirs', 'age_bracket' => 'avant_99'])->assertStatus(422);
    }

    public function test_marking_done_sets_the_date_and_unmarking_clears_it(): void
    {
        $g = $this->postJson('/api/life-goals', ['title' => 'X', 'domain' => 'sante', 'age_bracket' => 'un_jour'])->assertCreated()->json();

        $done = $this->putJson("/api/life-goals/{$g['id']}", ['status' => 'done'])->assertOk()->json();
        $this->assertNotNull($done['completed_at']);

        $back = $this->putJson("/api/life-goals/{$g['id']}", ['status' => 'planned'])->assertOk()->json();
        $this->assertNull($back['completed_at']);
    }

    public function test_a_goal_can_be_linked_to_ones_own_project_and_shows_its_progress(): void
    {
        $project = $this->user->projects()->create(['title' => 'Ouvrir un commerce', 'status' => 'in_progress']);
        $project->steps()->create(['title' => 'Trouver un local', 'done' => true, 'position' => 0]);
        $project->steps()->create(['title' => 'Acheter le matériel', 'done' => false, 'position' => 1]);

        $g = $this->postJson('/api/life-goals', ['title' => 'Ouvrir un commerce', 'domain' => 'carriere', 'age_bracket' => 'avant_30', 'project_id' => $project->id])
            ->assertCreated()->json();
        $this->assertSame($project->id, $g['project_id']);
        $this->assertCount(2, $g['project']['steps']);
    }

    public function test_a_goal_cannot_be_linked_to_someone_elses_project(): void
    {
        $other = User::factory()->create();
        $theirProject = $other->projects()->create(['title' => 'Secret', 'status' => 'idea']);

        $this->postJson('/api/life-goals', ['title' => 'X', 'domain' => 'carriere', 'age_bracket' => 'un_jour', 'project_id' => $theirProject->id])
            ->assertStatus(422);
    }

    public function test_goals_are_isolated_between_users(): void
    {
        $mine = $this->postJson('/api/life-goals', ['title' => 'À moi', 'domain' => 'loisirs', 'age_bracket' => 'un_jour'])->assertCreated()->json();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->getJson('/api/life-goals')->assertJson([]);
        $this->putJson("/api/life-goals/{$mine['id']}", ['status' => 'done'])->assertStatus(404);
    }

    /* ---------- Partage ---------- */

    public function test_a_share_link_is_created_and_listed(): void
    {
        $res = $this->postJson('/api/life-goal-shares', ['label' => 'Envoyé à Paul'])->assertCreated();
        $token = $res->json('token');
        $this->assertNotEmpty($token);

        $list = $this->getJson('/api/life-goal-shares')->assertOk()->json();
        $this->assertCount(1, $list);
        $this->assertSame('Envoyé à Paul', $list[0]['label']);
        $this->assertArrayNotHasKey('token_hash', $list[0]);
    }

    public function test_the_public_page_shows_the_owners_goals_without_authentication(): void
    {
        $this->postJson('/api/life-goals', ['title' => 'Voir le monde', 'domain' => 'voyage', 'age_bracket' => 'un_jour', 'status' => 'planned']);
        $token = $this->postJson('/api/life-goal-shares')->json('token');

        $this->postJson('/api/logout');
        $page = $this->getJson("/api/partage-vie/$token")->assertOk();
        $this->assertSame($this->user->name, $page->json('from'));
        $this->assertCount(1, $page->json('goals'));
        $this->assertSame('planned', $page->json('goals.0.status'));
        $this->assertSame([], $page->json('answers'));
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $this->postJson('/api/logout');
        $this->getJson('/api/partage-vie/jeton-invente')->assertStatus(404);
    }

    public function test_a_visitor_can_answer_and_set_their_name_without_an_account(): void
    {
        $goal = $this->postJson('/api/life-goals', ['title' => 'Apprendre à nager', 'domain' => 'loisirs', 'age_bracket' => 'avant_12'])->assertCreated()->json();
        $token = $this->postJson('/api/life-goal-shares')->json('token');
        $this->postJson('/api/logout');

        $this->putJson("/api/partage-vie/$token/nom", ['respondent_name' => 'Marie'])->assertOk();
        $this->putJson("/api/partage-vie/$token/accomplissements/{$goal['id']}", ['status' => 'done'])
            ->assertOk()->assertJson(['status' => 'done']);

        $page = $this->getJson("/api/partage-vie/$token")->assertOk();
        $this->assertSame('Marie', $page->json('respondent_name'));
        $this->assertCount(1, $page->json('answers'));
        $this->assertSame('done', $page->json('answers.0.status'));

        // Revenir et changer d'avis met à jour la même réponse plutôt que d'en créer une deuxième.
        $this->putJson("/api/partage-vie/$token/accomplissements/{$goal['id']}", ['status' => 'planned'])->assertOk();
        $this->assertCount(1, $this->getJson("/api/partage-vie/$token")->json('answers'));
    }

    public function test_the_owner_can_read_the_respondents_answers(): void
    {
        $goal = $this->postJson('/api/life-goals', ['title' => 'Nager', 'domain' => 'loisirs', 'age_bracket' => 'avant_12'])->assertCreated()->json();
        $created = $this->postJson('/api/life-goal-shares', ['label' => 'Paul'])->json();
        $token = $created['token'];
        $shareId = $created['id'];

        $this->postJson('/api/logout');
        $this->putJson("/api/partage-vie/$token/accomplissements/{$goal['id']}", ['status' => 'done'])->assertOk();
        $this->putJson("/api/partage-vie/$token/nom", ['respondent_name' => 'Paul'])->assertOk();

        $this->actingAs($this->user);
        $view = $this->getJson("/api/life-goal-shares/$shareId")->assertOk();
        $this->assertSame('Paul', $view->json('respondent_name'));
        $this->assertCount(1, $view->json('answers'));
        $this->assertSame($goal['title'], $view->json('answers.0.life_goal.title'));
    }

    public function test_a_visitor_cannot_answer_for_a_goal_that_belongs_to_someone_else(): void
    {
        $other = User::factory()->create();
        $theirGoal = $other->lifeGoals()->create(['title' => 'X', 'domain' => 'loisirs', 'age_bracket' => 'un_jour']);
        $token = $this->postJson('/api/life-goal-shares')->json('token');
        $this->postJson('/api/logout');

        $this->putJson("/api/partage-vie/$token/accomplissements/{$theirGoal->id}", ['status' => 'done'])->assertStatus(404);
    }

    public function test_revoking_a_share_makes_its_link_unusable(): void
    {
        $created = $this->postJson('/api/life-goal-shares')->json();
        $this->deleteJson("/api/life-goal-shares/{$created['id']}")->assertOk();

        $this->postJson('/api/logout');
        $this->getJson("/api/partage-vie/{$created['token']}")->assertStatus(404);
    }

    public function test_shares_are_isolated_between_users(): void
    {
        $created = $this->postJson('/api/life-goal-shares')->json();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->getJson('/api/life-goal-shares')->assertJson([]);
        $this->getJson("/api/life-goal-shares/{$created['id']}")->assertStatus(404);
        $this->deleteJson("/api/life-goal-shares/{$created['id']}")->assertStatus(404);
    }
}
