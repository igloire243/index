<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecretController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $list = $request->user()->secrets()->get()->map->summary()->sortBy(fn ($s) => mb_strtolower($s['title']))->values();

        return $this->noStore(response()->json($list));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $secret = $request->user()->secrets()->create(['type' => $data['type'], 'favorite' => $data['favorite'] ?? false, 'payload' => $this->payload($data)]);

        return $this->noStore(response()->json($secret->full(), 201));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return $this->noStore(response()->json($this->owned($request, $id)->full()));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $secret = $this->owned($request, $id);
        $data = $this->validated($request);
        $secret->update(['type' => $data['type'], 'favorite' => $data['favorite'] ?? $secret->favorite, 'payload' => $this->payload($data)]);

        return $this->noStore(response()->json($secret->fresh()->full()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->owned($request, $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function owned(Request $request, int $id): Secret
    {
        return $request->user()->secrets()->findOrFail($id);
    }

    private function noStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store');
    }

    private function payload(array $data): array
    {
        return [
            'title' => $data['title'],
            'username' => $data['username'] ?? null,
            'secret' => $data['secret'] ?? null,
            'url' => $data['url'] ?? null,
            'notes' => $data['notes'] ?? null,
            'fields' => array_values($data['fields'] ?? []),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(config('secrets.types'))],
            'favorite' => ['sometimes', 'boolean'],
            'title' => ['required', 'string', 'max:160'],
            'username' => ['nullable', 'string', 'max:255'],
            'secret' => ['nullable', 'string', 'max:2000'],
            'url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'fields' => ['nullable', 'array', 'max:'.(int) config('secrets.max_fields')],
            'fields.*.label' => ['required', 'string', 'max:80'],
            'fields.*.value' => ['nullable', 'string', 'max:2000'],
            'fields.*.hidden' => ['sometimes', 'boolean'],
        ]);
    }
}
