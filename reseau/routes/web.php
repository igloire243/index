<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\HabitController;
use App\Http\Controllers\Api\MealController;
use App\Http\Controllers\Api\MeasurementController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\PushController;
use Illuminate\Support\Facades\Route;

$date = '\d{4}-\d{2}-\d{2}';

Route::prefix('api')->group(function () use ($date) {
    Route::post('login', [AuthController::class, 'login']);

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

        Route::get('programs', [ProgramController::class, 'index']);
        Route::post('programs/{key}/adopt', [ProgramController::class, 'adopt'])->where('key', '[a-z0-9-]+');

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
