<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unidades que trae la presentación del producto.
 *
 * La droguería compra por caja pero vende por tableta, así que el costo hay que
 * repartirlo: una caja de 10 a $3.500 sale a $350 la unidad. El inventario y la
 * venta se llevan siempre en unidades sueltas; este número sólo sirve para
 * calcular ese costo unitario y proponer el precio de venta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('units_per_package')->default(1)->after('presentation');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('units_per_package');
        });
    }
};
