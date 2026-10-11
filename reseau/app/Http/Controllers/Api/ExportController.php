<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Secret;
use App\Services\VaultExport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function __construct(private readonly VaultExport $export) {}

    public function store(Request $request): Response|JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string', 'min:10', 'max:255']]);

        if ($this->export->estimatedBytes($request->user()->id) > VaultExport::MAX_BYTES) {
            return response()->json(['message' => 'Le Coffre est trop volumineux pour cet export : fais une sauvegarde par FTP à la place.'], 422);
        }

        $dossiers = $request->user()->dossiers()->with('files')->get();
        $secrets = Secret::where('user_id', $request->user()->id)->get();

        $content = $this->export->build($dossiers, $secrets, $data['password']);
        $name = 'reseau-export-'.now()->format('Y-m-d').'.enc';

        return response($content, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Content-Length' => (string) strlen($content),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
