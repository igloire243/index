<?php

/*
 * Le déploiement FTP d'un hébergement mutualisé dont le compte FTP ne voit que `htdocs` (LWS).
 *
 * `depot` : où le workflow dépose le code (dans le dossier servi par le web, faute de pouvoir écrire plus haut) ;
 * `destination` : où vit réellement l'application, HORS du web. Les deux sont vides par défaut : sans dépôt,
 * `deploiement:finaliser` ne copie rien et se contente de migrer. Voir `FinaliserLeDeploiement`.
 */
return [
    'depot' => env('DEPLOIEMENT_DEPOT'),
    'destination' => env('DEPLOIEMENT_DESTINATION'),
];
