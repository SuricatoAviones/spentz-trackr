<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->enum('currency', ['usd', 'ves', 'usdt']);
            $table->decimal('amount', 14, 2);
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('usd_amount', 14, 2);
            $table->decimal('usdt_amount', 14, 2);
            $table->timestamps();

            $table->index(['expense_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_items');
    }
};
