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
        Schema::create('savings_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('savings_goal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('income_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->enum('currency', ['usd', 'ves', 'usdt'])->default('usd');
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('usd_amount', 14, 2);
            $table->decimal('usdt_amount', 14, 2);
            $table->date('contributed_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['savings_goal_id', 'contributed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_contributions');
    }
};
