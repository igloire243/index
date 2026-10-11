<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['timezone' => 'Africa/Kinshasa']);
        $this->actingAs($this->user);
        Carbon::setTestNow(Carbon::parse('2026-10-12 08:30:00', 'UTC')); // lundi 12 octobre, 09:30 à Kinshasa
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function habit(array $overrides = []): array
    {
        return $this->postJson('/api/habits', $overrides + [
            'name' => 'Marche', 'category' => 'activite', 'days' => [3, 1, 5], 'time' => '17:30', 'reminder' => true,
        ])->assertCreated()->json();
    }

    public function test_habit_creation_sorts_days_and_defaults_start_date_in_user_timezone(): void
    {
        $habit = $this->habit();

        $this->assertSame([1, 3, 5], $habit['days']);
        $this->assertSame('2026-10-12', $habit['start_date']);
        $this->assertTrue($habit['reminder']);
        $this->assertArrayNotHasKey('user_id', $habit);
    }

    public function test_reminder_requires_a_time(): void
    {
        $this->assertFalse($this->habit(['time' => null, 'reminder' => true])['reminder']);
    }

    public function test_habit_validation(): void
    {
        $this->postJson('/api/habits', ['name' => 'X', 'category' => 'inconnue', 'days' => [1]])->assertStatus(422)->assertJsonValidationErrors('category');
        $this->postJson('/api/habits', ['name' => 'X', 'category' => 'soins', 'days' => []])->assertStatus(422)->assertJsonValidationErrors('days');
        $this->postJson('/api/habits', ['name' => 'X', 'category' => 'soins', 'days' => [8]])->assertStatus(422)->assertJsonValidationErrors('days.0');
        $this->postJson('/api/habits', ['name' => 'X', 'category' => 'soins', 'days' => [1, 1]])->assertStatus(422);
        $this->postJson('/api/habits', ['name' => 'X', 'category' => 'soins', 'days' => [1], 'time' => '25:00'])->assertStatus(422)->assertJsonValidationErrors('time');
        $this->postJson('/api/habits', ['name' => 'X', 'category' => 'soins', 'days' => [1], 'start_date' => '2026-10-10', 'end_date' => '2026-10-01'])->assertStatus(422);
    }

    public function test_is_due_on_follows_weekdays_and_active_period(): void
    {
        $habit = new Habit(['days' => [1, 3], 'start_date' => '2026-10-05', 'end_date' => '2026-10-20']);

        $this->assertTrue($habit->isDueOn(Carbon::parse('2026-10-12')));   // lundi
        $this->assertFalse($habit->isDueOn(Carbon::parse('2026-10-13')));  // mardi
        $this->assertFalse($habit->isDueOn(Carbon::parse('2026-09-28')));  // avant le début
        $this->assertFalse($habit->isDueOn(Carbon::parse('2026-10-26')));  // après la fin
        $habit->archived_at = now();
        $this->assertFalse($habit->isDueOn(Carbon::parse('2026-10-12')));
    }

    public function test_checking_and_unchecking_a_habit(): void
    {
        $id = $this->habit()['id'];

        $this->putJson("/api/habits/$id/logs/2026-10-12", ['done' => true])->assertOk();
        $this->putJson("/api/habits/$id/logs/2026-10-12", ['done' => true])->assertOk(); // idempotent
        $this->assertDatabaseCount('habit_logs', 1);

        $this->getJson('/api/habit-logs?from=2026-10-01&to=2026-10-31')->assertOk()->assertJsonCount(1)->assertJsonPath('0.date', '2026-10-12');

        $this->putJson("/api/habits/$id/logs/2026-10-12", ['done' => false])->assertOk();
        $this->assertDatabaseCount('habit_logs', 0);
    }

    public function test_cannot_check_a_habit_far_in_the_future_or_with_a_bad_date(): void
    {
        $id = $this->habit()['id'];

        $this->putJson("/api/habits/$id/logs/2026-10-20", ['done' => true])->assertStatus(422);
        $this->putJson("/api/habits/$id/logs/2026-13-45", ['done' => true])->assertStatus(422);
        $this->putJson("/api/habits/$id/logs/2026-10-12", [])->assertStatus(422);
    }

    public function test_archiving_keeps_history_and_permanent_delete_removes_it(): void
    {
        $id = $this->habit()['id'];
        $this->putJson("/api/habits/$id/logs/2026-10-12", ['done' => true]);

        $this->deleteJson("/api/habits/$id")->assertOk();
        $this->getJson('/api/habits')->assertJsonCount(0);
        $this->getJson('/api/habits?archived=1')->assertJsonCount(1);
        $this->assertDatabaseCount('habit_logs', 1);

        $this->deleteJson("/api/habits/$id?permanent=1")->assertOk();
        $this->assertDatabaseCount('habits', 0);
        $this->assertDatabaseCount('habit_logs', 0);
    }

    public function test_habits_are_private_to_their_owner(): void
    {
        $other = User::factory()->create();
        $theirs = $other->habits()->create(['name' => 'Secret', 'category' => 'soins', 'days' => [1], 'start_date' => '2026-10-01']);

        $this->getJson('/api/habits')->assertJsonCount(0);
        $this->putJson("/api/habits/{$theirs->id}", ['name' => 'X', 'category' => 'soins', 'days' => [1]])->assertNotFound();
        $this->putJson("/api/habits/{$theirs->id}/logs/2026-10-12", ['done' => true])->assertNotFound();
        $this->deleteJson("/api/habits/{$theirs->id}")->assertNotFound();

        $theirs->logs()->create(['date' => '2026-10-12']);
        $this->getJson('/api/habit-logs?from=2026-10-01&to=2026-10-31')->assertJsonCount(0);
    }

    public function test_log_range_is_limited(): void
    {
        $this->getJson('/api/habit-logs?from=2020-01-01&to=2026-10-31')->assertStatus(422);
    }

    public function test_meals_planning_toggle_and_copy_week(): void
    {
        $id = $this->postJson('/api/meals', ['date' => '2026-10-12', 'slot' => 'breakfast', 'description' => 'Bouillie et fruits'])->assertCreated()->json('id');
        $this->postJson('/api/meals', ['date' => '2026-10-14', 'slot' => 'dinner', 'description' => 'Poisson et légumes'])->assertCreated();
        $this->postJson('/api/meals', ['date' => '2026-10-12', 'slot' => 'brunch', 'description' => 'x'])->assertStatus(422);

        $this->putJson("/api/meals/$id", ['done' => true])->assertOk()->assertJsonPath('done_at', fn ($v) => $v !== null);
        $this->putJson("/api/meals/$id", ['done' => false])->assertOk()->assertJsonPath('done_at', null);

        $this->getJson('/api/meals?from=2026-10-12&to=2026-10-18')->assertJsonCount(2);

        $this->postJson('/api/meals/copy-week', ['from' => '2026-10-13', 'to' => '2026-10-20'])->assertOk()->assertJsonPath('created', 2);
        $this->postJson('/api/meals/copy-week', ['from' => '2026-10-13', 'to' => '2026-10-20'])->assertOk()->assertJsonPath('created', 0); // pas de doublon
        $this->getJson('/api/meals?from=2026-10-19&to=2026-10-25')->assertJsonCount(2)->assertJsonPath('0.date', '2026-10-19')->assertJsonPath('0.done_at', null);
        $this->postJson('/api/meals/copy-week', ['from' => '2026-10-12', 'to' => '2026-10-14'])->assertStatus(422); // même semaine
    }

    public function test_meals_are_private_to_their_owner(): void
    {
        $other = User::factory()->create();
        $theirs = $other->meals()->create(['date' => '2026-10-12', 'slot' => 'lunch', 'description' => 'Secret']);

        $this->getJson('/api/meals?from=2026-10-12&to=2026-10-12')->assertJsonCount(0);
        $this->putJson("/api/meals/{$theirs->id}", ['done' => true])->assertNotFound();
        $this->deleteJson("/api/meals/{$theirs->id}")->assertNotFound();
    }

    public function test_measurements_are_one_per_day_and_validated(): void
    {
        $this->putJson('/api/measurements/2026-10-05', ['weight_kg' => 71.5, 'energy' => 3, 'mood' => 4, 'sleep_hours' => 7.5])->assertOk()->assertJsonPath('weight_kg', 71.5);
        $this->putJson('/api/measurements/2026-10-05', ['weight_kg' => 71.0])->assertOk();
        $this->putJson('/api/measurements/2026-10-12', ['weight_kg' => 70.2, 'waist_cm' => 88])->assertOk();

        $this->assertDatabaseCount('measurements', 2);
        $this->getJson('/api/measurements')->assertJsonCount(2)->assertJsonPath('0.weight_kg', 71)->assertJsonPath('1.waist_cm', 88);

        $this->putJson('/api/measurements/2026-10-06', [])->assertStatus(422);
        $this->putJson('/api/measurements/2026-10-06', ['weight_kg' => 5])->assertStatus(422);
        $this->putJson('/api/measurements/2026-10-06', ['energy' => 9])->assertStatus(422);
        $this->putJson('/api/measurements/2027-01-01', ['weight_kg' => 70])->assertStatus(422);

        $this->deleteJson('/api/measurements/2026-10-05')->assertOk();
        $this->assertDatabaseCount('measurements', 1);
    }

    public function test_goal_roundtrip(): void
    {
        $this->getJson('/api/goal')->assertOk()->assertJsonPath('goal', null);

        $this->putJson('/api/goal', ['goal' => 'gain', 'target_weight_kg' => 68, 'target_date' => '2027-03-01'])->assertOk()->assertJsonPath('goal', 'gain');
        $this->putJson('/api/goal', ['goal' => 'maintain', 'target_weight_kg' => null])->assertOk();

        $this->assertDatabaseCount('health_goals', 1);
        $this->getJson('/api/goal')->assertJsonPath('goal', 'maintain')->assertJsonPath('target_weight_kg', null);
        $this->putJson('/api/goal', ['goal' => 'explode'])->assertStatus(422);
    }

    public function test_programs_listing_and_idempotent_adoption(): void
    {
        $programs = $this->getJson('/api/programs')->assertOk()->json();
        $this->assertGreaterThanOrEqual(5, count($programs));
        foreach ($programs as $program) {
            foreach ($program['habits'] as $habit) {
                $this->assertContains($habit['category'], Habit::CATEGORIES, $program['key'].' / '.$habit['name']);
                $this->assertNotEmpty($habit['days']);
            }
        }

        $count = count(config('programs.reprise-douce.habits'));
        $this->postJson('/api/programs/reprise-douce/adopt')->assertOk()->assertJsonPath('created', $count);
        $this->postJson('/api/programs/reprise-douce/adopt')->assertOk()->assertJsonPath('created', 0)->assertJsonPath('skipped', $count);
        $this->assertSame($count, $this->user->habits()->count());

        $this->postJson('/api/programs/inexistant/adopt')->assertNotFound();
    }
}
