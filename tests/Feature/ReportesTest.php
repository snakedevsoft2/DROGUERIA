<?php

namespace Tests\Feature;

use App\Livewire\ReportsComponent;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function venta(float $total, string $metodo = 'cash'): Sale
    {
        return Sale::create([
            'invoice_number' => 'FAC-'.str_pad((string) (Sale::count() + 1), 8, '0', STR_PAD_LEFT),
            'subtotal' => $total, 'tax' => 0, 'discount' => 0, 'total' => $total,
            'paid_amount' => $total, 'change_amount' => 0, 'payment_method' => $metodo,
        ]);
    }

    public function test_la_pagina_de_reportes_responde(): void
    {
        $this->get(route('reports'))->assertOk();
    }

    public function test_el_resumen_suma_las_ventas_del_rango(): void
    {
        $this->venta(10000);
        $this->venta(15000, 'card');

        $resumen = Livewire::test(ReportsComponent::class)->instance()->summary();

        $this->assertSame(2, $resumen['transactions']);
        $this->assertSame(25000.0, $resumen['revenue']);
        $this->assertSame(12500.0, $resumen['average']);
        $this->assertArrayNotHasKey('tax', $resumen, 'El resumen ya no reporta IVA.');
    }

    public function test_el_resumen_no_divide_por_cero_sin_ventas(): void
    {
        $resumen = Livewire::test(ReportsComponent::class)->instance()->summary();

        $this->assertSame(0, $resumen['transactions']);
        $this->assertSame(0.0, $resumen['average']);
    }

    public function test_las_ventas_no_se_pueden_eliminar_desde_la_aplicacion(): void
    {
        $this->venta(10000);

        $metodos = get_class_methods(ReportsComponent::class);

        foreach ($metodos as $metodo) {
            $this->assertStringNotContainsStringIgnoringCase(
                'delete',
                $metodo,
                "Reportes no debe exponer ninguna acción de borrado ({$metodo})."
            );
        }

        $this->assertSame(1, Sale::count());
    }

    public function test_el_detalle_muestra_el_producto_aunque_se_haya_eliminado(): void
    {
        $sale = $this->venta(5000);

        $product = Product::create([
            'barcode' => '7709999999999',
            'name' => 'Producto retirado',
            'cost_price' => 1000,
            'selling_price' => 5000,
            'min_stock' => 1,
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 5000,
            'subtotal' => 5000,
        ]);

        $product->delete();

        Livewire::test(ReportsComponent::class)
            ->call('toggleSale', $sale->id)
            ->assertSee('Producto retirado');
    }
}
