<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de instancia que un admin puede cambiar en caliente.
 *
 * No van en `.env` porque eso exige acceso al servidor y reiniciar: el
 * despliegue típico de esta app es cPanel o Docker, donde el fichero no siempre
 * está a mano. El `.env` sigue mandando el **valor por defecto**
 * (`config/features.php`); esta tabla lo sobreescribe cuando alguien lo toca
 * desde el panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
