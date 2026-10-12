<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * Termine un déploiement FTP : recopie le code, vide les caches et migre la base, SANS aucune adresse web.
 *
 * POURQUOI PAS UN SCRIPT PUBLIC : l'hébergeur mutualisé n'a pas de SSH, et un fichier public qui lancerait artisan à la
 * demande est supprimé par l'antivirus de l'hébergeur, sans rien dire. Le CRON de chaque minute existe déjà pour le
 * planificateur : c'est lui qui constate qu'un nouveau déploiement est arrivé. Aucune porte ouverte sur Internet,
 * donc aucun jeton à protéger.
 *
 * Le workflow dépose `VERSION` (identifiant du commit + « faut-il migrer ») avec le code. Tant que ce fichier est
 * identique à celui déjà traité, la tâche ne fait rien : elle coûte une lecture.
 *
 * LE DÉPÔT : le compte FTP de LWS a pour racine `htdocs` et ne peut rien écrire plus haut. Le workflow dépose donc le
 * code dans un dépôt de passage (`DEPLOIEMENT_DEPOT`), et c'est ICI, avec les droits du CRON, qu'il est recopié dans
 * l'application (`DEPLOIEMENT_DESTINATION`, hors du web).
 */
class FinaliserLeDeploiement extends Command
{
    protected $signature = 'deploiement:finaliser';

    protected $description = 'Après un envoi FTP : recopie le code, vide les caches et migre la base, une seule fois par déploiement';

    public function handle(): int
    {
        $destination = rtrim((string) (config('deploiement.destination') ?: base_path()), '/');
        $traitee = $destination.'/storage/app/version-traitee';
        $arrivee = $destination.'/VERSION';

        if (! $this->recopierLeDepot($destination)) {
            return self::FAILURE;
        }

        if (! is_file($arrivee)) {
            return self::SUCCESS;
        }

        $contenu = trim((string) file_get_contents($arrivee));
        if ($contenu === '' || (is_file($traitee) && trim((string) file_get_contents($traitee)) === $contenu)) {
            return self::SUCCESS;
        }

        // « migrer=0 » : le déploiement a demandé de ne pas toucher à la base (case décochée dans GitHub).
        $migrer = ! str_contains($contenu, 'migrer=0');

        // Les étapes tournent dans un NOUVEAU processus : ce code vient peut-être d'être remplacé sur le disque, et
        // celui-ci a chargé l'ancien en mémoire. Un `migrate` lancé d'ici ne verrait pas les nouvelles classes.
        $this->lancer('optimize:clear', $destination);

        // Si la migration échoue (verrou SQLite, erreur de schéma…), on ne marque RIEN comme traité : la minute
        // suivante retentera tout depuis le début plutôt que de laisser l'application tourner sur un schéma à moitié
        // à jour (c'est ce qui s'est produit avant ce correctif : une migration en échec passait inaperçue).
        if ($migrer && ! $this->lancer('migrate --force', $destination)) {
            $this->error('La migration a échoué : rien n’est marqué traité, nouvel essai à la prochaine minute.');

            return self::FAILURE;
        }

        $this->lancer('optimize', $destination);

        // Écrit en DERNIER : une migration qui échoue doit être retentée à la minute suivante, pas oubliée.
        File::ensureDirectoryExists(dirname($traitee));
        File::put($traitee, $contenu);
        $this->info('Déploiement finalisé : '.$contenu);

        return self::SUCCESS;
    }

    /**
     * Recopie le dépôt sur l'application quand il porte une version que l'application n'a pas encore.
     *
     * `cp -a` plutôt qu'une copie écrite en PHP : des milliers de fichiers (vendor), des droits et des dates à
     * garder, et un outil que l'hébergeur fournit. Le dépôt ne contient jamais `.env`, `storage`, `bootstrap/cache`
     * ni la base SQLite (le workflow les exclut de l'envoi) : les recopier ne peut rien écraser de ce qui appartient
     * au serveur.
     */
    private function recopierLeDepot(string $destination): bool
    {
        $depot = rtrim((string) config('deploiement.depot'), '/');
        if ($depot === '' || ! is_file($depot.'/VERSION')) {
            return true;
        }

        $arrivee = trim((string) file_get_contents($depot.'/VERSION'));
        $enPlace = is_file($destination.'/VERSION') ? trim((string) file_get_contents($destination.'/VERSION')) : '';
        if ($arrivee === '' || $arrivee === $enPlace) {
            return true;
        }

        $this->line('Nouveau code dans le dépôt : '.$arrivee);
        File::ensureDirectoryExists($destination);
        $copie = new Process(['cp', '-a', $depot.'/.', $destination.'/']);
        $copie->setTimeout(600)->run();

        if (! $copie->isSuccessful()) {
            // Rien n'est marqué traité : la copie sera retentée à la minute suivante.
            $this->error('La copie du dépôt a échoué : '.trim($copie->getErrorOutput()));

            return false;
        }

        // Ces dossiers appartiennent au serveur : le workflow ne les envoie pas, il faut qu'ils existent.
        foreach (['storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dossier) {
            File::ensureDirectoryExists($destination.'/'.$dossier);
        }

        return true;
    }

    private function lancer(string $commande, string $dossier): bool
    {
        // Pas d'application à cet endroit (dossier de test, destination mal réglée) : rien à lancer, et surtout pas
        // un `artisan` cherché ailleurs.
        if (! is_file($dossier.'/artisan')) {
            return true;
        }

        $processus = new Process([PHP_BINARY, 'artisan', ...explode(' ', $commande)], $dossier);
        $processus->setTimeout(600)->run();
        $this->line(trim($processus->getOutput().$processus->getErrorOutput()));

        return $processus->isSuccessful();
    }
}
