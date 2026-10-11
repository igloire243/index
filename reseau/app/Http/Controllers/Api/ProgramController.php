<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramController extends Controller
{
    public function index(): JsonResponse
    {
        $programs = [];
        foreach (config('programs') as $key => $program) {
            $programs[] = ['key' => $key] + $program;
        }

        return response()->json($programs);
    }

    /** Ajoute au planning les habitudes du programme (celles déjà présentes ne sont pas dupliquées). */
    public function adopt(Request $request, string $key): JsonResponse
    {
        $program = config("programs.$key");
        abort_if($program === null, 404);

        $user = $request->user();
        $today = Carbon::now($user->timezone)->toDateString();
        $existing = $user->habits()->whereNull('archived_at')->where('program_key', $key)->pluck('name')->all();

        $created = 0;
        foreach ($program['habits'] as $habit) {
            if (in_array($habit['name'], $existing, true)) {
                continue;
            }
            $user->habits()->create([
                'program_key' => $key,
                'name' => $habit['name'],
                'category' => $habit['category'],
                'days' => $habit['days'],
                'time' => $habit['time'] ?? null,
                'reminder' => (bool) ($habit['reminder'] ?? false) && ! empty($habit['time']),
                'notes' => $habit['notes'] ?? null,
                'start_date' => $today,
            ]);
            $created++;
        }

        return response()->json(['created' => $created, 'skipped' => count($program['habits']) - $created]);
    }
}
