<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->assets()->orderBy('category')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json($request->user()->assets()->create($this->validated($request)), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $asset = $request->user()->assets()->findOrFail($id);
        $asset->update($this->validated($request));

        return response()->json($asset->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->assets()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(config('finance.asset_categories'))],
            'status' => ['required', Rule::in(['owned', 'wanted'])],
            'value' => ['nullable', 'numeric', 'between:0,999999999999'],
            'currency' => AccountController::CURRENCY,
            'acquired_on' => ['nullable', 'date_format:Y-m-d'],
            'purchase_price' => ['nullable', 'numeric', 'between:0,999999999999'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
