<?php

namespace App\Console\Commands;

use App\Models\DossierFile;
use App\Models\DossierFileShare;
use App\Services\VaultFiles;
use Illuminate\Console\Command;

class CleanVault extends Command
{
    protected $signature = 'reseau:vault-clean';

    protected $description = 'Supprime les envois abandonnés (plus de 24 h), les fichiers chiffrés sans fiche et les vieux liens de partage.';

    public function handle(VaultFiles $vault): int
    {
        // Liens expirés depuis longtemps ou déjà utilisés : ne servent plus à rien, autant libérer la place.
        $shares = DossierFileShare::where('expires_at', '<', now()->subDays(30))->orWhere('viewed_at', '<', now()->subDays(30))->delete();

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

        $this->info("$stale envoi(s) abandonné(s), $orphans fichier(s) orphelin(s) et $shares lien(s) de partage ancien(s) supprimés.");

        return self::SUCCESS;
    }
}
