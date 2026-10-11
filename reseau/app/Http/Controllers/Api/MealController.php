<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meal;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MealController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $range = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        abort_if(Carbon::parse($range['from'])->diffInDays(Carbon::parse($range['to'])) > 62, 422, 'Période trop longue.');

        return response()->json(
            $request->user()->meals()->whereBetween('date', [$range['from'], $range['to']])->orderBy('date')->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $meal = $request->user()->meals()->create($this->validated($request));

        return response()->json($meal->fresh(), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $meal = $request->user()->meals()->findOrFail($id);
        $data = $this->validated($request, partial: true);
        if (array_key_exists('done', $data)) {
            $data['done_at'] = $data['done'] ? ($meal->done_at ?? now()) : null;
            unset($data['done']);
        }
        $meal->update($data);

        return response()->json($meal->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->meals()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    /** Recopie les repas d'une semaine vers une autre (non cochés), sans créer de doublons. */
    public function copyWeek(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
        ]);
        $from = Carbon::parse($data['from'])->startOfWeek();
        $to = Carbon::parse($data['to'])->startOfWeek();
        abort_if($from->equalTo($to), 422, 'Choisis deux semaines différentes.');

        $user = $request->user();
        $offset = $from->diffInDays($to, false);
        $created = 0;
        foreach ($user->meals()->whereBetween('date', [$from->toDateString(), $from->copy()->addDays(6)->toDateString()])->get() as $meal) {
            $date = $meal->date->copy()->addDays((int) $offset)->toDateString();
            $exists = $user->meals()->whereDate('date', $date)->where('slot', $meal->slot)->where('description', $meal->description)->exists();
            if (! $exists) {
                $user->meals()->create(['date' => $date, 'slot' => $meal->slot, 'description' => $meal->description]);
                $created++;
            }
        }

        return response()->json(['created' => $created]);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'date' => [$required, 'date_format:Y-m-d'],
            'slot' => [$required, Rule::in(Meal::SLOTS)],
            'description' => [$required, 'string', 'max:200'],
            'done' => ['sometimes', 'boolean'],
        ]);
    }
}
