<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMovement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public const CURRENCY = ['required', 'string', 'regex:/^[A-Z]{3,6}$/'];

    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->accounts()->whereNull('archived_at')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...$this->metaRules(),
            'currency' => self::CURRENCY,
            'balance' => ['nullable', 'numeric', 'between:-999999999999,999999999999'],
        ]);
        $opening = round((float) ($data['balance'] ?? 0), 2);
        unset($data['balance']);

        $account = DB::transaction(function () use ($request, $data, $opening) {
            $account = $request->user()->accounts()->create($data + ['balance' => 0]);
            if ($opening !== 0.0) {
                $this->move($account, $opening, $this->today($request), 'Solde initial');
            }

            return $account->fresh();
        });

        return response()->json($account, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $account = $request->user()->accounts()->findOrFail($id);
        $data = $request->validate([
            ...$this->metaRules(),
            // La devise ne change pas : les mouvements existants seraient faussés.
            'currency' => ['nullable', 'string', Rule::in([$account->currency])],
        ]);
        unset($data['currency']);
        $account->update($data);

        return response()->json($account->fresh());
    }

    /** Archive le compte (son historique est conservé) ; `permanent=1` le supprime avec ses mouvements. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $account = $request->user()->accounts()->findOrFail($id);
        if ($request->boolean('permanent')) {
            $account->delete();
        } else {
            $account->update(['archived_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function movements(Request $request, int $id): JsonResponse
    {
        $account = $request->user()->accounts()->findOrFail($id);

        return response()->json($account->movements()->orderByDesc('date')->orderByDesc('id')->limit(100)->get(['id', 'date', 'amount', 'note', 'transfer_id']));
    }

    public function addMovement(Request $request, int $id): JsonResponse
    {
        $account = $request->user()->accounts()->whereNull('archived_at')->findOrFail($id);
        $data = $request->validate([
            'kind' => ['required', Rule::in(['deposit', 'withdrawal'])],
            'amount' => ['required', 'numeric', 'between:0.01,999999999999'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $signed = round((float) $data['amount'], 2) * ($data['kind'] === 'withdrawal' ? -1 : 1);

        DB::transaction(fn () => $this->move($account, $signed, $data['date'] ?? $this->today($request), $data['note'] ?? null));

        return response()->json($account->fresh());
    }

    /** Remplace le solde par la valeur réelle constatée ; la différence est enregistrée comme « Ajustement ». */
    public function adjust(Request $request, int $id): JsonResponse
    {
        $account = $request->user()->accounts()->whereNull('archived_at')->findOrFail($id);
        $data = $request->validate([
            'balance' => ['required', 'numeric', 'between:-999999999999,999999999999'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $delta = round((float) $data['balance'] - $account->balance, 2);
        abort_if($delta === 0.0, 422, 'Le solde est déjà celui-ci.');

        DB::transaction(fn () => $this->move($account, $delta, $data['date'] ?? $this->today($request), $data['note'] ?? 'Ajustement'));

        return response()->json($account->fresh());
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'numeric', 'between:0.01,999999999999'],
            'received_amount' => ['nullable', 'numeric', 'between:0.01,999999999999'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $accounts = $request->user()->accounts()->whereNull('archived_at')->whereIn('id', [$data['from_account_id'], $data['to_account_id']])->get()->keyBy('id');
        abort_unless($accounts->count() === 2, 404);
        $from = $accounts[$data['from_account_id']];
        $to = $accounts[$data['to_account_id']];

        // Même devise : le montant reçu est le montant envoyé. Devises différentes : l'utilisateur indique ce qui est arrivé.
        $sent = round((float) $data['amount'], 2);
        if ($from->currency === $to->currency) {
            $received = $sent;
        } else {
            abort_if(! isset($data['received_amount']), 422, "Indique le montant reçu en {$to->currency}.");
            $received = round((float) $data['received_amount'], 2);
        }

        $transferId = (string) Str::uuid();
        $date = $data['date'] ?? $this->today($request);
        $note = $data['note'] ?? null;

        DB::transaction(function () use ($from, $to, $sent, $received, $date, $note, $transferId) {
            $this->move($from, -$sent, $date, $note ?? "Virement vers {$to->name}", $transferId);
            $this->move($to, $received, $date, $note ?? "Virement depuis {$from->name}", $transferId);
        });

        return response()->json(['from' => $from->fresh(), 'to' => $to->fresh()]);
    }

    /** Annule un mouvement (les deux côtés d'un virement ensemble) et corrige le solde. */
    public function destroyMovement(Request $request, int $id): JsonResponse
    {
        $accountIds = $request->user()->accounts()->pluck('id');
        $movement = AccountMovement::whereIn('account_id', $accountIds)->findOrFail($id);

        DB::transaction(function () use ($movement, $accountIds) {
            $targets = $movement->transfer_id
                ? AccountMovement::whereIn('account_id', $accountIds)->where('transfer_id', $movement->transfer_id)->get()
                : collect([$movement]);

            foreach ($targets as $m) {
                $account = Account::findOrFail($m->account_id);
                $account->update(['balance' => round($account->balance - $m->amount, 2)]);
                $m->delete();
            }
        });

        return response()->json(['ok' => true]);
    }

    /** Enregistre un mouvement et met à jour le solde. À appeler dans une transaction. */
    public static function move(Account $account, float $signed, string $date, ?string $note, ?string $transferId = null): AccountMovement
    {
        $movement = $account->movements()->create(['date' => $date, 'amount' => $signed, 'note' => $note, 'transfer_id' => $transferId]);
        $account->update(['balance' => round($account->balance + $signed, 2)]);

        return $movement;
    }

    private function metaRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(config('finance.account_types'))],
            'institution' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function today(Request $request): string
    {
        return Carbon::now($request->user()->timezone)->toDateString();
    }
}
