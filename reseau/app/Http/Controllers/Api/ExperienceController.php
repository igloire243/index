<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExperienceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $experience = $request->user()->experiences()->create($this->validated($request));

        return response()->json($experience->fresh('achievements'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $experience = $request->user()->experiences()->findOrFail($id);
        $data = $this->validated($request);
        if ($data['current']) {
            $data['end_date'] = null;
        }
        $experience->update($data);

        return response()->json($experience->fresh()->load('achievements'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->experiences()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'organization' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:120'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'current' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);
        $data['current'] = $data['current'] ?? false;

        return $data;
    }
}
