<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMovement;
use App\Models\Debt;
use App\Models\DebtPayment;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DebtController extends Controller
{
    public static function present(Debt $debt, string $today): array
    {
        $payments = $debt->relationLoaded('payments') ? $debt->payments : $debt->payments()->get();
        $paid = round((float) $payments->sum('amount'), 2);
        $remaining = round(max($debt->amount - $paid, 0), 2);

        return $debt->toArray() + [
            'paid' => $paid,
            'remaining' => $remaining,
            'overdue' => $remaining > 0 && $debt->due_on !== null && $debt->due_on->toDateString() < $today,
            'payments' => $payments->values()->toArray(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $today = $this->today($request);
        $debts = $request->user()->debts()->with('payments')->orderByRaw('settled_at IS NOT NULL')->orderByRaw('due_on IS NULL')->orderBy('due_on')->orderByDesc('id')->get();

        return response()->json($debts->map(fn (Debt $d) => self::present($d, $today)));
    }

    public function store(Request $request): JsonResponse
    {
        $debt = $request->user()->debts()->create($this->validated($request) + ['opened_on' => $this->today($request)]);

        return response()->json(self::present($debt->fresh('payments'), $this->today($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $debt = $request->user()->debts()->findOrFail($id);
        $data = $this->validated($request, $debt);
        abort_if($debt->paid() > $data['amount'] + 0.004, 422, 'Le montant ne peut pas être inférieur à ce qui a déjà été réglé.');

        $debt->update($data);
        $this->refreshSettled($debt);

        return response()->json(self::present($debt->fresh('payments'), $this->today($request)));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->debts()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Enregistre un remboursement (reçu ou effectué), et crédite ou débite un compte si on en indique un. */
    public function addPayment(Request $request, int $id): JsonResponse
    {
        $debt = $request->user()->debts()->findOrFail($id);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'between:0.01,999999999999'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'account_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $amount = round((float) $data['amount'], 2);
        abort_if($amount > $debt->remaining() + 0.004, 422, 'Ce montant dépasse ce qu’il reste à régler.');

        $account = null;
        if (! empty($data['account_id'])) {
            $account = $request->user()->accounts()->whereNull('archived_at')->find($data['account_id']);
            abort_if($account === null, 422, 'Compte introuvable.');
            abort_if($account->currency !== $debt->currency, 422, "Le compte doit être en {$debt->currency}.");
        }

        $date = $data['date'] ?? $this->today($request);
        DB::transaction(function () use ($debt, $account, $amount, $date, $data) {
            $movement = $account
                ? AccountController::move($account, $debt->direction === 'owed_to_me' ? $amount : -$amount, $date, ($debt->direction === 'owed_to_me' ? 'Remboursement reçu de ' : 'Remboursement à ').$debt->counterparty)
                : null;
            $debt->payments()->create(['date' => $date, 'amount' => $amount, 'account_id' => $account?->id, 'movement_id' => $movement?->id, 'note' => $data['note'] ?? null]);
            $this->refreshSettled($debt);
        });

        return response()->json(self::present($debt->fresh('payments'), $this->today($request)), 201);
    }

    public function destroyPayment(Request $request, int $id, int $paymentId): JsonResponse
    {
        $debt = $request->user()->debts()->findOrFail($id);
        $payment = DebtPayment::where('debt_id', $debt->id)->findOrFail($paymentId);

        DB::transaction(function () use ($debt, $payment) {
            if ($payment->movement_id && ($movement = AccountMovement::find($payment->movement_id))) {
                $account = Account::findOrFail($movement->account_id);
                $account->update(['balance' => round($account->balance - $movement->amount, 2)]);
                $movement->delete();
            }
            $payment->delete();
            $this->refreshSettled($debt);
        });

        return response()->json(self::present($debt->fresh('payments'), $this->today($request)));
    }

    private function refreshSettled(Debt $debt): void
    {
        $debt->update(['settled_at' => $debt->remaining() <= 0.004 ? ($debt->settled_at ?? now()) : null]);
    }

    private function validated(Request $request, ?Debt $existing = null): array
    {
        $user = $request->user();
        $data = $request->validate([
            'direction' => ['required', Rule::in(Debt::DIRECTIONS)],
            'counterparty' => ['nullable', 'string', 'max:120'],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('user_id', $user->id)],
            'amount' => ['required', 'numeric', 'between:0.01,999999999999'],
            'currency' => AccountController::CURRENCY,
            'reason' => ['nullable', 'string', 'max:200'],
            'opened_on' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:opened_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (blank($data['counterparty'] ?? null)) {
            $contact = isset($data['contact_id']) ? $user->contacts()->find($data['contact_id']) : null;
            abort_if($contact === null, 422, 'Indique la personne concernée.');
            $data['counterparty'] = $contact->name;
        }
        $data['amount'] = round((float) $data['amount'], 2);
        if ($existing && $existing->payments()->exists() && $data['currency'] !== $existing->currency) {
            abort(422, 'La devise ne peut plus changer : des règlements existent déjà.');
        }
        if (empty($data['opened_on'])) {
            unset($data['opened_on']);
        }

        return $data;
    }

    private function today(Request $request): string
    {
        return Carbon::now($request->user()->timezone)->toDateString();
    }
}
