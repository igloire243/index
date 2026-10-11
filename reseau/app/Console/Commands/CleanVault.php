<?php

namespace App\Console\Commands;

use App\Models\DossierFile;
use App\Services\VaultFiles;
use Illuminate\Console\Command;

class CleanVault extends Command
{
    protected $signature = 'reseau:vault-clean';

    protected $description = 'Supprime les envois abandonnés (plus de 24 h) et les fichiers chiffrés sans fiche dans la base.';

    public function handle(VaultFiles $vault): int
    {
        $stale = 0;
        DossierFile::where('status', 'uploading')->where('updated_at', '<', now()->subDay())->each(function (DossierFile $file) use ($vault, &$stale) {
            $vault->delete($file);
            $file->delete();
            $stale++;
        });

        // Fichiers présents sur le disque sans fiche (suppression d'un utilisateur, incident) : effacés après une heure de grâce.
        $known = DossierFile::pluck('uuid')->flip();
        $orphans = 0;
        foreach ($vault->disk()->allFiles() as $path) {
            $uuid = basename($path, '.bin');
            if (! $known->has($uuid) && $vault->disk()->lastModified($path) < now()->subHour()->timestamp) {
                $vault->disk()->delete($path);
                $orphans++;
            }
        }

        $this->info("$stale envoi(s) abandonné(s) et $orphans fichier(s) orphelin(s) supprimé(s).");

        return self::SUCCESS;
    }
}
