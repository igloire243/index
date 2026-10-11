<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'reseau:vapid';

    protected $description = "Génère les clés VAPID pour les notifications push.";

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('Ajoute ces lignes à ton fichier .env (et garde la clé privée secrète) :');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:ton-adresse@example.com');

        return self::SUCCESS;
    }
}
