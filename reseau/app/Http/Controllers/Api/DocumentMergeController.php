<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DossierFile;
use App\Services\VaultFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\Response;

class DocumentMergeController extends Controller
{
    public function __construct(private readonly VaultFiles $vault) {}

    /**
     * Fusionne plusieurs fichiers (images et PDF, dans l'ordre choisi) en un seul PDF, construit en mémoire et
     * jamais écrit en clair ailleurs que dans des fichiers temporaires privés, supprimés aussitôt après usage —
     * nécessaire ici parce que la bibliothèque de fusion de PDF ne sait lire qu'un chemin de fichier, pas des octets.
     */
    public function store(Request $request): Response
    {
        $user = $request->user();
        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1', 'max:30'],
            'file_ids.*' => ['integer'],
        ]);

        $files = DossierFile::where('user_id', $user->id)->where('status', 'ready')
            ->whereIn('id', $data['file_ids'])
            ->get()->sortBy(fn (DossierFile $f) => array_search($f->id, $data['file_ids']))->values();

        abort_unless($files->count() === count($data['file_ids']), 404, 'Fichier introuvable.');
        foreach ($files as $file) {
            // JPEG/PNG et PDF seulement : les autres formats d'image (webp, heic…) demanderaient une bibliothèque
            // de conversion (GD/Imagick) dont la disponibilité n'est pas garantie sur un hébergement mutualisé.
            $ok = $file->mime === 'application/pdf' || in_array($file->mime, ['image/jpeg', 'image/png'], true);
            abort_unless($ok, 422, "« {$file->name} » ne peut pas être fusionné : seuls les PDF et les images JPEG/PNG le peuvent.");
        }

        $pdf = new Fpdi;
        $pdf->SetMargins(0, 0, 0);
        $temp = [];

        try {
            foreach ($files as $file) {
                $path = $this->toTempFile($file);
                $temp[] = $path;

                if ($file->mime === 'application/pdf') {
                    $this->appendPdf($pdf, $path);
                } else {
                    $this->appendImage($pdf, $path, $file->mime);
                }
            }

            $content = $pdf->Output('S');
        } finally {
            foreach ($temp as $path) {
                @unlink($path);
            }
        }

        $name = $files->count() === 1 ? $files->first()->name : 'documents-fusionnes';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename*=UTF-8\'\''.rawurlencode(Str::limit(pathinfo($name, PATHINFO_FILENAME), 60, '')).'.pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function toTempFile(DossierFile $file): string
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        $handle = fopen($path, 'wb');
        foreach ($this->vault->readRange($file, 0, $file->size - 1) as $piece) {
            fwrite($handle, $piece);
        }
        fclose($handle);

        return $path;
    }

    private function appendPdf(Fpdi $pdf, string $path): void
    {
        $pages = $pdf->setSourceFile($path);
        for ($i = 1; $i <= $pages; $i++) {
            $id = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($id);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($id);
        }
    }

    private function appendImage(Fpdi $pdf, string $path, ?string $mime): void
    {
        [$pxW, $pxH] = getimagesize($path) ?: [0, 0];
        if (! $pxW || ! $pxH) {
            return; // image illisible : on l'ignore plutôt que de faire échouer toute la fusion
        }

        // Page A4 (mm), image mise à l'échelle pour tenir dedans en gardant ses proportions.
        [$pageW, $pageH] = $pxW >= $pxH ? [297.0, 210.0] : [210.0, 297.0];
        $ratio = min($pageW / ($pxW / 96 * 25.4), $pageH / ($pxH / 96 * 25.4));
        $w = $pxW / 96 * 25.4 * $ratio;
        $h = $pxH / 96 * 25.4 * $ratio;

        $pdf->AddPage($pageW >= $pageH ? 'L' : 'P', [$pageW, $pageH]);
        $pdf->Image($path, ($pageW - $w) / 2, ($pageH - $h) / 2, $w, $h, $this->imageType($mime));
    }

    private function imageType(?string $mime): string
    {
        return $mime === 'image/png' ? 'PNG' : 'JPG';
    }
}
