<?php

/*
 * Programmes santé prêts à l'emploi. « Adopter » un programme crée simplement les habitudes
 * correspondantes dans le planning de l'utilisateur, qu'il peut ensuite modifier librement.
 *
 * Ce sont des habitudes générales de bon sens (hydratation, sommeil, activité douce, régularité
 * des repas…), jamais des prescriptions médicales ni des objectifs de calories.
 *
 * Jours : ISO, 1 = lundi … 7 = dimanche.
 */

$all = [1, 2, 3, 4, 5, 6, 7];

return [
    'reprise-douce' => [
        'name' => 'Reprise en douceur',
        'goal' => 'maintain',
        'summary' => 'Retrouver de l\'énergie par petites étapes : régularité, hydratation, mouvement doux et repos.',
        'habits' => [
            ['name' => 'Boire de l\'eau régulièrement (environ 1,5 L)', 'category' => 'hydratation', 'days' => $all, 'time' => '10:00', 'reminder' => true],
            ['name' => 'Prendre 3 repas à heures régulières', 'category' => 'nutrition', 'days' => $all, 'time' => '13:00', 'reminder' => false],
            ['name' => 'Marche tranquille de 20 minutes', 'category' => 'activite', 'days' => [1, 2, 3, 4, 5, 6], 'time' => '17:30', 'reminder' => true],
            ['name' => 'Étirements doux (10 minutes)', 'category' => 'activite', 'days' => $all, 'time' => '08:00', 'reminder' => false],
            ['name' => 'Coucher à heure fixe', 'category' => 'sommeil', 'days' => $all, 'time' => '22:30', 'reminder' => true],
            ['name' => 'Respiration calme (5 minutes)', 'category' => 'bien-etre', 'days' => $all, 'time' => '21:30', 'reminder' => false],
            ['name' => 'Me peser et noter mon état', 'category' => 'suivi', 'days' => [7], 'time' => '08:00', 'reminder' => true,
                'notes' => 'Toujours dans les mêmes conditions : le matin, à jeun, après être passé aux toilettes.'],
        ],
    ],

    'poids-progressif' => [
        'name' => 'Perdre du poids progressivement',
        'goal' => 'lose',
        'summary' => 'Des habitudes simples et durables, sans régime extrême. Un rythme prudent est d\'environ 0,25 à 0,5 kg par semaine.',
        'habits' => [
            ['name' => 'Boire de l\'eau avant chaque repas', 'category' => 'hydratation', 'days' => $all, 'time' => '12:30', 'reminder' => true],
            ['name' => 'Des légumes à au moins deux repas', 'category' => 'nutrition', 'days' => $all, 'time' => '19:00', 'reminder' => false],
            ['name' => 'Pas de boissons sucrées', 'category' => 'nutrition', 'days' => $all, 'time' => null, 'reminder' => false],
            ['name' => 'Marche active de 30 minutes', 'category' => 'activite', 'days' => [1, 2, 3, 4, 5], 'time' => '17:30', 'reminder' => true],
            ['name' => 'Dernier repas 2 à 3 heures avant le coucher', 'category' => 'nutrition', 'days' => $all, 'time' => '19:30', 'reminder' => false],
            ['name' => 'Dormir au moins 7 heures', 'category' => 'sommeil', 'days' => $all, 'time' => '22:30', 'reminder' => true],
            ['name' => 'Me peser et noter mon tour de taille', 'category' => 'suivi', 'days' => [7], 'time' => '08:00', 'reminder' => true,
                'notes' => 'Une fois par semaine suffit : le poids varie naturellement d\'un jour à l\'autre.'],
        ],
    ],

    'prise-poids-saine' => [
        'name' => 'Reprendre du poids sainement',
        'goal' => 'gain',
        'summary' => 'Manger régulièrement, assez et varié, et rebâtir de la force en douceur.',
        'habits' => [
            ['name' => 'Petit-déjeuner complet', 'category' => 'nutrition', 'days' => $all, 'time' => '08:00', 'reminder' => true],
            ['name' => 'Collation de l\'après-midi (fruits, oléagineux, laitage)', 'category' => 'nutrition', 'days' => $all, 'time' => '16:00', 'reminder' => true],
            ['name' => 'Une source de protéines à chaque repas', 'category' => 'nutrition', 'days' => $all, 'time' => '13:00', 'reminder' => false],
            ['name' => 'Renforcement musculaire léger (20 minutes)', 'category' => 'activite', 'days' => [1, 3, 5], 'time' => '18:00', 'reminder' => true],
            ['name' => 'Boire de l\'eau dans la journée', 'category' => 'hydratation', 'days' => $all, 'time' => '10:00', 'reminder' => false],
            ['name' => 'Dormir au moins 8 heures', 'category' => 'sommeil', 'days' => $all, 'time' => '22:00', 'reminder' => true],
            ['name' => 'Me peser et noter mon état', 'category' => 'suivi', 'days' => [7], 'time' => '08:00', 'reminder' => true],
        ],
    ],

    'sommeil' => [
        'name' => 'Sommeil réparateur',
        'goal' => 'maintain',
        'summary' => 'Un rythme régulier et une bonne soirée pour mieux récupérer.',
        'habits' => [
            ['name' => 'Lumière du jour dès le matin (10 minutes dehors)', 'category' => 'sommeil', 'days' => $all, 'time' => '08:00', 'reminder' => false],
            ['name' => 'Pas de caféine après 15 h', 'category' => 'sommeil', 'days' => $all, 'time' => '15:00', 'reminder' => true],
            ['name' => 'Écrans éteints une heure avant le coucher', 'category' => 'sommeil', 'days' => $all, 'time' => '21:30', 'reminder' => true],
            ['name' => 'Chambre aérée, sombre et calme', 'category' => 'sommeil', 'days' => $all, 'time' => '21:45', 'reminder' => false],
            ['name' => 'Coucher à heure fixe', 'category' => 'sommeil', 'days' => $all, 'time' => '22:30', 'reminder' => true],
        ],
    ],

    'soins-du-corps' => [
        'name' => 'Soins du corps',
        'goal' => 'maintain',
        'summary' => 'Une routine d\'hygiène et de soins simple, matin et soir.',
        'habits' => [
            ['name' => 'Brossage des dents (matin)', 'category' => 'soins', 'days' => $all, 'time' => '07:30', 'reminder' => false],
            ['name' => 'Protection solaire sur le visage', 'category' => 'soins', 'days' => $all, 'time' => '07:45', 'reminder' => false],
            ['name' => 'Brossage des dents (soir)', 'category' => 'soins', 'days' => $all, 'time' => '21:30', 'reminder' => true],
            ['name' => 'Nettoyer le visage et hydrater la peau', 'category' => 'soins', 'days' => $all, 'time' => '21:40', 'reminder' => false],
            ['name' => 'Soin des mains et des pieds', 'category' => 'soins', 'days' => [7], 'time' => '18:00', 'reminder' => true],
            ['name' => 'Étirements de la nuque et des épaules', 'category' => 'bien-etre', 'days' => [1, 2, 3, 4, 5], 'time' => '15:30', 'reminder' => false],
        ],
    ],
];
