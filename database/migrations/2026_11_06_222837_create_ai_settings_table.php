<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedor de IA de cada usuario.
 *
 * La clave va cifrada con APP_KEY (cast `encrypted` del modelo) y por eso es
 * `text`: el texto cifrado es mucho más largo que la clave. Es nullable porque
 * Ollama o un endpoint propio pueden no pedirla, y porque el backup la omite a
 * propósito: tras restaurar, cada usuario vuelve a pegar la suya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('model', 120)->nullable();
            $table->text('api_key')->nullable();
            $table->string('base_url', 255)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_settings');
    }
};
