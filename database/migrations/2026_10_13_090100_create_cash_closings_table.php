<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada cierre es una foto de la caja en ese momento: las cifras se
        // copian y no se recalculan, para que un cierre entregado no cambie
        // después aunque se borre una venta o se corrija un costo.
        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->timestamp('opened_at');   // desde cuándo cuenta: justo después del cierre anterior
            $table->timestamp('closed_at');
            $table->unsignedInteger('sales_count')->default(0);
            $table->unsignedInteger('units_sold')->default(0);
            $table->decimal('revenue', 12, 2)->default(0);       // lo vendido, ya con descuentos
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('cost', 12, 2)->default(0);          // lo que costó lo vendido
            $table->decimal('profit', 12, 2)->default(0);
            $table->decimal('cash_sales', 12, 2)->default(0);
            $table->decimal('card_sales', 12, 2)->default(0);
            $table->decimal('transfer_sales', 12, 2)->default(0);
            $table->decimal('opening_cash', 12, 2)->default(0);  // base con la que abrió la caja
            $table->decimal('expected_cash', 12, 2)->default(0); // base + ventas en efectivo
            $table->decimal('counted_cash', 12, 2)->nullable();  // lo que se contó; vacío si no se contó
            $table->decimal('difference', 12, 2)->nullable();    // contado - esperado
            $table->string('cashier')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('closed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closings');
    }
};
