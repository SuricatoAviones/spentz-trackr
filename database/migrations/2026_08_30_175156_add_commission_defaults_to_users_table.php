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
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('pago_movil_commission', 14, 2)->nullable()->default(14.00)->after('default_display_currency');
            $table->decimal('transferencia_commission', 14, 2)->nullable()->default(8.00)->after('pago_movil_commission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pago_movil_commission', 'transferencia_commission']);
        });
    }
};
