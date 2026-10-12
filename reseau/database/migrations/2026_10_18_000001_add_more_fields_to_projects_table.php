<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('dossier_id')->nullable()->after('contact_id')->constrained()->nullOnDelete();
            $table->string('domain', 40)->nullable()->after('title'); // domaine libre (juridique, commerce…), comme celui des contacts
            // Date pour laquelle un rappel d'échéance a déjà été envoyé : se réarme tout seul si l'échéance change.
            $table->date('reminder_sent_for')->nullable()->after('target_date');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dossier_id');
            $table->dropColumn(['domain', 'reminder_sent_for']);
        });
    }
};
