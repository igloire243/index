<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requires_authentication(): void
    {
        foreach (['contacts', 'habits', 'meals?from=2026-01-01&to=2026-01-07', 'measurements', 'goal', 'programs', 'me'] as $path) {
            $this->getJson("/api/$path")->assertUnauthorized();
        }
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'moi@example.com', 'password' => 'un-mot-de-passe-long']);

        $this->postJson('/api/login', ['email' => 'moi@example.com', 'password' => 'un-mot-de-passe-long'])
            ->assertOk()
            ->assertJsonPath('email', 'moi@example.com')
            ->assertJsonMissingPath('password');

        $this->getJson('/api/me')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        User::factory()->create(['email' => 'moi@example.com', 'password' => 'un-mot-de-passe-long']);

        $this->postJson('/api/login', ['email' => 'moi@example.com', 'password' => 'faux'])->assertStatus(422);
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        User::factory()->create(['email' => 'moi@example.com', 'password' => 'un-mot-de-passe-long']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'moi@example.com', 'password' => 'faux'])->assertStatus(422);
        }

        // Même avec le bon mot de passe, le compte est temporairement bloqué.
        $this->postJson('/api/login', ['email' => 'moi@example.com', 'password' => 'un-mot-de-passe-long'])
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/logout')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_profile_timezone_is_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/api/profile', ['timezone' => 'Africa/Kinshasa'])->assertOk()->assertJsonPath('timezone', 'Africa/Kinshasa');
        $this->putJson('/api/profile', ['timezone' => 'Mars/Olympus'])->assertStatus(422);
    }

    public function test_the_monthly_network_goal_can_be_set_cleared_and_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/me')->assertJsonPath('network_goal_monthly', null);
        $this->putJson('/api/profile', ['network_goal_monthly' => 5])->assertOk()->assertJsonPath('network_goal_monthly', 5);
        $this->putJson('/api/profile', ['network_goal_monthly' => null])->assertOk()->assertJsonPath('network_goal_monthly', null);
        $this->putJson('/api/profile', ['network_goal_monthly' => 0])->assertStatus(422);
        $this->putJson('/api/profile', ['network_goal_monthly' => 201])->assertStatus(422);

        // Mettre à jour un seul champ ne touche pas à l'autre.
        $this->putJson('/api/profile', ['timezone' => 'Africa/Kinshasa', 'network_goal_monthly' => 5])->assertOk();
        $this->putJson('/api/profile', ['network_goal_monthly' => 8])->assertOk()->assertJsonPath('timezone', 'Africa/Kinshasa');
    }

    public function test_spa_and_unknown_api_routes(): void
    {
        $this->withoutVite();

        $this->get('/')->assertOk()->assertSee('id="app"', false)->assertHeader('X-Frame-Options', 'DENY');
        $this->get('/sante/suivi')->assertOk()->assertSee('id="app"', false);
        $this->getJson('/api/nimporte-quoi')->assertNotFound()->assertJsonPath('message', 'Introuvable.');
    }
}
