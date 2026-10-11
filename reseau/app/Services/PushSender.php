<?php

namespace App\Services;

use App\Models\PushSubscription;

interface PushSender
{
    public const SENT = 'sent';

    public const GONE = 'gone';     // abonnement expiré : à supprimer

    public const FAILED = 'failed'; // échec temporaire

    public function enabled(): bool;

    /** @param array<string, mixed> $payload */
    public function send(PushSubscription $subscription, array $payload): string;
}
