<?php

use Illuminate\Support\Facades\Schedule;

// Hôte classique : * * * * * cd /chemin/reseau && php artisan schedule:run >> /dev/null 2>&1
// LWS (sans ligne de commande) : tâche CRON chaque minute sur « php /home/reseau-app/cron.php ».
Schedule::command('reseau:send-reminders')->everyMinute()->withoutOverlapping();

Schedule::command('reseau:vault-clean')->dailyAt('03:30')->withoutOverlapping();

// Termine un déploiement FTP arrivé dans le dépôt de passage : recopie, caches, migrations (voir docs/deploiement/lws).
Schedule::command('deploiement:finaliser')
    ->everyMinute()
    ->withoutOverlapping(10); // 10 min : le défaut (24 h) bloquerait toute la journée après une exécution tuée
