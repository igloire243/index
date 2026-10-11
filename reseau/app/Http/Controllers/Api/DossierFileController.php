<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DossierFile;
use App\Services\VaultCrypto;
use App\Services\VaultFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DossierFileController extends Controller
{
    public function __construct(private readonly VaultFiles $vault) {}

    /** Première étape d'un envoi : réserve le fichier ; le contenu arrive ensuite morceau par morceau. */
    public function init(Request $request, int $dossierId): JsonResponse
    {
        $user = $request->user();
        $dossier = $user->dossiers()->findOrFail($dossierId);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.((int) config('vault.max_file_mb') * 1_048_576)],
            'mime' => ['nullable', 'string', 'max:120'],
        ]);

        $used = (int) DossierFile::where('user_id', $user->id)->sum('size');
        abort_if($used + $data['size'] > (int) config('vault.quota_mb') * 1_048_576, 422, 'Espace du coffre insuffisant.');

        $key = VaultCrypto::newKey();
        $file = DossierFile::create([
            'dossier_id' => $dossier->id,
            'user_id' => $user->id,
            'uuid' => (string) Str::uuid(),
            'name' => $this->cleanName($data['name']),
            'mime' => $data['mime'] ?? null, // provisoire : remplacé par le type réel détecté à l'arrivée du 1er morceau
            'size' => $data['size'],
            'total_chunks' => VaultCrypto::totalChunks($data['size']),
            'received' => [],
            'encrypted_key' => $this->vault->encryptedKey($key),
        ]);

        return response()->json(['id' => $file->id, 'chunk_size' => VaultCrypto::CHUNK, 'total_chunks' => $file->total_chunks], 201);
    }

    /** Reçoit le morceau $index (octets bruts), le chiffre et l'écrit à sa place. Renvoyer un morceau est sans danger. */
    public function chunk(Request $request, int $id, int $index): JsonResponse
    {
        $file = $this->owned($request, $id);
        abort_unless($file->status === 'uploading', 409, 'Ce fichier est déjà complet.');
        abort_if($index < 0 || $index >= $file->total_chunks, 422, 'Morceau inattendu.');

        $body = $request->getContent();
        abort_if(strlen($body) !== VaultCrypto::plainLength($file->size, $index), 422, 'Taille du morceau incorrecte.');

        if ($index === 0) {
            $kind = $this->detect($file, $body);
            if ($kind === null) {
                $this->discard($file);

                return response()->json(['message' => 'Ce type de fichier n’est pas autorisé dans le coffre.'], 422);
            }
        }

        $this->vault->writeChunk($file, $index, $body);
        $received = array_values(array_unique([...$file->received, $index]));
        $file->update(['received' => $received]);

        return response()->json(['received' => count($received), 'total' => $file->total_chunks]);
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $file = $this->owned($request, $id);
        if ($file->status === 'ready') {
            return response()->json($file);
        }
        abort_if(count($file->received) !== $file->total_chunks, 422, 'Il manque des morceaux : renvoie le fichier.');
        abort_if($file->kind === null, 422, 'Type de fichier inconnu.');
        abort_if($this->vault->diskSize($file) !== VaultCrypto::cipherSize($file->size), 422, 'Fichier incomplet : renvoie-le.');

        $file->update(['status' => 'ready']);
        $file->dossier()->touch();

        return response()->json($file->fresh());
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $file = $this->owned($request, $id);
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'caption' => ['nullable', 'string', 'max:2000']]);
        if (isset($data['name'])) {
            $data['name'] = $this->cleanName($data['name']);
        }
        $file->update($data);

        return response()->json($file->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->discard($this->owned($request, $id));

        return response()->json(['ok' => true]);
    }

    /** Contenu déchiffré à la volée, avec prise en charge des plages (lecture vidéo/audio, reprise de téléchargement). */
    public function content(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $file = $this->owned($request, $id);
        abort_unless($file->status === 'ready', 404);
        $size = $file->size;

        $start = 0;
        $end = $size - 1;
        $status = 200;
        if ($header = $request->header('Range')) {
            if (! preg_match('/^bytes=(\d*)-(\d*)$/', trim($header), $m) || ($m[1] === '' && $m[2] === '')) {
                return response()->json(['message' => 'Plage invalide.'], 416, ['Content-Range' => "bytes */$size"]);
            }
            if ($m[1] === '') {                       // « derniers N octets »
                $start = max($size - (int) $m[2], 0);
            } else {
                $start = (int) $m[1];
                $end = $m[2] === '' ? $end : min((int) $m[2], $end);
            }
            if ($start > $end || $start >= $size) {
                return response()->json(['message' => 'Plage non satisfaisante.'], 416, ['Content-Range' => "bytes */$size"]);
            }
            $status = 206;
        }

        $inline = in_array($file->kind, ['image', 'video', 'audio'], true);
        $headers = [
            'Content-Type' => $inline ? $file->mime : 'application/octet-stream',
            'Content-Length' => (string) ($end - $start + 1),
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename*=UTF-8\'\''.rawurlencode($file->name),
            // Le contenu ne doit jamais pouvoir exécuter de script ni être mis en cache.
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src 'self' data:; media-src 'self'",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
        if ($status === 206) {
            $headers['Content-Range'] = "bytes $start-$end/$size";
        }

        return response()->stream(function () use ($file, $start, $end) {
            foreach ($this->vault->readRange($file, $start, $end) as $piece) {
                echo $piece;
                flush();
            }
        }, $status, $headers);
    }

    private function owned(Request $request, int $id): DossierFile
    {
        return DossierFile::where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function discard(DossierFile $file): void
    {
        $this->vault->delete($file);
        $file->delete();
    }

    /** Déduit le type réel d'après le contenu et le compare à la liste autorisée. Renvoie null si refusé. */
    private function detect(DossierFile $file, string $firstChunk): ?string
    {
        $sniffed = (new \finfo(FILEINFO_MIME_TYPE))->buffer($firstChunk) ?: '';
        foreach (config('vault.mimes') as $kind => $mimes) {
            if (! in_array($sniffed, $mimes, true)) {
                continue;
            }
            $declared = (string) $file->mime;
            // Un enregistrement vocal (webm, m4a, ogg…) est détecté comme conteneur vidéo : on garde ce que déclare l'application.
            if (in_array($sniffed, config('vault.audio_containers'), true) && Str::startsWith($declared, 'audio/') && in_array($declared, config('vault.mimes.audio'), true)) {
                $file->update(['mime' => $declared, 'kind' => 'audio']);

                return 'audio';
            }
            $file->update(['mime' => $sniffed, 'kind' => $kind]);

            return $kind;
        }

        return null;
    }

    private function cleanName(string $name): string
    {
        $name = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '_', $name) ?? '');

        return $name === '' ? 'fichier' : Str::limit($name, 200, '');
    }
}
