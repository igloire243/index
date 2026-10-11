<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DebtController;
use App\Http\Controllers\Api\DossierController;
use App\Http\Controllers\Api\DossierFileController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HabitController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\PasskeyController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\PushController;
use App\Http\Controllers\Api\SecretController;
use App\Http\Controllers\Api\VaultController;
use Illuminate\Support\Facades\Route;

$date = '\d{4}-\d{2}-\d{2}';

Route::prefix('api')->group(function () use ($date) {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('passkey-login/options', [PasskeyController::class, 'loginOptions']);
    Route::post('passkey-login', [PasskeyController::class, 'login']);

    Route::middleware('auth')->group(function () use ($date) {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);

        Route::get('contacts', [ContactController::class, 'index']);
        Route::post('contacts', [ContactController::class, 'store']);
        Route::post('contacts/import', [ContactController::class, 'import']);
        Route::delete('contacts', [ContactController::class, 'destroyAll']);
        Route::put('contacts/{id}', [ContactController::class, 'update'])->whereNumber('id');
        Route::delete('contacts/{id}', [ContactController::class, 'destroy'])->whereNumber('id');

        Route::get('habits', [HabitController::class, 'index']);
        Route::post('habits', [HabitController::class, 'store']);
        Route::put('habits/{id}', [HabitController::class, 'update'])->whereNumber('id');
        Route::delete('habits/{id}', [HabitController::class, 'destroy'])->whereNumber('id');
        Route::get('habit-logs', [HabitController::class, 'logs']);
        Route::put('habits/{id}/logs/{date}', [HabitController::class, 'setLog'])->whereNumber('id')->where('date', $date);

        Route::get('meals', [MealController::class, 'index']);
        Route::post('meals', [MealController::class, 'store']);
        Route::post('meals/copy-week', [MealController::class, 'copyWeek']);
        Route::put('meals/{id}', [MealController::class, 'update'])->whereNumber('id');
        Route::delete('meals/{id}', [MealController::class, 'destroy'])->whereNumber('id');

        Route::get('measurements', [MeasurementController::class, 'index']);
        Route::put('measurements/{date}', [MeasurementController::class, 'upsert'])->where('date', $date);
        Route::delete('measurements/{date}', [MeasurementController::class, 'destroy'])->where('date', $date);

        Route::get('goal', [GoalController::class, 'show']);
        Route::put('goal', [GoalController::class, 'update']);

        Route::get('finance', [FinanceController::class, 'show']);
        Route::put('finance/settings', [FinanceController::class, 'updateSettings']);

        Route::get('accounts', [AccountController::class, 'index']);
        Route::post('accounts', [AccountController::class, 'store']);
        Route::post('accounts/transfer', [AccountController::class, 'transfer']);
        Route::put('accounts/{id}', [AccountController::class, 'update'])->whereNumber('id');
        Route::delete('accounts/{id}', [AccountController::class, 'destroy'])->whereNumber('id');
        Route::get('accounts/{id}/movements', [AccountController::class, 'movements'])->whereNumber('id');
        Route::post('accounts/{id}/movements', [AccountController::class, 'addMovement'])->whereNumber('id');
        Route::post('accounts/{id}/adjust', [AccountController::class, 'adjust'])->whereNumber('id');
        Route::delete('movements/{id}', [AccountController::class, 'destroyMovement'])->whereNumber('id');

        Route::get('debts', [DebtController::class, 'index']);
        Route::post('debts', [DebtController::class, 'store']);
        Route::put('debts/{id}', [DebtController::class, 'update'])->whereNumber('id');
        Route::delete('debts/{id}', [DebtController::class, 'destroy'])->whereNumber('id');
        Route::post('debts/{id}/payments', [DebtController::class, 'addPayment'])->whereNumber('id');
        Route::delete('debts/{id}/payments/{paymentId}', [DebtController::class, 'destroyPayment'])->whereNumber(['id', 'paymentId']);

        Route::get('assets', [AssetController::class, 'index']);
        Route::post('assets', [AssetController::class, 'store']);
        Route::put('assets/{id}', [AssetController::class, 'update'])->whereNumber('id');
        Route::delete('assets/{id}', [AssetController::class, 'destroy'])->whereNumber('id');

        Route::get('programs', [ProgramController::class, 'index']);
        Route::post('programs/{key}/adopt', [ProgramController::class, 'adopt'])->where('key', '[a-z0-9-]+');

        Route::get('passkeys', [PasskeyController::class, 'index']);
        Route::post('passkeys/options', [PasskeyController::class, 'registerOptions']);
        Route::post('passkeys', [PasskeyController::class, 'register']);
        Route::delete('passkeys/{id}', [PasskeyController::class, 'destroy'])->whereNumber('id');

        Route::get('vault', [VaultController::class, 'status']);
        Route::post('vault/unlock', [VaultController::class, 'unlock']);
        Route::post('vault/lock', [VaultController::class, 'lock']);

        // Tout ce qui touche aux dossiers exige que le coffre soit déverrouillé.
        Route::middleware('vault')->group(function () {
            Route::get('secrets', [SecretController::class, 'index']);
            Route::post('secrets', [SecretController::class, 'store']);
            Route::get('secrets/{id}', [SecretController::class, 'show'])->whereNumber('id');
            Route::put('secrets/{id}', [SecretController::class, 'update'])->whereNumber('id');
            Route::delete('secrets/{id}', [SecretController::class, 'destroy'])->whereNumber('id');

            Route::get('dossiers', [DossierController::class, 'index']);
            Route::post('dossiers', [DossierController::class, 'store']);
            Route::get('dossiers/{id}', [DossierController::class, 'show'])->whereNumber('id');
            Route::put('dossiers/{id}', [DossierController::class, 'update'])->whereNumber('id');
            Route::delete('dossiers/{id}', [DossierController::class, 'destroy'])->whereNumber('id');
            Route::post('dossiers/{dossierId}/files', [DossierFileController::class, 'init'])->whereNumber('dossierId');
            Route::put('dossier-files/{id}/chunks/{index}', [DossierFileController::class, 'chunk'])->whereNumber(['id', 'index']);
            Route::post('dossier-files/{id}/complete', [DossierFileController::class, 'complete'])->whereNumber('id');
            Route::get('dossier-files/{id}/content', [DossierFileController::class, 'content'])->whereNumber('id');
            Route::put('dossier-files/{id}', [DossierFileController::class, 'update'])->whereNumber('id');
            Route::delete('dossier-files/{id}', [DossierFileController::class, 'destroy'])->whereNumber('id');
        });

        Route::get('push/key', [PushController::class, 'key']);
        Route::post('push/subscribe', [PushController::class, 'subscribe']);
        Route::post('push/unsubscribe', [PushController::class, 'unsubscribe']);
        Route::post('push/test', [PushController::class, 'test']);
    });

    // Toute autre URL /api/* est une erreur 404 JSON, jamais la page de l'application.
    Route::any('{any}', fn () => response()->json(['message' => 'Introuvable.'], 404))->where('any', '.*');
});

// L'application Vue gère elle-même ses pages (/contacts, /sante, …).
Route::view('/{any?}', 'app')->where('any', '^(?!api(/|$)).*$');
