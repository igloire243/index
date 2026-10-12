<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CvController extends Controller
{
    /** Tout le CV en un appel : compétences, langues, expériences (avec leurs réalisations). */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'skills' => $user->skills()->orderBy('type')->orderBy('name')->get(),
            'languages' => $user->languages()->orderBy('name')->get(),
            'experiences' => $user->experiences()->with('achievements')->orderByDesc('current')->orderByDesc('start_date')->get(),
            'achievements' => $user->achievements()->whereNull('experience_id')->orderByDesc('date')->get(),
        ]);
    }

    /** Export imprimable du CV, mis en forme, en PDF. */
    public function export(Request $request): Response
    {
        $user = $request->user();
        $data = [
            'user' => $user,
            'skills' => $user->skills()->orderBy('type')->orderBy('name')->get(),
            'languages' => $user->languages()->orderBy('name')->get(),
            'experiences' => $user->experiences()->with('achievements')->orderByDesc('current')->orderByDesc('start_date')->get(),
            'achievements' => $user->achievements()->whereNull('experience_id')->orderByDesc('date')->get(),
        ];

        return Pdf::loadView('pdf.cv', $data)->download('cv.pdf');
    }
}
