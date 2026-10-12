<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            // Un dossier avec une catégorie est un « document personnel » (acte de naissance, diplôme…) plutôt
            // qu'un dossier libre sur une personne ou un sujet ; les deux restent le même modèle (mêmes fichiers
            // chiffrés, même partage) mais sont présentés dans des onglets distincts du Coffre.
            $table->string('category', 30)->nullable()->after('title');
            $table->text('document_number')->nullable()->after('category'); // chiffré : numéro de pièce, de passeport…
            $table->string('issued_by', 160)->nullable()->after('document_number');
            $table->date('issue_date')->nullable()->after('issued_by');
            $table->date('expiry_date')->nullable()->after('issue_date');
            $table->date('reminder_sent_for')->nullable()->after('expiry_date');

            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('dossiers', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'category']);
            $table->dropColumn(['category', 'document_number', 'issued_by', 'issue_date', 'expiry_date', 'reminder_sent_for']);
        });
    }
};
