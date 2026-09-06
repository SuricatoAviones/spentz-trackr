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
        Schema::create('recurring_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->enum('currency', ['usd', 'ves', 'usdt'])->default('usd');
            $table->decimal('exchange_rate', 14, 4)->nullable();
            $table->decimal('usd_amount', 14, 2);
            $table->decimal('usdt_amount', 14, 2);
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])->default('monthly');
            $table->date('next_due_date');
            $table->date('last_paid_at')->nullable();
            $table->string('icon')->default('wallet');
            $table->string('color')->default('#10B981');
            $table->boolean('active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'active']);
            $table->index(['user_id', 'next_due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_payments');
    }
};
