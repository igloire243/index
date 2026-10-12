<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SkillController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json($request->user()->skills()->create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $skill = $request->user()->skills()->findOrFail($id);
        $skill->update($this->validated($request));

        return response()->json($skill->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->skills()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(Skill::TYPES)],
            'name' => ['required', 'string', 'max:120'],
            'level' => ['nullable', 'integer', 'between:1,5'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
    }
}
