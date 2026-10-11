<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushSender implements PushSender
{
    private ?WebPush $client = null;

    public function __construct(private readonly ?string $publicKey, private readonly ?string $privateKey, private readonly string $subject) {}

    public function enabled(): bool
    {
        return filled($this->publicKey) && filled($this->privateKey);
    }

    public function send(PushSubscription $subscription, array $payload): string
    {
        if (! $this->enabled()) {
            return self::FAILED;
        }

        try {
            $this->client ??= new WebPush(['VAPID' => [
                'subject' => $this->subject,
                'publicKey' => $this->publicKey,
                'privateKey' => $this->privateKey,
            ]]);

            $report = $this->client->sendOneNotification(
                Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]),
                json_encode($payload, JSON_UNESCAPED_UNICODE),
                ['TTL' => 3600],
            );
        } catch (Throwable $e) {
            report($e);

            return self::FAILED;
        }

        if ($report->isSuccess()) {
            return self::SENT;
        }

        return $report->isSubscriptionExpired() ? self::GONE : self::FAILED;
    }
}
