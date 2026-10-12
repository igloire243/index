<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dossier_file_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // sha256 du jeton ; le jeton lui-même n'est jamais stocké
            $table->timestamp('expires_at');
            $table->timestamp('viewed_at')->nullable(); // posé au premier contenu servi : le lien est à usage unique
            $table->timestamps();

            $table->index(['dossier_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dossier_file_shares');
    }
};
