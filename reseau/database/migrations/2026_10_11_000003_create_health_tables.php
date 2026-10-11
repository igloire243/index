<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('program_key', 40)->nullable();
            $table->string('name', 120);
            $table->string('category', 20);
            $table->json('days');                       // jours ISO : 1 = lundi … 7 = dimanche
            $table->string('time', 5)->nullable();      // HH:MM, heure du rappel
            $table->boolean('reminder')->default(false);
            $table->text('notes')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->date('last_reminded_on')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('habit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('habit_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['habit_id', 'date']);
            $table->index('date');
        });

        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('slot', 12);                 // breakfast | lunch | snack | dinner
            $table->string('description', 200);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });

        Schema::create('measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('sleep_hours', 3, 1)->nullable();
            $table->unsignedTinyInteger('energy')->nullable();   // 1 à 5
            $table->unsignedTinyInteger('mood')->nullable();     // 1 à 5
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('health_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('goal', 10);                 // lose | gain | maintain
            $table->decimal('target_weight_kg', 5, 2)->nullable();
            $table->date('target_date')->nullable();
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['push_subscriptions', 'health_goals', 'measurements', 'meals', 'habit_logs', 'habits'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
