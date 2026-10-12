<?php

namespace Tests\Feature;

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
}
