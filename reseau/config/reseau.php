<?php

return [
    // Notifications push (rappels) : générer les clés avec `php artisan reseau:vapid`.
    'vapid' => [
        'public' => env('VAPID_PUBLIC_KEY'),
        'private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:admin@example.com'),
    ],

    // Face ID / empreinte (clés d'accès WebAuthn). L'identifiant du site est le nom de domaine d'accès ; à fixer
    // seulement si le site est servi sous plusieurs sous-domaines (ex. WEBAUTHN_RP_ID=ton-domaine.com).
    'webauthn' => [
        'rp_id' => env('WEBAUTHN_RP_ID'),
    ],

    // Les rappels sont envoyés dans les 3 heures qui suivent l'heure prévue, puis abandonnés pour la journée.
    'reminder_window_minutes' => 180,
];
