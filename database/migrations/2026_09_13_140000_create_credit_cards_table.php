<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * La tarjeta POSEE su origen de pago: al crearla se crea un
             * PaymentSource y los gastos se siguen registrando contra él, sin
             * tocar el formulario de gasto ni migrar nada. Si se borra el
             * origen, la tarjeta pierde el vínculo pero no sus cortes.
             */
            $table->foreignId('payment_source_id')->nullable()->unique()->constrained()->nullOnDelete();

            $table->string('bank');
            $table->string('name');
            $table->string('last_four', 4)->nullable();
            $table->enum('brand', ['visa', 'mastercard', 'amex', 'other'])->default('other');

            // Una tarjeta opera en UNA moneda: su límite, sus cortes y sus
            // pagos van todos en ella.
            $table->enum('currency', ['usd', 'ves', 'usdt'])->default('ves');
            $table->decimal('credit_limit', 14, 2);

            // Día del mes. Se recorta al último día real del mes al calcular
            // fechas: un corte el 31 no existe en febrero.
            $table->unsignedTinyInteger('cut_day');
            $table->unsignedTinyInteger('due_day');

            // Informativas: la app estima, no replica el cálculo del banco.
            $table->decimal('annual_interest_rate', 6, 2)->nullable();
            $table->decimal('minimum_payment_rate', 5, 2)->nullable();

            $table->string('icon')->default('credit-card');
            $table->string('color')->default('#8B5CF6');
            $table->boolean('active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_cards');
    }
};
