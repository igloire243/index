<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json($request->user()->languages()->create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $language = $request->user()->languages()->findOrFail($id);
        $language->update($this->validated($request));

        return response()->json($language->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->languages()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'level' => ['required', Rule::in(Language::LEVELS)],
        ]);
    }
}
