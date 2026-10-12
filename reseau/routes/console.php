<?php

use Illuminate\Support\Facades\Schedule;

// Hôte classique : * * * * * cd /chemin/reseau && php artisan schedule:run >> /dev/null 2>&1
// LWS (sans ligne de commande) : tâche CRON chaque minute sur « php /home/reseau-app/cron.php ».
Schedule::command('reseau:send-reminders')->everyMinute()->withoutOverlapping();

Schedule::command('reseau:vault-clean')->dailyAt('03:30')->withoutOverlapping();

// Termine un déploiement FTP arrivé dans le dépôt de passage : recopie, caches, migrations (voir docs/deploiement/lws).
// La sortie est gardée dans un fichier : sans CRON ni SSH, c'est le seul moyen de voir pourquoi une finalisation
// échoue (lisible par FTP ou le gestionnaire de fichiers de l'hébergeur).
Schedule::command('deploiement:finaliser')
    ->everyMinute()
    ->withoutOverlapping(10) // 10 min : le défaut (24 h) bloquerait toute la journée après une exécution tuée
    ->appendOutputTo(storage_path('logs/deploiement.log'));
