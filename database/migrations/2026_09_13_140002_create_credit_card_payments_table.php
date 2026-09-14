<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonos a la tarjeta.
 *
 * Tabla propia y NO un `expense` a propósito: pagar la tarjeta no es gastar,
 * es mover dinero de tu bolsillo a tu deuda. Si el pago se registrara como
 * gasto se contaría dos veces —una en la compra, otra en el pago— y todos los
 * informes mentirían. Por eso nada de esta tabla entra en los totales de gasto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('credit_card_id')->constrained()->cascadeOnDelete();

            // Un abono puede ir contra un corte concreto o ser un adelanto.
            $table->foreignId('credit_card_statement_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 14, 2);
            $table->enum('currency', ['usd', 'ves', 'usdt']);
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('usd_amount', 14, 2);
            $table->decimal('usdt_amount', 14, 2);

            $table->date('paid_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['credit_card_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_payments');
    }
};
