<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * PREMIER DÉPLOIEMENT SUR UN HÉBERGEMENT SANS LIGNE DE COMMANDE (LWS) : à lancer une seule fois, par une tâche CRON
 * à usage unique, avec le chemin ABSOLU de destination en argument (il appartient au serveur, pas au dépôt — rien
 * ne permet de le déduire de l'emplacement de ce script par une simple remontée de dossiers : sur certains comptes,
 * le dépôt sous `htdocs` et la destination hors du web ne partagent aucun ancêtre commun qu'on pourrait calculer) :
 *     php /home/htdocs/home/reseau-app/amorcage.php /home/reseau-app
 *
 * Tout se passe DANS CE SEUL PROCESSUS PHP : la copie des fichiers est faite en PHP pur et les commandes artisan
 * sont appelées directement sur le noyau Laravel, jamais en relançant `php` en sous-processus. Certains
 * hébergements mutualisés désactivent `exec`/`shell_exec`/`proc_open` ; un script qui en dépend échoue alors sans
 * rien écrire nulle part de visible.
 *
 * Chaque étape est journalisée dans `amorcage.log`, À CÔTÉ de ce fichier (donc dans le dépôt, visible par FTP ou le
 * gestionnaire de fichiers) : le panneau CRON ne montre pas toujours la sortie d'une tâche.
 *
 * Attend un fichier `.env` posé par FTP dans le dépôt de passage (à côté de ce script) OU déjà présent dans
 * l'application. Il le recopie dans l'application puis SUPPRIME celui du dépôt (qui est sous htdocs). Idempotent :
 * le relancer ne casse rien.
 *
 * Optionnel dans ce .env, pour créer le premier utilisateur (les trois lignes sont retirées du .env ensuite) :
 *     AMORCAGE_EMAIL=...   AMORCAGE_NOM=...   AMORCAGE_MOT_DE_PASSE=...  (10 caractères minimum)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$depot = __DIR__;
$journal = $depot.'/amorcage.log';

function ecrit(string $journal, string $message): void
{
    file_put_contents($journal, '['.date('Y-m-d H:i:s')."] $message\n", FILE_APPEND | LOCK_EX);
}
$dire = fn (string $m) => ecrit($journal, $m);

$dire('--- Amorçage lancé ---');

if (! is_file($depot.'/VERSION')) {
    $dire('Dépôt vide : lance d’abord le workflow « Déployer sur LWS » dans GitHub.');
    exit(1);
}

// Pas de valeur par défaut devinée : sur certains comptes, remonter depuis __DIR__ ne retombe jamais sur la
// destination (elle n'a pas de dossier ancêtre commun avec le dépôt). Le chemin doit être donné explicitement.
$destination = isset($argv[1]) ? rtrim($argv[1], '/') : '';
if ($destination === '') {
    $dire('Chemin de destination manquant : relance avec « php amorcage.php /home/reseau-app » (ton chemin réel).');
    exit(1);
}

$dire("Dépôt : $depot");
$dire("Application : $destination");

/** Copie récursive en PHP pur : aucune dépendance à exec()/shell_exec(), parfois désactivés sur l'hébergement. */
function copierDossier(string $source, string $cible): void
{
    if (! is_dir($cible) && ! mkdir($cible, 0755, true) && ! is_dir($cible)) {
        throw new RuntimeException("impossible de créer $cible");
    }
    foreach (new FilesystemIterator($source, FilesystemIterator::SKIP_DOTS) as $item) {
        $dest = $cible.'/'.$item->getFilename();
        if ($item->isDir()) {
            copierDossier($item->getPathname(), $dest);

            continue;
        }
        if (! copy($item->getPathname(), $dest)) {
            throw new RuntimeException('échec de la copie de '.$item->getPathname());
        }
        @chmod($dest, fileperms($item->getPathname()));
    }
}

// 1. Recopie du code
try {
    copierDossier($depot, $destination);
    $dire('Code recopié (copie PHP, sans sous-processus).');
} catch (Throwable $e) {
    $dire('Copie échouée : '.$e->getMessage());
    exit(1);
}

// 2. Le .env ne doit pas rester sous htdocs
$envEnPlace = is_file($destination.'/.env');
if (is_file($depot.'/.env')) {
    if (! $envEnPlace && ! copy($depot.'/.env', $destination.'/.env')) {
        $dire('Impossible de recopier le .env.');
        exit(1);
    }
    @chmod($destination.'/.env', 0600);
    @unlink($depot.'/.env');
    $dire('.env recopié dans l’application et supprimé du dépôt.');
} elseif (! $envEnPlace) {
    $dire('Aucun .env : dépose-le par FTP dans '.$depot.'/ puis relance cette tâche.');
    exit(1);
}

// 3. Dossiers et base qui appartiennent au serveur
foreach (['storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'database'] as $dossier) {
    if (! is_dir("$destination/$dossier")) {
        @mkdir("$destination/$dossier", 0775, true);
    }
}
if (! is_file($destination.'/database/database.sqlite')) {
    touch($destination.'/database/database.sqlite');
}

// 4. Migrations et caches : EN PROCESSUS, sur le noyau artisan de l'application fraîchement copiée — jamais par un
// sous-processus `php artisan ...` (certains hébergements désactivent exec()/proc_open()).
try {
    require $destination.'/vendor/autoload.php';
    $app = require $destination.'/bootstrap/app.php';
} catch (Throwable $e) {
    $dire('Démarrage de l’application impossible : '.$e->getMessage());
    exit(1);
}
/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);

$artisan = function (string $commande, array $parametres = []) use ($kernel, $journal, $dire): bool {
    $dire('> php artisan '.$commande);
    $sortie = new BufferedOutput;
    try {
        $code = $kernel->call($commande, $parametres, $sortie);
    } catch (Throwable $e) {
        ecrit($journal, 'Erreur : '.$e->getMessage());

        return false;
    }
    $texte = trim($sortie->fetch());
    if ($texte !== '') {
        ecrit($journal, $texte);
    }

    return $code === 0;
};

$artisan('optimize:clear');
if (! $artisan('migrate', ['--force' => true])) {
    $dire('La migration a échoué : corrige le .env (APP_KEY, DB_DATABASE…) puis relance.');
    exit(1);
}

// 5. Premier utilisateur (optionnel)
$env = (string) file_get_contents($destination.'/.env');
// Le \r en trim() : un .env aux fins de ligne Windows (\r\n) laissait ce caractère invisible à la fin de chaque
// valeur, qui faisait alors échouer la validation (ex. « adresse@example.com\r » rejetée comme e-mail invalide).
$valeur = fn (string $cle) => preg_match('/^'.$cle.'=(.*)$/m', $env, $m) ? trim($m[1], " \t\r\n\"'") : '';
$email = $valeur('AMORCAGE_EMAIL');
$motDePasse = $valeur('AMORCAGE_MOT_DE_PASSE');
if ($email !== '' && $motDePasse !== '') {
    $ok = $artisan('reseau:user', ['email' => $email, '--name' => $valeur('AMORCAGE_NOM') ?: $email, '--password' => $motDePasse]);
    if ($ok) {
        file_put_contents($destination.'/.env', preg_replace('/^AMORCAGE_[A-Z_]*=.*\R?/m', '', $env));
        $dire('Utilisateur créé ; les lignes AMORCAGE_* ont été retirées du .env.');
    } else {
        $dire('La création de l’utilisateur a échoué (voir ci-dessus).');
    }
}

$artisan('optimize');

@mkdir($destination.'/storage/app', 0775, true);
file_put_contents($destination.'/storage/app/version-traitee', trim((string) file_get_contents($depot.'/VERSION')));

$dire('--- Amorçage terminé. Active maintenant la tâche CRON de chaque minute (cron.php ou le répartiteur) ---');
