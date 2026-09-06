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
        Schema::create('savings_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('target_amount', 14, 2);
            $table->enum('currency', ['usd', 'ves', 'usdt'])->default('usd');
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('target_usd_amount', 14, 2);
            $table->string('icon')->default('piggy-bank');
            $table->string('color')->default('#10B981');
            $table->date('deadline')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('savings_goals');
    }
};
