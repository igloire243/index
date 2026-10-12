<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Objectif mensuel du Coach : nombre de contacts distincts à relancer dans le mois. Vide = pas d'objectif.
            $table->unsignedSmallInteger('network_goal_monthly')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('network_goal_monthly');
        });
    }
};
