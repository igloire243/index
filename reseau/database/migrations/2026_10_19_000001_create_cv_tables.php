<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // competence | connaissance | talent
            $table->string('name', 120);
            $table->unsignedTinyInteger('level')->nullable(); // 1 à 5, facultatif
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });

        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('level', 20); // notions | intermediaire | courant | natif
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('organization', 160)->nullable();
            $table->string('location', 120)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('current')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('experience_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 160);
            $table->date('date')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('experiences');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('skills');
    }
};
