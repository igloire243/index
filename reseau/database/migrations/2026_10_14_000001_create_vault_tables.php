<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 120)->nullable();   // la personne concernée (reprend le nom du contact lié)
            $table->string('title', 160);
            $table->longText('summary')->nullable();      // chiffré
            $table->timestamps();

            $table->index(['user_id', 'contact_id']);
        });

        Schema::create('dossier_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('uuid', 36)->unique();         // nom du fichier chiffré sur le disque
            $table->string('name', 255);
            $table->string('mime', 120)->nullable();      // détecté d'après le contenu
            $table->string('kind', 10)->nullable();       // image | video | audio | document
            $table->unsignedBigInteger('size');           // taille en clair
            $table->unsignedInteger('total_chunks');
            $table->json('received');                     // numéros des morceaux déjà reçus
            $table->text('encrypted_key');                // clé du fichier, elle-même chiffrée avec la clé de l'application
            $table->string('status', 10)->default('uploading'); // uploading | ready
            $table->text('caption')->nullable();          // chiffré
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_files');
        Schema::dropIfExists('dossiers');
    }
};
