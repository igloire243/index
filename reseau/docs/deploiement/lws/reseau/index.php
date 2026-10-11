<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
 * HÉBERGEMENT MUTUALISÉ LWS : ce fichier remplace `public/index.php`.
 *
 * Le dossier d'un sous-domaine vit DANS `htdocs`, donc il est servi par le web. On n'y met que la partie
 * publique (ce fichier, les écrans compilés, les icônes). Le code, le `.env` et la base de données vivent
 * HORS du web, dans `home/reseau-app` : un `.env` posé sous `htdocs` serait téléchargeable par n'importe qui.
 *
 * Arborescence attendue (c'est celle du panneau LWS) :
 *     <racine du compte>/reseau-app/                   le code de l'application (vu du CRON : /home/reseau-app)
 *     <racine du compte>/htdocs/reseau.mark-cd.com/    ce dossier (public)
 * Par prudence on accepte aussi <racine du compte>/home/reseau-app (selon la façon dont le compte est présenté).
 */
$application = __DIR__.'/../../reseau-app';
if (! is_file($application.'/vendor/autoload.php') && is_file(__DIR__.'/../../home/reseau-app/vendor/autoload.php')) {
    $application = __DIR__.'/../../home/reseau-app';
}

// Mode maintenance
if (file_exists($maintenance = $application.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $application.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $application.'/bootstrap/app.php';

// Sans cette ligne Laravel chercherait `public/` sous le code : les écrans compilés, le manifeste, le service worker
// et les icônes sont ici, dans le dossier du sous-domaine.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
