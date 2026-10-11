<?php

/**
 * PREMIER DÉPLOIEMENT SUR UN HÉBERGEMENT SANS LIGNE DE COMMANDE (LWS) : À LANCER UNE SEULE FOIS, par une tâche CRON
 * à usage unique (`php /home/htdocs/home/reseau-app/amorcage.php`).
 *
 * Avant le tout premier envoi, `deploiement:finaliser` n'existe pas encore sur le serveur : ce script fait à la main
 * ce qu'il fera ensuite chaque minute, et prépare ce qui n'est jamais envoyé par FTP (dossiers du serveur, base
 * SQLite, premier utilisateur). Il ne s'exécute qu'en ligne de commande. Idempotent : le relancer ne casse rien.
 *
 * Il attend un fichier `.env` posé par FTP dans le dépôt de passage (à côté de ce script) OU déjà présent dans
 * l'application. Il le recopie dans l'application puis SUPPRIME celui du dépôt (qui est sous htdocs).
 *
 * Optionnel dans ce .env, pour créer le premier utilisateur (les trois lignes sont retirées du .env ensuite) :
 *     AMORCAGE_EMAIL=...   AMORCAGE_NOM=...   AMORCAGE_MOT_DE_PASSE=...  (10 caractères minimum)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$depot = __DIR__;
$destination = rtrim($argv[1] ?? dirname(__DIR__, 3).'/reseau-app', '/');
$dire = fn (string $m) => print $m."\n";

if (! is_file($depot.'/VERSION')) {
    $dire('Dépôt vide : lance d’abord le workflow « Déployer sur LWS » dans GitHub.');
    exit(1);
}

$dire("Dépôt : $depot");
$dire("Application : $destination");

// 1. Recopie du code
if (! is_dir($destination) && ! mkdir($destination, 0755, true)) {
    $dire('Impossible de créer le dossier de l’application.');
    exit(1);
}
exec('cp -a '.escapeshellarg($depot.'/.').' '.escapeshellarg($destination.'/').' 2>&1', $sortie, $code);
if ($code !== 0) {
    $dire('Copie échouée : '.implode("\n", $sortie));
    exit(1);
}
$dire('Code recopié.');

// 2. Le .env ne doit pas rester sous htdocs
$envEnPlace = is_file($destination.'/.env');
if (is_file($depot.'/.env')) {
    if (! $envEnPlace && ! copy($depot.'/.env', $destination.'/.env')) {
        $dire('Impossible de recopier le .env.');
        exit(1);
    }
    chmod($destination.'/.env', 0600);
    unlink($depot.'/.env');
    $dire('.env recopié dans l’application et supprimé du dépôt.');
} elseif (! $envEnPlace) {
    $dire('Aucun .env : dépose-le par FTP dans '.$depot.'/ puis relance cette tâche.');
    exit(1);
}

// 3. Dossiers et base qui appartiennent au serveur
foreach (['storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'database'] as $dossier) {
    if (! is_dir("$destination/$dossier")) {
        mkdir("$destination/$dossier", 0775, true);
    }
}
if (! is_file($destination.'/database/database.sqlite')) {
    touch($destination.'/database/database.sqlite');
}

// 4. Migrations et caches, dans un processus neuf
$artisan = function (string $args) use ($destination, $dire): bool {
    $dire('> php artisan '.$args);
    exec('cd '.escapeshellarg($destination).' && '.escapeshellarg(PHP_BINARY).' artisan '.$args.' 2>&1', $sortie, $code);
    $dire(implode("\n", $sortie));

    return $code === 0;
};
$artisan('optimize:clear');
if (! $artisan('migrate --force')) {
    $dire('La migration a échoué : corrige le .env (APP_KEY, DB_DATABASE…) puis relance.');
    exit(1);
}

// 5. Premier utilisateur (optionnel)
$env = (string) file_get_contents($destination.'/.env');
$valeur = fn (string $cle) => preg_match('/^'.$cle.'=(.*)$/m', $env, $m) ? trim($m[1], " \t\"'") : '';
$email = $valeur('AMORCAGE_EMAIL');
$motDePasse = $valeur('AMORCAGE_MOT_DE_PASSE');
if ($email !== '' && $motDePasse !== '') {
    $ok = $artisan('reseau:user '.escapeshellarg($email).' --name='.escapeshellarg($valeur('AMORCAGE_NOM') ?: $email).' --password='.escapeshellarg($motDePasse));
    if ($ok) {
        file_put_contents($destination.'/.env', preg_replace('/^AMORCAGE_[A-Z_]*=.*\R?/m', '', $env));
        $dire('Utilisateur créé ; les lignes AMORCAGE_* ont été retirées du .env.');
    }
}

$artisan('optimize');

// Les prochains déploiements passeront par `deploiement:finaliser` (CRON chaque minute) : cette version est déjà en place.
@mkdir($destination.'/storage/app', 0775, true);
file_put_contents($destination.'/storage/app/version-traitee', trim((string) file_get_contents($depot.'/VERSION')));

$dire('Amorçage terminé. Active maintenant la tâche CRON de chaque minute : php '.$destination.'/cron.php');
