<?php

return [
    // Durée pendant laquelle le coffre reste ouvert sans activité.
    'unlock_minutes' => (int) env('VAULT_UNLOCK_MINUTES', 15),

    // Taille maximale d'un fichier et espace total par utilisateur (en Mo).
    'max_file_mb' => (int) env('VAULT_MAX_FILE_MB', 500),
    'quota_mb' => (int) env('VAULT_QUOTA_MB', 4096),

    // Types autorisés, reconnus d'après le CONTENU du fichier (pas d'après son nom ni ce que déclare le navigateur).
    // Le HTML, le SVG et les scripts n'y figurent pas : ils pourraient s'exécuter à l'ouverture.
    'mimes' => [
        'image' => ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/heic', 'image/heif', 'image/avif'],
        'video' => ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-matroska', 'video/3gpp', 'video/ogg'],
        'audio' => ['audio/mpeg', 'audio/mp4', 'audio/x-m4a', 'audio/aac', 'audio/wav', 'audio/x-wav', 'audio/ogg', 'audio/webm', 'audio/flac', 'audio/x-flac', 'application/ogg'],
        'document' => [
            'application/pdf', 'text/plain', 'application/msword', 'application/vnd.ms-excel', 'application/zip',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ],
    ],

    // Contenants audio/vidéo ambigus : un enregistrement vocal .webm ou .m4a est reconnu comme « vidéo ».
    'audio_containers' => ['video/webm', 'video/mp4', 'video/ogg', 'video/3gpp', 'application/ogg'],
];
