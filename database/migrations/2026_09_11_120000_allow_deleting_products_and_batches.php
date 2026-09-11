<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite borrar productos y lotes sin arrastrar el historial de ventas.
 *
 * Hasta ahora sale_details apuntaba a products y batches con la llave foránea
 * por defecto (RESTRICT): borrar un producto ya vendido daba error de base de
 * datos, así que el inventario lo desactivaba en lugar de eliminarlo. Ahora la
 * referencia queda en NULL y el nombre del producto viaja copiado en el propio
 * detalle, de modo que las facturas viejas se siguen leyendo completas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            // Copia del nombre al momento de la venta: lo que se imprime en el
            // comprobante aunque el producto ya no exista en el catálogo.
            $table->string('product_name')->nullable()->after('product_id');
        });

        // Rellena el histórico existente antes de soltar la llave foránea.
        DB::table('sale_details')
            ->whereNull('product_name')
            ->update([
                'product_name' => DB::raw('(select name from products where products.id = sale_details.product_id)'),
            ]);

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['batch_id']);
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable()->change();

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('batch_id')->references('id')->on('batches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Sin producto no se puede volver a RESTRICT: las filas huérfanas
        // romperían la llave foránea.
        DB::table('sale_details')->whereNull('product_id')->delete();

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropForeign(['batch_id']);
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->unsignedBigInteger('product_id')->nullable(false)->change();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('batch_id')->references('id')->on('batches');
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
