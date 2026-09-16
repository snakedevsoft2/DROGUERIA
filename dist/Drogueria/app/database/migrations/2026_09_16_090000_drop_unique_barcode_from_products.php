<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El código de barras deja de identificar un único producto: dos fichas
     * distintas pueden compartir el mismo código (o el mismo nombre) y el
     * nombre numerado las distingue en la lista y en el punto de venta.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['barcode']);
            $table->unique('barcode');
        });
    }
};
