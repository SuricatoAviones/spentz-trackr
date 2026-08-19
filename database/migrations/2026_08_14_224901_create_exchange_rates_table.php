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
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('source', ['api', 'manual', 'seed'])->default('api');
            $table->decimal('rate', 14, 4);
            $table->string('provider', 30)->default('dolarapi');
            $table->date('rate_date');
            $table->timestamps();

            $table->unique(['user_id', 'rate_date', 'source', 'provider']);
            $table->index('rate_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
