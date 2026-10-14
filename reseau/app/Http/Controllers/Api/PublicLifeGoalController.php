<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LifeGoal;
use App\Models\LifeGoalShare;
use App\Models\LifeGoalShareAnswer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Accès public à un lien d'accomplissements de vie : pas de compte, pas de session. Le jeton dans l'URL est la
 * seule preuve d'accès. Contrairement aux liens de fichiers du Coffre, celui-ci n'est pas à usage unique : la
 * personne peut revenir cocher ou modifier ses réponses tant que le propriétaire n'a pas révoqué le lien.
 */
class PublicLifeGoalController extends Controller
{
    /** Le nom du propriétaire, sa liste (lecture seule), et les réponses déjà données par cette personne. */
    public function show(string $token): JsonResponse
    {
        $share = $this->find($token);
        $owner = $share->user;

        return response()->json([
            'from' => $owner->name,
            'respondent_name' => $share->respondent_name,
            'goals' => $owner->lifeGoals()->orderBy('position')->orderBy('id')
                ->get(['id', 'domain', 'age_bracket', 'title', 'status', 'target_date', 'completed_at']),
            'answers' => $share->answers,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function setName(Request $request, string $token): JsonResponse
    {
        $share = $this->find($token);
        $data = $request->validate(['respondent_name' => ['nullable', 'string', 'max:120']]);
        $share->update(['respondent_name' => $data['respondent_name'] ?? null]);

        return response()->json(['ok' => true]);
    }

    /** Enregistre (ou met à jour) la réponse de la personne pour un accomplissement du propriétaire. */
    public function answer(Request $request, string $token, int $lifeGoalId): JsonResponse
    {
        $share = $this->find($token);
        $goal = $share->user->lifeGoals()->findOrFail($lifeGoalId);

        $data = $request->validate([
            'status' => ['required', Rule::in(LifeGoal::STATUSES)],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $data['completed_at'] = $data['status'] === 'done' ? now()->toDateString() : null;

        $answer = LifeGoalShareAnswer::updateOrCreate(['share_id' => $share->id, 'life_goal_id' => $goal->id], $data);

        return response()->json($answer);
    }

    private function find(string $token): LifeGoalShare
    {
        $share = LifeGoalShare::findByToken($token);
        abort_if($share === null, 404, 'Lien introuvable.');

        return $share;
    }
}
