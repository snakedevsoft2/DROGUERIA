<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes que el dueño de la droguería cambia desde la propia aplicación.
 *
 * Van en la base y no en el archivo de configuración porque en el servidor no
 * hay forma de editar un .env: la clave de los reportes tiene que poder
 * cambiarse desde el mostrador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
