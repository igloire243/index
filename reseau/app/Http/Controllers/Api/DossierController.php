<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dossier;
use App\Models\DossierFile;
use App\Services\VaultFiles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DossierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->dossiers()->withCount(['files'])->orderByDesc('updated_at');
        if ($request->filled('contact_id')) {
            $query->where('contact_id', (int) $request->input('contact_id'));
        }

        return response()->json($query->get()->map(fn (Dossier $d) => $d->only(['id', 'contact_id', 'subject', 'title', 'updated_at', 'files_count'])));
    }

    public function store(Request $request): JsonResponse
    {
        $dossier = $request->user()->dossiers()->create($this->validated($request));

        return response()->json($this->present($dossier->fresh()), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json($this->present($request->user()->dossiers()->findOrFail($id)));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $dossier = $request->user()->dossiers()->findOrFail($id);
        $dossier->update($this->validated($request));

        return response()->json($this->present($dossier->fresh()));
    }

    /** Supprime le dossier et efface aussi ses fichiers chiffrés du disque. */
    public function destroy(Request $request, int $id, VaultFiles $vault): JsonResponse
    {
        $dossier = $request->user()->dossiers()->findOrFail($id);
        // Tous les fichiers, y compris les envois inachevés (la relation `files()` ne renvoie que les fichiers prêts).
        foreach (DossierFile::where('dossier_id', $dossier->id)->get() as $file) {
            $vault->delete($file);
        }
        $dossier->delete();

        return response()->json(['ok' => true]);
    }

    private function present(Dossier $dossier): array
    {
        return $dossier->toArray() + ['files' => $dossier->files()->get()->toArray()];
    }

    private function validated(Request $request): array
    {
        $user = $request->user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:120'],
            'contact_id' => ['nullable', 'integer', Rule::exists('contacts', 'id')->where('user_id', $user->id)],
            'summary' => ['nullable', 'string', 'max:20000'],
        ]);
        if (blank($data['subject'] ?? null) && ! empty($data['contact_id'])) {
            $data['subject'] = $user->contacts()->whereKey($data['contact_id'])->value('name');
        }

        return $data;
    }
}
