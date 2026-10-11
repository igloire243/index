<?php

namespace Tests\Feature;

use App\Models\Habit;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushSender;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakePushSender implements PushSender
{
    public bool $on = true;
    public string $result = self::SENT;
    /** @var list<array{endpoint: string, payload: array}> */
    public array $sent = [];

    public function enabled(): bool
    {
        return $this->on;
    }

    public function send(PushSubscription $subscription, array $payload): string
    {
        $this->sent[] = ['endpoint' => $subscription->endpoint, 'payload' => $payload];

        return $this->result;
    }
}

class RemindersTest extends TestCase
{
    use RefreshDatabase;

    private FakePushSender $push;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->push = new FakePushSender;
        $this->app->instance(PushSender::class, $this->push);

        $this->user = User::factory()->create(['timezone' => 'Africa/Kinshasa']); // UTC+1
        $this->subscribe($this->user, 'https://push.example/a');
        $this->at('2026-10-12 08:30:00'); // lundi 09:30 heure locale
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
    }

    private function subscribe(User $user, string $endpoint): PushSubscription
    {
        return $user->pushSubscriptions()->create([
            'endpoint' => $endpoint, 'endpoint_hash' => hash('sha256', $endpoint), 'public_key' => 'k', 'auth_token' => 'a',
        ]);
    }

    private function habit(array $o = []): Habit
    {
        return $this->user->habits()->create($o + [
            'name' => 'Boire de l\'eau', 'category' => 'hydratation', 'days' => [1, 2, 3, 4, 5, 6, 7],
            'time' => '09:00', 'reminder' => true, 'start_date' => '2026-10-01',
        ]);
    }

    private function run_(): void
    {
        $this->artisan('reseau:send-reminders')->assertSuccessful();
    }

    public function test_sends_a_due_reminder_once_per_day(): void
    {
        $habit = $this->habit();

        $this->run_();
        $this->assertCount(1, $this->push->sent);
        $this->assertSame('Rappel santé', $this->push->sent[0]['payload']['title']);
        $this->assertStringContainsString('Boire de l\'eau', $this->push->sent[0]['payload']['body']);
        $this->assertSame('/sante', $this->push->sent[0]['payload']['url']);
        $this->assertSame('2026-10-12', $habit->fresh()->last_reminded_on->toDateString());

        $this->at('2026-10-12 08:45:00');
        $this->run_();
        $this->assertCount(1, $this->push->sent, 'pas de second rappel le même jour');

        $this->at('2026-10-13 08:30:00'); // lendemain
        $this->run_();
        $this->assertCount(2, $this->push->sent);
    }

    public function test_uses_the_users_timezone(): void
    {
        $this->habit(['time' => '10:00']);

        $this->run_(); // 09:30 à Kinshasa : trop tôt
        $this->assertCount(0, $this->push->sent);

        $this->at('2026-10-12 09:05:00'); // 10:05 à Kinshasa
        $this->run_();
        $this->assertCount(1, $this->push->sent);
    }

    public function test_skips_done_archived_not_due_and_expired_windows(): void
    {
        $done = $this->habit(['name' => 'Fait']);
        $done->logs()->create(['date' => '2026-10-12']);
        $this->habit(['name' => 'Archivée', 'archived_at' => now()]);
        $this->habit(['name' => 'Mardi seulement', 'days' => [2]]);
        $this->habit(['name' => 'Sans rappel', 'reminder' => false]);
        $this->habit(['name' => 'Pas encore commencée', 'start_date' => '2026-10-20']);
        $this->habit(['name' => 'Trop tard', 'time' => '05:00']); // plus de 3 h de retard

        $this->run_();

        $this->assertCount(0, $this->push->sent);
    }

    public function test_groups_several_habits_into_one_notification(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $this->habit(['name' => $name]);
        }

        $this->run_();

        $this->assertCount(1, $this->push->sent);
        $this->assertSame('4 rappels santé', $this->push->sent[0]['payload']['title']);
        $this->assertStringEndsWith('…', $this->push->sent[0]['payload']['body']);
    }

    public function test_failed_delivery_is_retried_and_expired_subscriptions_are_removed(): void
    {
        $habit = $this->habit();

        $this->push->result = PushSender::FAILED;
        $this->run_();
        $this->assertNull($habit->fresh()->last_reminded_on, 'un échec ne consomme pas le rappel');

        $this->push->result = PushSender::GONE;
        $this->run_();
        $this->assertDatabaseCount('push_subscriptions', 0);
        $this->assertNull($habit->fresh()->last_reminded_on);
    }

    public function test_does_not_notify_users_without_subscription_and_does_nothing_when_disabled(): void
    {
        $other = User::factory()->create(['timezone' => 'Africa/Kinshasa']);
        $other->habits()->create(['name' => 'Sans abonnement', 'category' => 'soins', 'days' => [1], 'time' => '09:00', 'reminder' => true, 'start_date' => '2026-10-01']);
        $this->run_();
        $this->assertCount(0, $this->push->sent);

        $this->habit();
        $this->push->on = false;
        $this->run_();
        $this->assertCount(0, $this->push->sent);
    }

    public function test_reminders_only_go_to_the_owner_of_the_habit(): void
    {
        $other = User::factory()->create(['timezone' => 'Africa/Kinshasa']);
        $this->subscribe($other, 'https://push.example/b');
        $this->habit();

        $this->run_();

        $this->assertSame(['https://push.example/a'], array_column($this->push->sent, 'endpoint'));
    }

    public function test_subscription_endpoints(): void
    {
        $this->actingAs($this->user);
        $body = ['endpoint' => 'https://push.example/new', 'keys' => ['p256dh' => 'abc', 'auth' => 'def']];

        $this->postJson('/api/push/subscribe', $body)->assertCreated();
        $this->postJson('/api/push/subscribe', $body)->assertCreated(); // ré-abonnement : pas de doublon
        $this->assertSame(1, PushSubscription::where('endpoint', 'https://push.example/new')->count());

        $this->postJson('/api/push/subscribe', ['endpoint' => 'http://insecure.example', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertStatus(422);

        $this->postJson('/api/push/unsubscribe', ['endpoint' => 'https://push.example/new'])->assertOk();
        $this->assertSame(0, PushSubscription::where('endpoint', 'https://push.example/new')->count());

        $this->postJson('/api/push/test')->assertOk()->assertJsonPath('sent', 1); // l'abonnement « a » de setUp
        $this->push->on = false;
        $this->postJson('/api/push/test')->assertStatus(503);
        $this->getJson('/api/push/key')->assertOk()->assertJsonPath('public_key', null);
    }
}
