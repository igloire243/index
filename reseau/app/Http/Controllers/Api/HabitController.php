<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HabitController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->habits()->orderByRaw('time IS NULL, time')->orderBy('name');
        if (! $request->boolean('archived')) {
            $query->whereNull('archived_at');
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['start_date'] ??= Carbon::now($request->user()->timezone)->toDateString();
        $habit = $request->user()->habits()->create($data);

        return response()->json($habit->fresh(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $habit = $request->user()->habits()->findOrFail($id);
        $data = $this->validated($request);
        $data['archived_at'] = $request->boolean('archived') ? ($habit->archived_at ?? now()) : null;
        $habit->update($data);

        return response()->json($habit->fresh());
    }

    /** Archive l'habitude (l'historique est conservé) ; `permanent=1` supprime aussi l'historique. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $habit = $request->user()->habits()->findOrFail($id);
        if ($request->boolean('permanent')) {
            $habit->delete();
        } else {
            $habit->update(['archived_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }

    public function logs(Request $request): JsonResponse
    {
        $range = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        abort_if(Carbon::parse($range['from'])->diffInDays(Carbon::parse($range['to'])) > 400, 422, 'Période trop longue.');

        $logs = HabitLog::query()
            ->whereIn('habit_id', $request->user()->habits()->select('id'))
            ->whereBetween('date', [$range['from'], $range['to']])
            ->get(['habit_id', 'date']);

        return response()->json($logs);
    }

    /** Coche ou décoche une habitude pour un jour donné. */
    public function setLog(Request $request, int $id, string $date): JsonResponse
    {
        $habit = $request->user()->habits()->findOrFail($id);
        $request->validate(['done' => ['required', 'boolean']]);
        validator(['date' => $date], ['date' => ['date_format:Y-m-d']])->validate();

        // On accepte jusqu'à demain pour absorber les écarts de fuseau, pas plus.
        abort_if(Carbon::parse($date)->gt(Carbon::now($request->user()->timezone)->addDay()->endOfDay()), 422, 'Date dans le futur.');

        if ($request->boolean('done')) {
            HabitLog::firstOrCreate(['habit_id' => $habit->id, 'date' => $date]);
        } else {
            HabitLog::where('habit_id', $habit->id)->whereDate('date', $date)->delete();
        }

        return response()->json(['habit_id' => $habit->id, 'date' => $date, 'done' => $request->boolean('done')]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(Habit::CATEGORIES)],
            'days' => ['required', 'array', 'min:1', 'max:7'],
            'days.*' => ['integer', 'between:1,7', 'distinct'],
            'time' => ['nullable', 'date_format:H:i'],
            'reminder' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'program_key' => ['nullable', 'string', 'max:40'],
        ]);
        sort($data['days']);
        $data['reminder'] = ($data['reminder'] ?? false) && ! empty($data['time']);

        return $data;
    }
}
