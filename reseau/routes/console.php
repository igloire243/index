<?php

use Illuminate\Support\Facades\Schedule;

// Hôte : * * * * * cd /chemin/reseau && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('reseau:send-reminders')->everyMinute()->withoutOverlapping();
