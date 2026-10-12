<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AchievementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json($request->user()->achievements()->create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $achievement = $request->user()->achievements()->findOrFail($id);
        $achievement->update($this->validated($request));

        return response()->json($achievement->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->achievements()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $user = $request->user();

        return $request->validate([
            'experience_id' => ['nullable', 'integer', Rule::exists('experiences', 'id')->where('user_id', $user->id)],
            'title' => ['required', 'string', 'max:160'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
