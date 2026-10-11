<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DossierFileShare;
use App\Services\VaultFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Accès public à un lien de partage : pas de session, pas de verrou du coffre. Le jeton dans l'URL est la seule
 * preuve d'accès. Volontairement simple : pas de plages (Range) comme pour le contenu normal du coffre — la
 * vidéo ou l'audio se télécharge en entier avant lecture, pour garder le lien à usage unique sans ambiguïté sur
 * ce qui « consomme » le lien.
 */
class PublicShareController extends Controller
{
    public function __construct(private readonly VaultFiles $vault) {}

    /** Informations générales, affichées avant que la personne choisisse d'ouvrir le document. Ne consomme pas le lien. */
    public function show(string $token): JsonResponse
    {
        $share = $this->find($token);
        $file = $share->dossierFile;

        return response()->json([
            'name' => $file->name,
            'kind' => $file->kind,
            'size' => $file->size,
            'from' => $share->user->name,
            'expires_at' => $share->expires_at,
        ])->header('Cache-Control', 'private, no-store');
    }

    /** Le contenu déchiffré, une seule fois : le lien est marqué utilisé dès que la réponse part. */
    public function content(Request $request, string $token): StreamedResponse|JsonResponse
    {
        $share = $this->find($token);
        $file = $share->dossierFile;

        $share->update(['viewed_at' => now()]);

        $inline = in_array($file->kind, ['image', 'video', 'audio'], true);
        $headers = [
            'Content-Type' => $inline ? $file->mime : 'application/octet-stream',
            'Content-Length' => (string) $file->size,
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename*=UTF-8\'\''.rawurlencode($file->name),
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; media-src 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        return response()->stream(function () use ($file) {
            foreach ($this->vault->readRange($file, 0, $file->size - 1) as $piece) {
                echo $piece;
                flush();
            }
        }, 200, $headers);
    }

    private function find(string $token): DossierFileShare
    {
        $share = DossierFileShare::findByToken($token);
        abort_if($share === null, 404, 'Lien introuvable.');
        abort_if($share->expired(), 410, 'Ce lien a expiré.');
        abort_if($share->used(), 410, 'Ce lien a déjà été utilisé.');
        abort_unless($share->dossierFile->status === 'ready', 404);

        return $share;
    }
}
