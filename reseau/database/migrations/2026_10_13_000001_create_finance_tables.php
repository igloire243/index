<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fonds : un compte = un endroit où de l'argent est conservé (banque, mobile money, espèces, tontine…).
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('type', 16);
            $table->string('institution', 120)->nullable(); // organisme ou opérateur
            $table->string('location', 120)->nullable();    // lieu où c'est conservé
            $table->string('source', 120)->nullable();      // d'où vient l'argent
            $table->string('currency', 6);
            $table->decimal('balance', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('account_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 16, 2);               // positif = entrée, négatif = sortie
            $table->string('note', 200)->nullable();
            $table->string('transfer_id', 36)->nullable();  // relie les deux côtés d'un virement
            $table->timestamps();

            $table->index(['account_id', 'date']);
            $table->index('transfer_id');
        });

        // Créances (on me doit) et dettes (je dois) dans la même table.
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 12);                // owed_to_me | i_owe
            $table->string('counterparty', 120);
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 16, 2);
            $table->string('currency', 6);
            $table->string('reason', 200)->nullable();
            $table->date('opened_on');
            $table->date('due_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'direction']);
        });

        Schema::create('debt_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('debt_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 16, 2);
            $table->foreignId('account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('movement_id')->nullable()->constrained('account_movements')->nullOnDelete();
            $table->string('note', 200)->nullable();
            $table->timestamps();
        });

        // Biens possédés, ou souhaités (« envie ») pour planifier une acquisition.
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('category', 16);
            $table->string('status', 8)->default('owned'); // owned | wanted
            $table->decimal('value', 16, 2)->nullable();    // valeur estimée (ou prix visé si « envie »)
            $table->string('currency', 6);
            $table->date('acquired_on')->nullable();
            $table->decimal('purchase_price', 16, 2)->nullable();
            $table->string('location', 120)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'category']);
        });

        Schema::create('finance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('base_currency', 6)->default('USD');
            $table->json('rates');                           // 1 unité de la devise de référence = N unités de chaque devise
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['finance_settings', 'assets', 'debt_payments', 'debts', 'account_movements', 'accounts'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
