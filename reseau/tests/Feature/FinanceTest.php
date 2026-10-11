<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['timezone' => 'Africa/Kinshasa']);
        $this->actingAs($this->user);
        Carbon::setTestNow(Carbon::parse('2026-10-12 08:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function account(array $o = []): array
    {
        return $this->postJson('/api/accounts', $o + ['name' => 'Rawbank', 'type' => 'bank', 'currency' => 'USD', 'balance' => 100])->assertCreated()->json();
    }

    private function debt(array $o = []): array
    {
        return $this->postJson('/api/debts', $o + ['direction' => 'owed_to_me', 'counterparty' => 'Paul', 'amount' => 200, 'currency' => 'USD'])->assertCreated()->json();
    }

    /* ---------- Comptes ---------- */

    public function test_an_account_records_its_opening_balance_as_a_movement(): void
    {
        $a = $this->account(['institution' => 'Rawbank', 'location' => 'Kinshasa', 'source' => 'Salaire', 'balance' => 250.5]);

        $this->assertEquals(250.5, $a['balance']);
        $this->assertSame('Salaire', $a['source']);
        $this->assertArrayNotHasKey('user_id', $a);
        $m = $this->getJson("/api/accounts/{$a['id']}/movements")->assertOk()->assertJsonCount(1);
        $this->assertSame('Solde initial', $m->json('0.note'));
        $this->assertSame('2026-10-12', $m->json('0.date'));
    }

    public function test_an_account_without_opening_balance_has_no_movement(): void
    {
        $a = $this->account(['balance' => null]);

        $this->assertEquals(0.0, $a['balance']);
        $this->getJson("/api/accounts/{$a['id']}/movements")->assertJsonCount(0);
    }

    public function test_account_validation(): void
    {
        $this->postJson('/api/accounts', ['name' => 'X', 'type' => 'bank', 'currency' => 'usd'])->assertStatus(422)->assertJsonValidationErrors('currency');
        $this->postJson('/api/accounts', ['name' => 'X', 'type' => 'coffre', 'currency' => 'USD'])->assertStatus(422)->assertJsonValidationErrors('type');
        $this->postJson('/api/accounts', ['name' => '', 'type' => 'bank', 'currency' => 'USD'])->assertStatus(422)->assertJsonValidationErrors('name');
        $this->postJson('/api/accounts', ['name' => 'X', 'type' => 'bank', 'currency' => 'USD', 'balance' => 'beaucoup'])->assertStatus(422);
    }

    public function test_deposits_and_withdrawals_update_the_balance_and_history(): void
    {
        $a = $this->account(['balance' => 0]);

        $this->postJson("/api/accounts/{$a['id']}/movements", ['kind' => 'deposit', 'amount' => 0.1])->assertOk();
        $this->postJson("/api/accounts/{$a['id']}/movements", ['kind' => 'deposit', 'amount' => 0.2, 'note' => 'Vente'])->assertOk()->assertJsonPath('balance', 0.3); // pas d'erreur d'arrondi
        $this->postJson("/api/accounts/{$a['id']}/movements", ['kind' => 'withdrawal', 'amount' => 0.05, 'date' => '2026-10-01'])->assertOk()->assertJsonPath('balance', 0.25);

        $this->getJson("/api/accounts/{$a['id']}/movements")->assertJsonCount(3);
        $this->postJson("/api/accounts/{$a['id']}/movements", ['kind' => 'deposit', 'amount' => 0])->assertStatus(422);
        $this->postJson("/api/accounts/{$a['id']}/movements", ['kind' => 'transfer', 'amount' => 5])->assertStatus(422);
    }

    public function test_adjusting_records_the_difference(): void
    {
        $a = $this->account(['balance' => 100]);

        $this->postJson("/api/accounts/{$a['id']}/adjust", ['balance' => 80])->assertOk()->assertJsonPath('balance', 80);
        $this->assertEquals(-20.0, $this->getJson("/api/accounts/{$a['id']}/movements")->json('0.amount'));
        $this->postJson("/api/accounts/{$a['id']}/adjust", ['balance' => 80])->assertStatus(422);
    }

    public function test_currency_cannot_change_but_other_fields_can(): void
    {
        $a = $this->account();

        $this->putJson("/api/accounts/{$a['id']}", ['name' => 'Rawbank pro', 'type' => 'bank', 'currency' => 'CDF'])->assertStatus(422);
        $this->putJson("/api/accounts/{$a['id']}", ['name' => 'Rawbank pro', 'type' => 'savings', 'currency' => 'USD', 'location' => 'Gombe'])
            ->assertOk()->assertJsonPath('name', 'Rawbank pro')->assertJsonPath('balance', 100)->assertJsonPath('location', 'Gombe');
    }

    public function test_transfers_between_accounts_of_the_same_currency(): void
    {
        $a = $this->account(['name' => 'A', 'balance' => 100]);
        $b = $this->account(['name' => 'B', 'balance' => 5]);

        $r = $this->postJson('/api/accounts/transfer', ['from_account_id' => $a['id'], 'to_account_id' => $b['id'], 'amount' => 40])->assertOk();

        $this->assertEquals(60.0, $r->json('from.balance'));
        $this->assertEquals(45.0, $r->json('to.balance'));
        $this->assertSame(
            $this->getJson("/api/accounts/{$a['id']}/movements")->json('0.transfer_id'),
            $this->getJson("/api/accounts/{$b['id']}/movements")->json('0.transfer_id'),
        );
    }

    public function test_transfers_between_currencies_need_the_received_amount(): void
    {
        $usd = $this->account(['name' => 'USD', 'balance' => 100]);
        $cdf = $this->account(['name' => 'CDF', 'currency' => 'CDF', 'balance' => 0]);
        $body = ['from_account_id' => $usd['id'], 'to_account_id' => $cdf['id'], 'amount' => 10];

        $this->postJson('/api/accounts/transfer', $body)->assertStatus(422);
        $r = $this->postJson('/api/accounts/transfer', $body + ['received_amount' => 27500])->assertOk();

        $this->assertEquals(90.0, $r->json('from.balance'));
        $this->assertEquals(27500.0, $r->json('to.balance'));
    }

    public function test_transfer_rules(): void
    {
        $a = $this->account(['name' => 'A']);

        $this->postJson('/api/accounts/transfer', ['from_account_id' => $a['id'], 'to_account_id' => $a['id'], 'amount' => 5])->assertStatus(422);
        $this->postJson('/api/accounts/transfer', ['from_account_id' => $a['id'], 'to_account_id' => 9999, 'amount' => 5])->assertNotFound();
        $this->assertEquals(100.0, Account::find($a['id'])->balance);
    }

    public function test_deleting_a_movement_corrects_the_balance_and_both_sides_of_a_transfer(): void
    {
        $a = $this->account(['name' => 'A', 'balance' => 100]);
        $b = $this->account(['name' => 'B', 'balance' => 0]);
        $this->postJson('/api/accounts/transfer', ['from_account_id' => $a['id'], 'to_account_id' => $b['id'], 'amount' => 30]);

        $this->postJson("/api/accounts/{$a['id']}/movements", ['kind' => 'withdrawal', 'amount' => 10]);
        $this->deleteJson('/api/movements/'.$this->getJson("/api/accounts/{$a['id']}/movements")->json('0.id'))->assertOk();
        $this->assertEquals(70.0, Account::find($a['id'])->balance);

        $transferMovement = $this->getJson("/api/accounts/{$a['id']}/movements")->json('0.id');
        $this->deleteJson("/api/movements/$transferMovement")->assertOk();
        $this->assertEquals(100.0, Account::find($a['id'])->balance);
        $this->assertEquals(0.0, Account::find($b['id'])->balance);
    }

    public function test_accounts_are_archived_then_hidden_and_closed_to_movements(): void
    {
        $a = $this->account();

        $this->deleteJson("/api/accounts/{$a['id']}")->assertOk();
        $this->getJson('/api/accounts')->assertJsonCount(0);
        $this->postJson("/api/accounts/{$a['id']}/movements", ['kind' => 'deposit', 'amount' => 5])->assertNotFound();
        $this->assertDatabaseHas('accounts', ['id' => $a['id']]);

        $b = $this->account(['name' => 'B']);
        $this->deleteJson("/api/accounts/{$b['id']}?permanent=1")->assertOk();
        $this->assertDatabaseMissing('accounts', ['id' => $b['id']]);
        $this->assertDatabaseCount('account_movements', 1); // seuls ceux du compte archivé restent
    }

    public function test_one_user_cannot_touch_anothers_accounts(): void
    {
        $other = User::factory()->create();
        $theirs = $other->accounts()->create(['name' => 'Secret', 'type' => 'bank', 'currency' => 'USD', 'balance' => 50]);
        $mine = $this->account();
        $movement = $theirs->movements()->create(['date' => '2026-10-01', 'amount' => 50]);

        $this->getJson('/api/accounts')->assertJsonCount(1);
        $this->getJson("/api/accounts/{$theirs->id}/movements")->assertNotFound();
        $this->postJson("/api/accounts/{$theirs->id}/movements", ['kind' => 'deposit', 'amount' => 5])->assertNotFound();
        $this->putJson("/api/accounts/{$theirs->id}", ['name' => 'X', 'type' => 'bank'])->assertNotFound();
        $this->deleteJson("/api/accounts/{$theirs->id}")->assertNotFound();
        $this->deleteJson("/api/movements/{$movement->id}")->assertNotFound();
        $this->postJson('/api/accounts/transfer', ['from_account_id' => $mine['id'], 'to_account_id' => $theirs->id, 'amount' => 5])->assertNotFound();
        $this->assertEquals(50.0, $theirs->fresh()->balance);
    }

    /* ---------- Créances et dettes ---------- */

    public function test_a_debt_takes_its_counterparty_from_a_contact(): void
    {
        $contact = $this->user->contacts()->create(['name' => 'Didier Lukusa', 'domains' => []]);

        $d = $this->postJson('/api/debts', ['direction' => 'owed_to_me', 'contact_id' => $contact->id, 'amount' => 150, 'currency' => 'USD', 'due_on' => '2026-10-20'])->assertCreated();

        $this->assertSame('Didier Lukusa', $d->json('counterparty'));
        $this->assertEquals(150.0, $d->json('remaining'));
        $this->assertSame('2026-10-12', $d->json('opened_on'));
        $this->assertFalse($d->json('overdue'));
    }

    public function test_debt_validation(): void
    {
        $other = User::factory()->create();
        $foreign = $other->contacts()->create(['name' => 'Autre', 'domains' => []]);

        $this->postJson('/api/debts', ['direction' => 'owed_to_me', 'amount' => 10, 'currency' => 'USD'])->assertStatus(422); // personne manquante
        $this->postJson('/api/debts', ['direction' => 'owed_to_me', 'contact_id' => $foreign->id, 'amount' => 10, 'currency' => 'USD'])->assertStatus(422); // contact d'un autre
        $this->postJson('/api/debts', ['direction' => 'peut-etre', 'counterparty' => 'X', 'amount' => 10, 'currency' => 'USD'])->assertStatus(422);
        $this->postJson('/api/debts', ['direction' => 'i_owe', 'counterparty' => 'X', 'amount' => 0, 'currency' => 'USD'])->assertStatus(422);
        $this->postJson('/api/debts', ['direction' => 'i_owe', 'counterparty' => 'X', 'amount' => 10, 'currency' => 'dollars'])->assertStatus(422);
        $this->postJson('/api/debts', ['direction' => 'i_owe', 'counterparty' => 'X', 'amount' => 10, 'currency' => 'USD', 'opened_on' => '2026-10-10', 'due_on' => '2026-10-01'])->assertStatus(422);
    }

    public function test_a_partial_repayment_credits_the_account_and_a_full_one_settles_the_debt(): void
    {
        $acc = $this->account(['balance' => 10]);
        $d = $this->debt(['amount' => 200]);

        $r = $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 50, 'account_id' => $acc['id']])->assertCreated();
        $this->assertEquals(150.0, $r->json('remaining'));
        $this->assertEquals(50.0, $r->json('paid'));
        $this->assertNull($r->json('settled_at'));
        $this->assertEquals(60.0, Account::find($acc['id'])->balance);
        $this->assertStringContainsString('Paul', $this->getJson("/api/accounts/{$acc['id']}/movements")->json('0.note'));

        $r = $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 150])->assertCreated(); // sans compte lié
        $this->assertEquals(0.0, $r->json('remaining'));
        $this->assertNotNull($r->json('settled_at'));
        $this->assertEquals(60.0, Account::find($acc['id'])->balance, 'sans compte, aucun solde n’est modifié');
    }

    public function test_repaying_a_debt_i_owe_debits_the_account(): void
    {
        $acc = $this->account(['balance' => 300]);
        $d = $this->debt(['direction' => 'i_owe', 'counterparty' => 'Banque', 'amount' => 120]);

        $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 120, 'account_id' => $acc['id']])->assertCreated()->assertJsonPath('remaining', 0);

        $this->assertEquals(180.0, Account::find($acc['id'])->balance);
    }

    public function test_payment_rules(): void
    {
        $usd = $this->account();
        $cdf = $this->account(['name' => 'CDF', 'currency' => 'CDF']);
        $d = $this->debt(['amount' => 100]);

        $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 101])->assertStatus(422);                          // dépasse le reste
        $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 10, 'account_id' => $cdf['id']])->assertStatus(422); // devise différente
        $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 10, 'account_id' => 9999])->assertStatus(422);
        $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => -5])->assertStatus(422);
        $this->assertEquals(100.0, Account::find($usd['id'])->balance);
        $this->assertDatabaseCount('debt_payments', 0);
    }

    public function test_deleting_a_payment_reverses_it(): void
    {
        $acc = $this->account(['balance' => 0]);
        $d = $this->debt(['amount' => 100]);
        $paid = $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 100, 'account_id' => $acc['id']])->json();
        $this->assertEquals(100.0, Account::find($acc['id'])->balance);

        $r = $this->deleteJson("/api/debts/{$d['id']}/payments/{$paid['payments'][0]['id']}")->assertOk();

        $this->assertEquals(100.0, $r->json('remaining'));
        $this->assertNull($r->json('settled_at'));
        $this->assertEquals(0.0, Account::find($acc['id'])->balance);
        $this->assertDatabaseCount('account_movements', 0);
    }

    public function test_an_amount_cannot_drop_below_what_was_already_settled(): void
    {
        $d = $this->debt(['amount' => 100]);
        $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 60]);

        $this->putJson("/api/debts/{$d['id']}", ['direction' => 'owed_to_me', 'counterparty' => 'Paul', 'amount' => 50, 'currency' => 'USD'])->assertStatus(422);
        $this->putJson("/api/debts/{$d['id']}", ['direction' => 'owed_to_me', 'counterparty' => 'Paul', 'amount' => 60, 'currency' => 'USD'])->assertOk()->assertJsonPath('remaining', 0);
        $this->putJson("/api/debts/{$d['id']}", ['direction' => 'owed_to_me', 'counterparty' => 'Paul', 'amount' => 60, 'currency' => 'CDF'])->assertStatus(422);
    }

    public function test_overdue_is_computed_in_the_users_timezone(): void
    {
        $this->debt(['counterparty' => 'En retard', 'opened_on' => '2026-09-01', 'due_on' => '2026-10-11']);
        $this->debt(['counterparty' => 'Aujourd’hui', 'opened_on' => '2026-09-01', 'due_on' => '2026-10-12']);
        $this->debt(['counterparty' => 'Réglée', 'opened_on' => '2026-09-01', 'due_on' => '2026-09-15', 'amount' => 10]);
        $settled = Debt::where('counterparty', 'Réglée')->first();
        $this->postJson("/api/debts/{$settled->id}/payments", ['amount' => 10]);

        $byName = collect($this->getJson('/api/debts')->assertOk()->json())->keyBy('counterparty');

        $this->assertTrue($byName['En retard']['overdue']);
        $this->assertFalse($byName['Aujourd’hui']['overdue']);
        $this->assertFalse($byName['Réglée']['overdue'], 'une dette réglée n’est jamais en retard');
    }

    public function test_deleting_an_account_keeps_the_payments_that_used_it(): void
    {
        $acc = $this->account(['balance' => 0]);
        $d = $this->debt(['amount' => 10]);
        $this->postJson("/api/debts/{$d['id']}/payments", ['amount' => 10, 'account_id' => $acc['id']]);

        $this->deleteJson("/api/accounts/{$acc['id']}?permanent=1")->assertOk();

        $this->assertDatabaseCount('debt_payments', 1);
        $this->assertNull(DebtPayment::first()->account_id);
    }

    public function test_one_user_cannot_touch_anothers_debts(): void
    {
        $other = User::factory()->create();
        $theirs = $other->debts()->create(['direction' => 'owed_to_me', 'counterparty' => 'Secret', 'amount' => 10, 'currency' => 'USD', 'opened_on' => '2026-10-01']);

        $this->getJson('/api/debts')->assertJsonCount(0);
        $this->putJson("/api/debts/{$theirs->id}", ['direction' => 'owed_to_me', 'counterparty' => 'X', 'amount' => 5, 'currency' => 'USD'])->assertNotFound();
        $this->postJson("/api/debts/{$theirs->id}/payments", ['amount' => 5])->assertNotFound();
        $this->deleteJson("/api/debts/{$theirs->id}")->assertNotFound();
    }

    /* ---------- Biens ---------- */

    public function test_assets_can_be_owned_or_wanted(): void
    {
        $owned = $this->postJson('/api/assets', ['name' => 'Toyota Hilux', 'category' => 'vehicule', 'status' => 'owned', 'value' => 18000, 'currency' => 'USD', 'acquired_on' => '2022-03-01', 'purchase_price' => 21000, 'location' => 'Kinshasa'])->assertCreated();
        $wanted = $this->postJson('/api/assets', ['name' => 'Parcelle à Mont-Ngafula', 'category' => 'terrain', 'status' => 'wanted', 'value' => 12000, 'currency' => 'USD'])->assertCreated();

        $this->assertEquals(18000.0, $owned->json('value'));
        $this->getJson('/api/assets')->assertJsonCount(2);
        $this->putJson("/api/assets/{$wanted->json('id')}", ['name' => 'Parcelle', 'category' => 'terrain', 'status' => 'owned', 'value' => 11000, 'currency' => 'USD'])->assertOk()->assertJsonPath('status', 'owned');
        $this->deleteJson("/api/assets/{$owned->json('id')}")->assertOk();
        $this->getJson('/api/assets')->assertJsonCount(1);
    }

    public function test_asset_validation_and_isolation(): void
    {
        $this->postJson('/api/assets', ['name' => 'X', 'category' => 'licorne', 'status' => 'owned', 'currency' => 'USD'])->assertStatus(422)->assertJsonValidationErrors('category');
        $this->postJson('/api/assets', ['name' => 'X', 'category' => 'terrain', 'status' => 'vendu', 'currency' => 'USD'])->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson('/api/assets', ['name' => 'X', 'category' => 'terrain', 'status' => 'owned', 'currency' => 'USD', 'value' => -5])->assertStatus(422);

        $theirs = User::factory()->create()->assets()->create(['name' => 'Secret', 'category' => 'terrain', 'status' => 'owned', 'currency' => 'USD']);
        $this->getJson('/api/assets')->assertJsonCount(0);
        $this->putJson("/api/assets/{$theirs->id}", ['name' => 'X', 'category' => 'terrain', 'status' => 'owned', 'currency' => 'USD'])->assertNotFound();
        $this->deleteJson("/api/assets/{$theirs->id}")->assertNotFound();
    }

    /* ---------- Réglages et vue d'ensemble ---------- */

    public function test_exchange_rate_settings(): void
    {
        $this->getJson('/api/finance')->assertOk()->assertJsonPath('settings.base_currency', 'USD');

        $r = $this->putJson('/api/finance/settings', ['base_currency' => 'USD', 'rates' => ['CDF' => 2800, 'EUR' => 0.92, 'USD' => 1]])->assertOk();
        $this->assertEquals(['CDF' => 2800, 'EUR' => 0.92], $r->json('rates'), 'la devise de référence n’a pas de taux');
        $this->putJson('/api/finance/settings', ['base_currency' => 'EUR', 'rates' => ['USD' => 1.09]])->assertOk()->assertJsonPath('base_currency', 'EUR');
        $this->assertDatabaseCount('finance_settings', 1);

        $this->putJson('/api/finance/settings', ['base_currency' => 'usd'])->assertStatus(422);
        $this->putJson('/api/finance/settings', ['base_currency' => 'USD', 'rates' => ['CDF' => 0]])->assertStatus(422);
        $this->putJson('/api/finance/settings', ['base_currency' => 'USD', 'rates' => ['cdf' => 5]])->assertStatus(422);
    }

    public function test_the_overview_returns_everything_for_the_current_user_only(): void
    {
        $this->account();
        $this->debt();
        $this->postJson('/api/assets', ['name' => 'Maison', 'category' => 'residence', 'status' => 'owned', 'currency' => 'USD', 'value' => 50000]);
        $other = User::factory()->create();
        $other->accounts()->create(['name' => 'Secret', 'type' => 'bank', 'currency' => 'USD', 'balance' => 1]);

        $r = $this->getJson('/api/finance')->assertOk();

        $r->assertJsonCount(1, 'accounts')->assertJsonCount(1, 'debts')->assertJsonCount(1, 'assets');
        $this->assertEquals(200.0, $r->json('debts.0.remaining'));
        $this->assertSame([], $r->json('debts.0.payments'));
    }

    public function test_finance_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->guard('web')->logout();

        foreach (['finance', 'accounts', 'debts', 'assets'] as $path) {
            $this->getJson("/api/$path")->assertUnauthorized();
        }
    }
}
