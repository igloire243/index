<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LifeGoal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class LifeGoalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->list($request));
    }

    /** Ajoute au catalogue de l'utilisateur chaque entrée du référentiel qu'il n'a pas encore (ou déjà retirée). */
    public function seed(Request $request): JsonResponse
    {
        $user = $request->user();
        $have = $user->lifeGoals()->whereNotNull('source_key')->pluck('source_key')->all();

        $rows = [];
        $now = now();
        foreach (config('life_goals.catalog') as $i => $item) {
            if (in_array($item['key'], $have, true)) {
                continue;
            }
            $rows[] = [
                'user_id' => $user->id, 'source_key' => $item['key'], 'domain' => $item['domain'],
                'age_bracket' => $item['age_bracket'], 'title' => $item['title'], 'status' => 'none',
                'position' => $i, 'created_at' => $now, 'updated_at' => $now,
            ];
        }
        if ($rows !== []) {
            LifeGoal::insert($rows);
        }

        return response()->json($this->list($request));
    }

    public function store(Request $request): JsonResponse
    {
        $goal = $request->user()->lifeGoals()->create($this->validated($request));

        return response()->json($goal->fresh('project.steps'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $goal = $this->owned($request, $id);
        $data = $this->validated($request, $goal);

        $status = $data['status'] ?? $goal->status;
        if ($status === 'done' && ! array_key_exists('completed_at', $data)) {
            $data['completed_at'] = now()->toDateString();
        } elseif ($status !== 'done') {
            $data['completed_at'] = null;
        }

        $goal->update($data);

        return response()->json($goal->fresh('project.steps'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->owned($request, $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function list(Request $request): Collection
    {
        // Les étapes du projet lié viennent avec : la fiche peut afficher sa progression sans appel supplémentaire.
        return $request->user()->lifeGoals()->with('project.steps')->orderBy('position')->orderBy('id')->get();
    }

    private function owned(Request $request, int $id): LifeGoal
    {
        return $request->user()->lifeGoals()->findOrFail($id);
    }

    private function validated(Request $request, ?LifeGoal $goal = null): array
    {
        $user = $request->user();

        return $request->validate([
            'title' => [$goal ? 'sometimes' : 'required', 'string', 'max:160'],
            'domain' => [$goal ? 'sometimes' : 'required', Rule::in(config('life_goals.domains'))],
            'age_bracket' => [$goal ? 'sometimes' : 'required', Rule::in(config('life_goals.age_brackets'))],
            'status' => ['sometimes', Rule::in(LifeGoal::STATUSES)],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('user_id', $user->id)],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
