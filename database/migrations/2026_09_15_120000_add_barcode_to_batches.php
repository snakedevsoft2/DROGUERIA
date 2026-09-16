<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Código de barras propio del lote.
 *
 * El mismo medicamento llega a veces con códigos distintos según el laboratorio
 * o el empaque, y el mostrador tiene que poder escanear cualquiera de ellos sin
 * partir el producto en dos fichas. El código del producto sigue siendo el
 * principal; este es opcional y sólo se llena cuando el lote trae uno distinto.
 *
 * No lleva índice único: dos lotes del mismo producto pueden compartir código.
 * Que un código no apunte a dos productos distintos lo cuida el formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->after('batch_number');
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
