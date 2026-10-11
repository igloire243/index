<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\PushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    public function key(PushSender $sender): JsonResponse
    {
        return response()->json(['public_key' => $sender->enabled() ? config('reseau.vapid.public') : null]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        // L'endpoint appartient à un seul compte : on le rattache à l'utilisateur courant.
        PushSubscription::where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        $request->user()->pushSubscriptions()->create([
            'endpoint' => $data['endpoint'],
            'endpoint_hash' => hash('sha256', $data['endpoint']),
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }

    public function test(Request $request, PushSender $sender): JsonResponse
    {
        abort_unless($sender->enabled(), 503, 'Notifications push non configurées sur le serveur.');

        $sent = 0;
        foreach ($request->user()->pushSubscriptions as $subscription) {
            $result = $sender->send($subscription, ['title' => 'Réseau', 'body' => 'Les rappels fonctionnent sur cet appareil.', 'url' => '/sante', 'tag' => 'test']);
            if ($result === PushSender::GONE) {
                $subscription->delete();
            }
            $sent += $result === PushSender::SENT ? 1 : 0;
        }

        return response()->json(['sent' => $sent]);
    }
}
