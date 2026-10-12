<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->projects()->with('steps')->orderByRaw("status = 'done', status = 'abandoned'")->orderByDesc('updated_at');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $project = $request->user()->projects()->create($this->validated($request));

        return response()->json($project->fresh('steps'), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->owned($request, $id)->load('steps'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $project = $this->owned($request, $id);
        $data = $this->validated($request, $project);

        if (($data['status'] ?? $project->status) === 'done' && $project->status !== 'done') {
            $data['completed_at'] = now();
        } elseif (($data['status'] ?? $project->status) !== 'done') {
            $data['completed_at'] = null;
        }

        $project->update($data);

        return response()->json($project->fresh('steps'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->owned($request, $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function owned(Request $request, int $id): Project
    {
        return $request->user()->projects()->findOrFail($id);
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        $user = $request->user();

        return $request->validate([
            'title' => [$project ? 'sometimes' : 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['sometimes', Rule::in(Project::STATUSES)],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('user_id', $user->id)],
            'target_date' => ['nullable', 'date'],
        ]);
    }
}
