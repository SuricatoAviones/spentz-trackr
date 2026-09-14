<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cortes de tarjeta: la verdad autoritativa.
 *
 * `closing_balance` es lo que dice el banco, no una suma nuestra. Entre un
 * corte y el siguiente la app PROYECTA (corte + gastos − pagos) y presenta esa
 * cifra etiquetada como estimación, nunca como saldo real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();

            $table->date('cut_date');
            $table->date('due_date');

            $table->decimal('closing_balance', 14, 2);
            $table->decimal('minimum_payment', 14, 2)->nullable();

            // Congelado al escribir, como todo importe de la app (ADR-001).
            $table->enum('currency', ['usd', 'ves', 'usdt']);
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('usd_amount', 14, 2);
            $table->decimal('usdt_amount', 14, 2);

            $table->date('paid_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            // Un solo corte por fecha y tarjeta: dos cortes el mismo día harían
            // ambiguo cuál es "el último" y la proyección saldría mal.
            $table->unique(['credit_card_id', 'cut_date']);
            $table->index(['credit_card_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_statements');
    }
};
