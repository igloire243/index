<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('life_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Lien optionnel vers un projet en cours (ex. « Ouvrir un commerce ») : affiche sa progression ici
            // sans dupliquer le suivi. Jamais obligatoire : on peut cocher « fait » sans être passé par un projet.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_key', 60)->nullable(); // clé du catalogue prédéfini, si l'entrée en vient
            $table->string('domain', 30);
            $table->string('age_bracket', 20);
            $table->string('title', 160);
            $table->string('status', 10)->default('none'); // none | planned | done
            $table->date('target_date')->nullable();
            $table->date('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'age_bracket']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('life_goal_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('label', 120)->nullable(); // ex. « Envoyé à Paul », pour s'y retrouver
            $table->string('respondent_name', 120)->nullable(); // donné librement par la personne, facultatif
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('life_goal_share_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_id')->constrained('life_goal_shares')->cascadeOnDelete();
            $table->foreignId('life_goal_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10)->default('none'); // none | planned | done
            $table->date('target_date')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['share_id', 'life_goal_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('life_goal_share_answers');
        Schema::dropIfExists('life_goal_shares');
        Schema::dropIfExists('life_goals');
    }
};
