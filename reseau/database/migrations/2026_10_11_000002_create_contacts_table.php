<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->json('domains');
            $table->string('role', 120)->nullable();
            $table->string('place', 200)->nullable();
            $table->double('lat')->nullable();
            $table->double('lng')->nullable();
            $table->string('how', 200)->nullable();
            $table->text('anecdote')->nullable();
            $table->text('notes')->nullable();
            $table->string('favor', 8)->nullable();
            $table->date('last_contact')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
