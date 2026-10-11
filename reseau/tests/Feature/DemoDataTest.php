<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): User
    {
        $this->artisan('reseau:demo')->assertSuccessful();

        return User::where('email', DemoSeeder::EMAIL)->firstOrFail();
    }

    public function test_creates_a_rich_demo_account(): void
    {
        $user = $this->demo();

        $this->assertGreaterThanOrEqual(30, $user->contacts()->count());
        $this->assertSame(13, $user->habits()->count());
        $this->assertGreaterThan(300, $user->habits()->withCount('logs')->get()->sum('logs_count'));
        $this->assertGreaterThanOrEqual(20, $user->measurements()->count());
        $this->assertGreaterThan(40, $user->meals()->count());
        $this->assertSame('gain', $user->healthGoal->goal);
        $this->assertSame('Africa/Kinshasa', $user->timezone);
    }

    public function test_demo_login_works(): void
    {
        $this->demo();

        $this->postJson('/api/login', ['email' => DemoSeeder::EMAIL, 'password' => DemoSeeder::PASSWORD])->assertOk();
    }

    public function test_data_gives_the_coach_something_to_say(): void
    {
        $user = $this->demo();
        $today = Carbon::now('Africa/Kinshasa')->toDateString();

        // Des contacts à relancer, un jamais contacté, des services dus et rendus.
        $this->assertGreaterThan(3, $user->contacts()->where('last_contact', '<', Carbon::now('Africa/Kinshasa')->subDays(90)->toDateString())->count());
        $this->assertGreaterThan(0, $user->contacts()->whereNull('last_contact')->count());
        $this->assertGreaterThan(0, $user->contacts()->where('favor', 'owe')->count());
        $this->assertGreaterThan(0, $user->contacts()->where('favor', 'owed')->count());
        // Moins de dix anecdotes, un domaine totalement absent (politique).
        $this->assertLessThan(10, $user->contacts()->whereNotNull('anecdote')->count());
        $this->assertFalse($user->contacts()->get()->contains(fn ($c) => in_array('politique', $c->domains, true)));
        // Aujourd'hui n'est pas terminé : la journée est « en cours ».
        $due = $user->habits()->get()->filter(fn (Habit $h) => $h->isDueOn(Carbon::parse($today)));
        $done = $due->filter(fn (Habit $h) => $h->logs()->whereDate('date', $today)->exists());
        $this->assertLessThan($due->count(), $done->count());
    }

    public function test_weight_story_is_a_realistic_gain(): void
    {
        $user = $this->demo();
        $weights = $user->measurements()->orderBy('date')->pluck('weight_kg');

        $this->assertEqualsWithDelta(61.2, $weights->first(), 0.6);
        $this->assertEqualsWithDelta(65.4, $weights->last(), 0.6);
        $this->assertTrue($weights->every(fn ($w) => $w > 55 && $w < 70));
    }

    public function test_is_idempotent_and_only_touches_the_demo_account(): void
    {
        $other = User::factory()->create();
        $other->contacts()->create(['name' => 'Autre', 'domains' => []]);

        $first = $this->demo();
        $contacts = $first->contacts()->count();
        $second = $this->demo();

        $this->assertSame(1, User::where('email', DemoSeeder::EMAIL)->count());
        $this->assertSame($contacts, $second->contacts()->count());
        $this->assertSame(1, $other->contacts()->count());
        $this->assertDatabaseCount('contacts', $contacts + 1);
    }

    public function test_is_deterministic(): void
    {
        $names = fn () => $this->demo()->measurements()->orderBy('date')->pluck('weight_kg')->all();

        $this->assertSame($names(), $names());
    }

    public function test_command_refuses_to_run_in_production_without_force(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('reseau:demo')->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
