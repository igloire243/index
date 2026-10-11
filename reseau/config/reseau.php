<?php

return [
    // Notifications push (rappels) : générer les clés avec `php artisan reseau:vapid`.
    'vapid' => [
        'public' => env('VAPID_PUBLIC_KEY'),
        'private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    ],

    // Les rappels sont envoyés dans les 3 heures qui suivent l'heure prévue, puis abandonnés pour la journée.
    'reminder_window_minutes' => 180,
];
