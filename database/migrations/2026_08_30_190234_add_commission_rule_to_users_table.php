<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pago_movil_commission', 'transferencia_commission']);
            $table->decimal('min_commission', 14, 2)->nullable()->default(14.00);
            $table->decimal('commission_rate', 14, 2)->nullable()->default(0.30);
        });

        DB::table('expenses')
            ->whereNotNull('payment_method')
            ->where('commission', '>', 0)
            ->update(['amount' => DB::raw('amount - commission')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('expenses')
            ->whereNotNull('payment_method')
            ->where('commission', '>', 0)
            ->update(['amount' => DB::raw('amount + commission')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['min_commission', 'commission_rate']);
            $table->decimal('pago_movil_commission', 14, 2)->nullable()->default(14.00);
            $table->decimal('transferencia_commission', 14, 2)->nullable()->default(8.00);
        });
    }
};
