<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HealthGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GoalController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->healthGoal()->first());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'goal' => ['required', Rule::in(HealthGoal::GOALS)],
            'target_weight_kg' => ['nullable', 'numeric', 'between:20,400'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $goal = $request->user()->healthGoal()->updateOrCreate([], $data);

        return response()->json($goal->fresh());
    }
}
