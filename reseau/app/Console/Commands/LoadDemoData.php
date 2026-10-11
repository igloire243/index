<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class LoadDemoData extends Command
{
    protected $signature = 'reseau:demo {--force : Autorise l\'exécution en production}';

    protected $description = 'Crée (ou recrée) un compte de démonstration rempli de données fictives.';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Refusé en production : ajoute --force si tu es sûr de vouloir créer un compte de démonstration.');

            return self::FAILURE;
        }

        $this->call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Compte de démonstration prêt :');
        $this->line('  e-mail       : '.DemoSeeder::EMAIL);
        $this->line('  mot de passe : '.DemoSeeder::PASSWORD);

        return self::SUCCESS;
    }
}
