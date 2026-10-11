<?php

/*
 * Mots de passe et codes du coffre : types proposés et limites. Le contenu de chaque fiche est chiffré avec APP_KEY
 * avant d'être écrit dans la base ; seul le type, l'état « favori » et les dates restent lisibles.
 */
return [
    'types' => ['site', 'bank', 'card', 'pin', 'pattern', 'wifi', 'note', 'other'],
    'max_fields' => 20,
];
