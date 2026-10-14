<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LifeGoalShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifeGoalShareController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->lifeGoalShares()->withCount('answers')->orderByDesc('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['label' => ['nullable', 'string', 'max:120']]);
        [$share, $token] = LifeGoalShare::issue($request->user(), $data['label'] ?? null);

        return response()->json(['id' => $share->id, 'token' => $token, 'label' => $share->label], 201);
    }

    /** Les réponses d'une personne à qui ce lien a été envoyé. */
    public function show(Request $request, int $id): JsonResponse
    {
        $share = $this->owned($request, $id);

        return response()->json([
            'id' => $share->id, 'label' => $share->label, 'respondent_name' => $share->respondent_name,
            'answers' => $share->answers()->with('lifeGoal:id,title,domain,age_bracket')->get(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->owned($request, $id)->delete();

        return response()->json(['ok' => true]);
    }

    private function owned(Request $request, int $id): LifeGoalShare
    {
        return $request->user()->lifeGoalShares()->findOrFail($id);
    }
}
