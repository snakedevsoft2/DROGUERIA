<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            // Lo que costaba cada unidad al momento de venderla. Sin esta copia
            // la ganancia de un día ya cerrado cambiaría cada vez que el
            // proveedor sube el precio y se actualiza la ficha del producto.
            $table->decimal('unit_cost', 10, 2)->nullable()->after('unit_price');
        });

        // Las ventas anteriores no lo guardaban: se les pone el costo que hoy
        // tiene el producto, que es lo más cercano que queda. El "* 1.0" evita
        // la división entera de SQLite (1000 / 3 daría 333 y no 333,33).
        DB::statement('
            UPDATE sale_details
            SET unit_cost = (
                SELECT ROUND(products.cost_price * 1.0 / CASE WHEN products.units_per_package > 0 THEN products.units_per_package ELSE 1 END, 2)
                FROM products
                WHERE products.id = sale_details.product_id
            )
            WHERE unit_cost IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
