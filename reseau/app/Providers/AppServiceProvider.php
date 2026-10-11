<?php

namespace App\Providers;

use App\Services\PushSender;
use App\Services\WebPushSender;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PushSender::class, fn () => new WebPushSender(
            config('reseau.vapid.public'),
            config('reseau.vapid.private'),
            config('reseau.vapid.subject'),
        ));
    }

    public function boot(): void
    {
        //
    }
}
