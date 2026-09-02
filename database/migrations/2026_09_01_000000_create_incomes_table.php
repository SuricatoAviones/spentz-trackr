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
        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->enum('currency', ['usd', 'ves', 'usdt'])->default('usd');
            $table->decimal('amount', 14, 2);
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->string('rate_provider')->nullable();
            $table->decimal('usd_amount', 14, 2);
            $table->decimal('usdt_amount', 14, 2);
            $table->string('description');
            $table->text('note')->nullable();
            $table->date('received_at');
            $table->timestamps();

            $table->index(['user_id', 'received_at']);
            $table->index(['user_id', 'category_id']);
            $table->index(['user_id', 'currency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incomes');
    }
};
