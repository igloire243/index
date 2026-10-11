<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    /** Tout le patrimoine en un appel : fonds, créances et dettes (avec règlements), biens, taux de change. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::now($user->timezone)->toDateString();

        return response()->json([
            'accounts' => $user->accounts()->whereNull('archived_at')->orderBy('name')->get(),
            'debts' => $user->debts()->with('payments')->orderBy('due_on')->orderByDesc('id')->get()->map(fn (Debt $d) => DebtController::present($d, $today)),
            'assets' => $user->assets()->orderBy('category')->orderBy('name')->get(),
            'settings' => $this->settings($request),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_currency' => ['required', 'string', 'regex:/^[A-Z]{3,6}$/'],
            'rates' => ['nullable', 'array', 'max:20'],
            'rates.*' => ['numeric', 'gt:0', 'max:1000000000000'],
        ]);
        $rates = [];
        foreach ($data['rates'] ?? [] as $code => $rate) {
            abort_unless(preg_match('/^[A-Z]{3,6}$/', (string) $code) === 1, 422, 'Code de devise invalide.');
            if ($code !== $data['base_currency']) {
                $rates[$code] = (float) $rate;
            }
        }
        $request->user()->financeSetting()->updateOrCreate([], ['base_currency' => $data['base_currency'], 'rates' => $rates]);

        return response()->json($this->settings($request));
    }

    private function settings(Request $request): array
    {
        $s = $request->user()->financeSetting()->first();

        return ['base_currency' => $s?->base_currency ?? 'USD', 'rates' => $s?->rates ?? (object) []];
    }
}
