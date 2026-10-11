<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->contacts()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $contact = $request->user()->contacts()->create($this->validated($request));

        return response()->json($contact, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contact = $request->user()->contacts()->findOrFail($id);
        $contact->update($this->validated($request));

        return response()->json($contact->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $request->user()->contacts()->findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        return response()->json(['deleted' => $request->user()->contacts()->delete()]);
    }

    /** Import en masse (fichier .vcf de Google Contacts ou sauvegarde .json) : ignore les doublons. */
    public function import(Request $request): JsonResponse
    {
        $request->validate(['contacts' => ['required', 'array', 'max:5000']]);

        $user = $request->user();
        $known = $user->contacts()->get(['name', 'phone'])
            ->mapWithKeys(fn ($c) => [$this->signature($c->name, $c->phone) => true])
            ->all();

        $added = 0;
        $skipped = 0;
        foreach ($request->input('contacts') as $raw) {
            $raw = is_array($raw) ? $this->legacyKeys($raw) : [];
            $validator = Validator::make($raw, $this->rules());
            if ($validator->fails()) {
                $skipped++;

                continue;
            }
            $data = $this->normalize($validator->validated());
            $sig = $this->signature($data['name'], $data['phone'] ?? null);
            if (isset($known[$sig])) {
                $skipped++;

                continue;
            }
            $known[$sig] = true;
            $user->contacts()->create($data);
            $added++;
        }

        return response()->json(['added' => $added, 'skipped' => $skipped]);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:120'],
            'domains' => ['nullable', 'array', 'max:30'],
            'domains.*' => ['nullable', 'string', 'max:40'],
            'role' => ['nullable', 'string', 'max:120'],
            'place' => ['nullable', 'string', 'max:200'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'how' => ['nullable', 'string', 'max:200'],
            'anecdote' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'favor' => ['nullable', 'in:owed,owe'],
            'last_contact' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    private function validated(Request $request): array
    {
        return $this->normalize($request->validate($this->rules()));
    }

    /** Domaines dédoublonnés (sans tenir compte de la casse ni des accents) ; lat/lng toujours ensemble. */
    private function normalize(array $data): array
    {
        $seen = [];
        $domains = [];
        foreach ($data['domains'] ?? [] as $label) {
            $label = trim((string) $label);
            $key = Str::lower(Str::ascii($label));
            if ($key !== '' && ! isset($seen[$key])) {
                $seen[$key] = true;
                $domains[] = $label;
            }
        }
        $data['domains'] = $domains;

        if (! isset($data['lat'], $data['lng'])) {
            $data['lat'] = $data['lng'] = null;
        }

        return $data;
    }

    private function signature(?string $name, ?string $phone): string
    {
        return Str::lower(Str::ascii(trim((string) $name))).'|'.preg_replace('/\D/', '', (string) $phone);
    }

    /** Accepte aussi les anciens noms de champs de la première version (sauvegarde .json). */
    private function legacyKeys(array $raw): array
    {
        if (isset($raw['last']) && ! isset($raw['last_contact'])) {
            $raw['last_contact'] = $raw['last'];
        }
        if (is_string($raw['domains'] ?? null)) {
            $raw['domains'] = preg_split('/[,;]/', $raw['domains']);
        }
        unset($raw['id'], $raw['user_id']);

        return $raw;
    }
}
