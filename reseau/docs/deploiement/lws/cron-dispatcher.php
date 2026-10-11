<?php

/**
 * CRON UNIQUE POUR TOUT LE COMPTE LWS : à déposer une seule fois, par le gestionnaire de fichiers LWS, à la
 * racine du compte, À CÔTÉ des dossiers `<projet>-app` (jamais dedans) — par exemple `/home/cron-dispatcher.php`,
 * sibling de `/home/reseau-app`, `/home/mgj-app`, etc.
 *
 * Certains forfaits LWS limitent le compte à UNE seule tâche CRON. Ce fichier sert de point d'entrée UNIQUE :
 * la tâche CRON du panneau pointe sur lui, et lui seul, et c'est lui qui appelle ensuite le `cron.php` de
 * CHAQUE application déployée selon ce mode d'emploi, l'une après l'autre.
 *
 * Chaque application est lancée dans son PROPRE processus PHP (jamais dans celui-ci) : deux applications
 * Laravel ne peuvent pas partager un même processus sans se marcher dessus (classes déjà déclarées,
 * configuration et connexions à la base de l'une qui contaminent l'autre).
 *
 * Ajouter une application à l'avenir ne demande RIEN ici : dès que son `<projet>-app/cron.php` existe à côté
 * de celui-ci, il est pris en compte tout seul au prochain passage. En retirer une : supprimer son dossier
 * (ou son `cron.php`) suffit, rien à changer ici non plus.
 */
if (PHP_SAPI !== 'cli') {
    // Appelé par le web, il ne ferait que donner un moyen de déclencher ces tâches à volonté.
    http_response_code(404);
    exit;
}

foreach (glob(__DIR__.'/*/cron.php') as $cron) {
    $app = basename(dirname($cron));
    $debut = microtime(true);
    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($cron).' 2>&1', $sortie, $code);
    $duree = round((microtime(true) - $debut) * 1000);
    // Une application en échec ne doit jamais empêcher les autres de tourner.
    if ($code !== 0) {
        fwrite(STDERR, "[$app] code=$code (${duree} ms) : ".implode(' | ', $sortie)."\n");
    }
    $sortie = [];
}
