<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectStepController extends Controller
{
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = $this->project($request, $projectId);
        $data = $request->validate(['title' => ['required', 'string', 'max:200']]);

        $position = (int) $project->steps()->max('position') + 1;
        $project->steps()->create(['title' => $data['title'], 'position' => $position]);

        return response()->json($project->fresh('steps'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $step = $this->owned($request, $id);
        $data = $request->validate(['title' => ['sometimes', 'string', 'max:200'], 'done' => ['sometimes', 'boolean']]);

        if (array_key_exists('done', $data)) {
            $data['done_at'] = $data['done'] ? now() : null;
        }
        $step->update($data);

        return response()->json($step->project()->with('steps')->first());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $step = $this->owned($request, $id);
        $project = $step->project;
        $step->delete();

        return response()->json($project->fresh('steps'));
    }

    /** Nouvel ordre des étapes, donné en entier par le client (glisser-déposer ou flèches). */
    public function reorder(Request $request, int $projectId): JsonResponse
    {
        $project = $this->project($request, $projectId);
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);

        $ids = $project->steps()->pluck('id');
        abort_unless(collect($data['order'])->sort()->values()->all() === $ids->sort()->values()->all(), 422, 'Liste d’étapes incohérente.');

        foreach ($data['order'] as $position => $stepId) {
            ProjectStep::whereKey($stepId)->update(['position' => $position]);
        }

        return response()->json($project->fresh('steps'));
    }

    private function project(Request $request, int $projectId): Project
    {
        return $request->user()->projects()->findOrFail($projectId);
    }

    private function owned(Request $request, int $id): ProjectStep
    {
        return ProjectStep::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))->findOrFail($id);
    }
}
