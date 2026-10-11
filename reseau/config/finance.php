<?php

/*
 * Référentiels du module patrimoine. Les libellés sont affichés par l'interface (resources/js/lib/finance.js
 * en garde une copie pour l'affichage) ; seules les clés comptent côté serveur.
 */

return [
    'account_types' => ['cash', 'bank', 'mobile', 'savings', 'investment', 'crypto', 'tontine', 'other'],

    'asset_categories' => [
        'residence', 'locatif', 'terrain', 'vehicule', 'commerce', 'agricole', 'equipement',
        'electronique', 'or', 'mobilier', 'placement', 'crypto', 'assurance', 'autre',
    ],
];
