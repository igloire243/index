<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Carbon::setTestNow('2026-10-12 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function project(array $o = []): array
    {
        return $this->postJson('/api/projects', $o + ['title' => 'Ouvrir un salon de coiffure'])->assertCreated()->json();
    }

    public function test_requires_authentication(): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/projects')->assertUnauthorized();
    }

    public function test_a_project_is_created_with_sensible_defaults(): void
    {
        $p = $this->project();
        $this->assertSame('idea', $p['status']);
        $this->assertNull($p['completed_at']);
        $this->assertSame([], $p['steps']);
        $this->assertArrayNotHasKey('user_id', $p);
    }

    public function test_title_is_required(): void
    {
        $this->postJson('/api/projects', ['title' => ''])->assertStatus(422)->assertJsonValidationErrors('title');
    }

    public function test_status_is_validated(): void
    {
        $this->postJson('/api/projects', ['title' => 'X', 'status' => 'nimportequoi'])->assertStatus(422);
        $this->postJson('/api/projects', ['title' => 'X', 'status' => 'in_progress'])->assertCreated();
    }

    public function test_a_project_can_be_linked_to_a_contact_the_user_owns(): void
    {
        $contact = $this->postJson('/api/contacts', ['name' => 'Paul Mbala'])->json('id');
        $p = $this->project(['contact_id' => $contact]);
        $this->assertSame($contact, $p['contact_id']);

        $other = User::factory()->create();
        $theirContact = $this->actingAs($other)->postJson('/api/contacts', ['name' => 'Autre'])->json('id');
        $this->actingAs($this->user);
        $this->postJson('/api/projects', ['title' => 'X', 'contact_id' => $theirContact])->assertStatus(422);
    }

    public function test_marking_a_project_done_sets_completed_at_and_unmarking_clears_it(): void
    {
        $p = $this->project();
        $done = $this->putJson("/api/projects/{$p['id']}", ['status' => 'done'])->assertOk()->json();
        $this->assertSame('2026-10-12T08:00:00.000000Z', $done['completed_at']);

        $reopened = $this->putJson("/api/projects/{$p['id']}", ['status' => 'in_progress'])->assertOk()->json();
        $this->assertNull($reopened['completed_at']);
    }

    public function test_steps_are_created_in_order_and_toggled(): void
    {
        $p = $this->project();
        $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'Trouver un local'])->assertCreated();
        $r = $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'Acheter le matériel'])->assertCreated()->json();
        $this->assertCount(2, $r['steps']);
        $this->assertSame(['Trouver un local', 'Acheter le matériel'], array_column($r['steps'], 'title'));

        $stepId = $r['steps'][0]['id'];
        $toggled = $this->putJson("/api/project-steps/$stepId", ['done' => true])->assertOk()->json();
        $this->assertTrue($toggled['steps'][0]['done']);
        $this->assertNotNull($toggled['steps'][0]['done_at']);

        $this->putJson("/api/project-steps/$stepId", ['done' => false])->assertOk()->assertJsonPath('steps.0.done_at', null);
    }

    public function test_step_title_requires_content(): void
    {
        $p = $this->project();
        $this->postJson("/api/projects/{$p['id']}/steps", ['title' => ''])->assertStatus(422);
    }

    public function test_steps_can_be_reordered(): void
    {
        $p = $this->project();
        $a = $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'A'])->json('steps.0.id');
        $r = $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'B'])->json();
        $b = $r['steps'][1]['id'];

        $reordered = $this->postJson("/api/projects/{$p['id']}/steps/reorder", ['order' => [$b, $a]])->assertOk()->json();
        $this->assertSame(['B', 'A'], array_column($reordered['steps'], 'title'));

        $this->postJson("/api/projects/{$p['id']}/steps/reorder", ['order' => [$a]])->assertStatus(422, 'liste incomplète refusée');
        $this->postJson("/api/projects/{$p['id']}/steps/reorder", ['order' => [$a, $b, 999]])->assertStatus(422);
    }

    public function test_deleting_a_step_renumbers_nothing_but_returns_the_rest(): void
    {
        $p = $this->project();
        $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'A']);
        $r = $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'B'])->json();
        $idA = $r['steps'][0]['id'];

        $after = $this->deleteJson("/api/project-steps/$idA")->assertOk()->json();
        $this->assertCount(1, $after['steps']);
        $this->assertSame('B', $after['steps'][0]['title']);
    }

    public function test_index_can_filter_by_status_and_sorts_active_projects_first(): void
    {
        $a = $this->project(['title' => 'Idée']);
        $b = $this->project(['title' => 'En cours']);
        $this->putJson("/api/projects/{$b['id']}", ['status' => 'in_progress']);
        $c = $this->project(['title' => 'Fini']);
        $this->putJson("/api/projects/{$c['id']}", ['status' => 'done']);

        $all = $this->getJson('/api/projects')->assertOk()->json();
        $this->assertSame('Fini', $all[count($all) - 1]['title'], 'les projets terminés passent en dernier');

        $this->getJson('/api/projects?status=done')->assertJsonCount(1)->assertJsonPath('0.title', 'Fini');
        $this->getJson('/api/projects?status=idea')->assertJsonCount(1)->assertJsonPath('0.title', 'Idée');
    }

    public function test_isolation_between_users(): void
    {
        $p = $this->project();
        $step = $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'A'])->json('steps.0.id');

        $other = User::factory()->create();
        $this->actingAs($other);

        $this->getJson('/api/projects')->assertJsonCount(0);
        $this->getJson("/api/projects/{$p['id']}")->assertNotFound();
        $this->putJson("/api/projects/{$p['id']}", ['title' => 'Piraté'])->assertNotFound();
        $this->deleteJson("/api/projects/{$p['id']}")->assertNotFound();
        $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'X'])->assertNotFound();
        $this->putJson("/api/project-steps/$step", ['done' => true])->assertNotFound();
        $this->deleteJson("/api/project-steps/$step")->assertNotFound();
    }

    public function test_deleting_a_project_deletes_its_steps(): void
    {
        $p = $this->project();
        $this->postJson("/api/projects/{$p['id']}/steps", ['title' => 'A']);

        $this->deleteJson("/api/projects/{$p['id']}")->assertOk();
        $this->assertDatabaseCount('project_steps', 0);
        $this->assertDatabaseCount('projects', 0);
    }

    public function test_deleting_a_contact_detaches_the_project_instead_of_deleting_it(): void
    {
        $contact = $this->postJson('/api/contacts', ['name' => 'Paul'])->json('id');
        $p = $this->project(['contact_id' => $contact]);

        $this->deleteJson("/api/contacts/$contact")->assertOk();
        $this->getJson("/api/projects/{$p['id']}")->assertOk()->assertJsonPath('contact_id', null);
    }

    public function test_a_domain_tag_can_be_set(): void
    {
        $p = $this->project(['domain' => 'commerce']);
        $this->assertSame('commerce', $p['domain']);
        $this->postJson('/api/projects', ['title' => 'X', 'domain' => str_repeat('a', 41)])->assertStatus(422);
    }

    public function test_a_project_can_be_linked_to_a_dossier_the_user_owns_without_leaking_its_content(): void
    {
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
        $dossier = $this->postJson('/api/dossiers', ['title' => 'Secret du projet', 'summary' => 'Très confidentiel'])->json();
        $this->postJson('/api/vault/lock')->assertOk();

        $p = $this->project(['dossier_id' => $dossier['id']]);
        $this->assertSame($dossier['id'], $p['dossier_id']);
        // Le titre et le résumé du dossier ne doivent JAMAIS apparaître dans la réponse du projet (coffre verrouillé).
        $raw = $this->getJson("/api/projects/{$p['id']}")->assertOk()->getContent();
        $this->assertStringNotContainsString('Secret du projet', $raw);
        $this->assertStringNotContainsString('confidentiel', $raw);
        $this->assertArrayNotHasKey('dossier', $this->getJson("/api/projects/{$p['id']}")->json());
    }

    public function test_a_project_cannot_be_linked_to_someone_elses_dossier(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
        $theirDossier = $this->postJson('/api/dossiers', ['title' => 'X'])->json('id');

        $this->actingAs($this->user);
        $this->postJson('/api/projects', ['title' => 'X', 'dossier_id' => $theirDossier])->assertStatus(422);
    }

    public function test_deleting_the_linked_dossier_detaches_it_from_the_project(): void
    {
        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
        $dossier = $this->postJson('/api/dossiers', ['title' => 'X'])->json();
        $this->postJson('/api/vault/lock')->assertOk();
        $p = $this->project(['dossier_id' => $dossier['id']]);

        $this->postJson('/api/vault/unlock', ['password' => 'password'])->assertOk();
        $this->deleteJson("/api/dossiers/{$dossier['id']}")->assertOk();

        $this->getJson("/api/projects/{$p['id']}")->assertOk()->assertJsonPath('dossier_id', null);
    }

    public function test_changing_the_target_date_rearms_the_reminder(): void
    {
        $p = $this->project(['target_date' => '2026-10-20']);
        Project::whereKey($p['id'])->update(['reminder_sent_for' => '2026-10-20']);

        // Même date : le rappel déjà envoyé reste marqué (pas vérifiable via l'API, le champ est caché ;
        // on vérifie seulement que changer la date le réarme).
        $this->putJson("/api/projects/{$p['id']}", ['target_date' => '2026-10-25'])->assertOk();
        $this->assertNull(Project::find($p['id'])->reminder_sent_for);
    }
}
