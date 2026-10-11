<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $range = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $query = $request->user()->measurements()->orderBy('date');
        $query->where('date', '>=', $range['from'] ?? Carbon::now($request->user()->timezone)->subDays(365)->toDateString());
        if (isset($range['to'])) {
            $query->where('date', '<=', $range['to']);
        }

        return response()->json($query->get());
    }

    /** Crée ou remplace la mesure d'un jour (une seule par jour). */
    public function upsert(Request $request, string $date): JsonResponse
    {
        validator(['date' => $date], ['date' => ['date_format:Y-m-d']])->validate();
        abort_if(Carbon::parse($date)->gt(Carbon::now($request->user()->timezone)->addDay()->endOfDay()), 422, 'Date dans le futur.');

        $data = $request->validate([
            'weight_kg' => ['nullable', 'numeric', 'between:20,400'],
            'waist_cm' => ['nullable', 'numeric', 'between:30,250'],
            'sleep_hours' => ['nullable', 'numeric', 'between:0,24'],
            'energy' => ['nullable', 'integer', 'between:1,5'],
            'mood' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        abort_if(count(array_filter($data, fn ($v) => $v !== null)) === 0, 422, 'Renseigne au moins une valeur.');

        $measurement = $request->user()->measurements()->updateOrCreate(['date' => $date], $data);

        return response()->json($measurement->fresh());
    }

    public function destroy(Request $request, string $date): JsonResponse
    {
        validator(['date' => $date], ['date' => ['date_format:Y-m-d']])->validate();
        $request->user()->measurements()->whereDate('date', $date)->delete();

        return response()->json(['ok' => true]);
    }
}
