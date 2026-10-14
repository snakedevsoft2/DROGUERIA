<?php

namespace Tests\Feature;

use App\Livewire\CashClosingComponent;
use App\Livewire\PosComponent;
use App\Models\Batch;
use App\Models\CashClosing;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Services\SalesSummary;
use App\Support\AdminPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CierreDeCajaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Un miércoles a media tarde: la semana y el mes ya llevan días.
        $this->travelTo(Carbon::parse('2026-10-14 15:00:00'));
    }

    protected function producto(float $costo = 1000, float $precio = 2500, int $unidades = 1): Product
    {
        $product = Product::create([
            'barcode' => '770'.fake()->unique()->numerify('##########'),
            'name' => 'Acetaminofén 500 mg',
            'presentation' => 'Caja x 10',
            'units_per_package' => $unidades,
            'cost_price' => $costo,
            'selling_price' => $precio,
            'min_stock' => 0,
            'is_active' => true,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-'.fake()->numerify('####'),
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 100,
            'is_active' => true,
        ]);

        return $product;
    }

    /** Venta registrada directamente, con la fecha que se necesite. */
    protected function venta(Product $product, int $cantidad, string $cuando = 'now', string $metodo = 'cash', float $descuento = 0): Sale
    {
        $at = Carbon::parse($cuando);
        $subtotal = $cantidad * (float) $product->selling_price;

        $sale = Sale::create([
            'invoice_number' => 'FAC-'.str_pad((string) (Sale::count() + 1), 8, '0', STR_PAD_LEFT),
            'subtotal' => $subtotal,
            'tax' => 0,
            'discount' => $descuento,
            'total' => $subtotal - $descuento,
            'paid_amount' => $subtotal - $descuento,
            'change_amount' => 0,
            'payment_method' => $metodo,
        ]);
        $sale->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $cantidad,
            'unit_price' => $product->selling_price,
            'unit_cost' => $product->unit_cost,
            'subtotal' => $subtotal,
        ]);

        return $sale;
    }

    public function test_la_pagina_responde_y_esta_en_el_menu(): void
    {
        $this->get(route('cash-closing'))
            ->assertOk()
            ->assertSee('Cierre de caja')
            ->assertSee(route('cash-closing'), false);
    }

    public function test_la_venta_guarda_el_costo_por_unidad(): void
    {
        // Caja x 10 que cuesta $10.000: cada tableta cuesta $1.000.
        $product = $this->producto(costo: 10000, precio: 2500, unidades: 10);

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 3)
            ->set('paidAmount', 7500)
            ->call('completeSale');

        $this->assertEquals(1000, SaleDetail::first()->unit_cost);
    }

    public function test_la_ganancia_es_lo_vendido_menos_el_costo(): void
    {
        $product = $this->producto(costo: 1000, precio: 2500);

        $this->venta($product, 4);                        // 10.000 vendidos, 4.000 de costo
        $this->venta($product, 2, descuento: 1000);       // 4.000 vendidos, 2.000 de costo

        $hoy = SalesSummary::today()->totals();

        $this->assertSame(2, $hoy['sales_count']);
        $this->assertSame(6, $hoy['units']);
        $this->assertSame(14000.0, $hoy['revenue']);
        $this->assertSame(6000.0, $hoy['cost']);
        $this->assertSame(8000.0, $hoy['profit']);
        $this->assertSame(1000.0, $hoy['discount']);
    }

    public function test_la_ganancia_vieja_no_cambia_si_despues_sube_el_costo(): void
    {
        $product = $this->producto(costo: 1000, precio: 2500);
        $this->venta($product, 2);

        $product->update(['cost_price' => 2000]);

        $this->assertSame(3000.0, SalesSummary::today()->totals()['profit']);
    }

    public function test_muestra_lo_que_va_del_dia_la_semana_y_el_mes(): void
    {
        $product = $this->producto(costo: 1000, precio: 2500);

        $this->venta($product, 1);                         // hoy
        $this->venta($product, 1, '2026-10-12 10:00');     // lunes: esta semana
        $this->venta($product, 1, '2026-10-02 10:00');     // este mes
        $this->venta($product, 1, '2026-09-30 10:00');     // mes pasado: no cuenta

        $progreso = Livewire::test(CashClosingComponent::class)->instance()->progress();

        $this->assertSame(1500.0, $progreso['Hoy'][1]['profit']);
        $this->assertSame(3000.0, $progreso['Esta semana'][1]['profit']);
        $this->assertSame(4500.0, $progreso['Este mes'][1]['profit']);
    }

    public function test_cerrar_la_caja_guarda_lo_vendido_y_el_arqueo(): void
    {
        $product = $this->producto(costo: 1000, precio: 2500);
        $this->venta($product, 4, '-2 hours');               // 10.000 en efectivo
        $this->venta($product, 2, '-1 hour', 'card');        // 5.000 con tarjeta

        Livewire::test(CashClosingComponent::class)
            ->set('opening_cash', 50000)
            ->set('counted_cash', 59000)
            ->set('cashier', 'Ana')
            ->call('closeRegister')
            ->assertHasNoErrors();

        $cierre = CashClosing::sole();

        $this->assertSame(2, $cierre->sales_count);
        $this->assertSame(15000.0, $cierre->revenue);
        $this->assertSame(6000.0, $cierre->cost);
        $this->assertSame(9000.0, $cierre->profit);
        $this->assertSame(10000.0, $cierre->cash_sales);
        $this->assertSame(5000.0, $cierre->card_sales);
        $this->assertSame(60000.0, $cierre->expected_cash);
        $this->assertSame(-1000.0, $cierre->difference, 'Faltan mil pesos.');
        $this->assertSame('Ana', $cierre->cashier);
    }

    public function test_sin_contar_el_efectivo_no_hay_diferencia(): void
    {
        $this->venta($this->producto(), 1, '-1 hour');

        Livewire::test(CashClosingComponent::class)->call('closeRegister');

        $this->assertNull(CashClosing::sole()->difference);
    }

    public function test_la_caja_nueva_arranca_despues_del_cierre(): void
    {
        $product = $this->producto(costo: 1000, precio: 2500);
        $this->venta($product, 1, '-1 hour');

        Livewire::test(CashClosingComponent::class)->call('closeRegister');

        $this->travel(10)->minutes();
        $this->venta($product, 3);

        $caja = Livewire::test(CashClosingComponent::class)->instance()->register();

        $this->assertSame(1, $caja['totals']['sales_count'], 'Sólo la venta posterior al cierre.');
        $this->assertSame(7500.0, $caja['totals']['revenue']);
    }

    public function test_la_primera_caja_no_arrastra_ventas_de_dias_anteriores(): void
    {
        $product = $this->producto();
        $this->venta($product, 1, '-1 day');
        $this->venta($product, 1, '-1 hour');

        $caja = Livewire::test(CashClosingComponent::class)->instance()->register();

        $this->assertSame(1, $caja['totals']['sales_count']);
    }

    public function test_la_base_se_propone_con_la_del_ultimo_cierre(): void
    {
        Livewire::test(CashClosingComponent::class)
            ->set('opening_cash', 80000)
            ->call('closeRegister');

        Livewire::test(CashClosingComponent::class)->assertSet('opening_cash', 80000.0);
    }

    public function test_anular_un_cierre_pide_la_clave(): void
    {
        Livewire::test(CashClosingComponent::class)->call('closeRegister');
        $cierre = CashClosing::sole();

        Livewire::test(CashClosingComponent::class)
            ->call('confirmDeleteClosing', $cierre->id)
            ->call('deleteClosing')
            ->assertHasErrors('deletePassword');

        Livewire::test(CashClosingComponent::class)
            ->call('confirmDeleteClosing', $cierre->id)
            ->set('deletePassword', 'equivocada')
            ->call('deleteClosing')
            ->assertHasErrors('deletePassword');

        $this->assertDatabaseHas('cash_closings', ['id' => $cierre->id]);

        Livewire::test(CashClosingComponent::class)
            ->call('confirmDeleteClosing', $cierre->id)
            ->set('deletePassword', AdminPassword::INICIAL)
            ->call('deleteClosing')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('cash_closings', ['id' => $cierre->id]);
    }

    public function test_solo_se_anula_el_ultimo_cierre(): void
    {
        Livewire::test(CashClosingComponent::class)->call('closeRegister');
        $primero = CashClosing::sole();

        $this->travel(1)->hour();
        Livewire::test(CashClosingComponent::class)->call('closeRegister');

        Livewire::test(CashClosingComponent::class)
            ->call('confirmDeleteClosing', $primero->id)
            ->assertSet('confirmingClosingId', null)
            ->set('deletePassword', AdminPassword::INICIAL)
            ->call('deleteClosing');

        $this->assertDatabaseHas('cash_closings', ['id' => $primero->id]);
    }

    public function test_al_anular_las_ventas_vuelven_a_la_caja_abierta(): void
    {
        $this->venta($this->producto(), 2, '-1 hour');

        Livewire::test(CashClosingComponent::class)->call('closeRegister');
        $cierre = CashClosing::sole();

        Livewire::test(CashClosingComponent::class)
            ->call('confirmDeleteClosing', $cierre->id)
            ->set('deletePassword', AdminPassword::INICIAL)
            ->call('deleteClosing');

        $caja = Livewire::test(CashClosingComponent::class)->instance()->register();

        $this->assertSame(1, $caja['totals']['sales_count']);
    }

    public function test_descarga_el_pdf_de_un_cierre(): void
    {
        $this->venta($this->producto(), 2, '-1 hour');
        Livewire::test(CashClosingComponent::class)->set('counted_cash', 5000)->call('closeRegister');

        $respuesta = $this->get(route('cash-closing.pdf', CashClosing::sole()));

        $respuesta->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $respuesta->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $respuesta->getContent());
    }

    public function test_descarga_el_historial_y_el_informe_en_pdf(): void
    {
        $product = $this->producto();
        $this->venta($product, 2, '-1 hour');
        $this->venta($product, 1, '2026-10-02 10:00', 'transfer');
        Livewire::test(CashClosingComponent::class)->call('closeRegister');

        // Un producto borrado del catálogo no debe tumbar el informe.
        SaleDetail::query()->update(['product_id' => null]);

        $rango = ['desde' => '2026-10-01', 'hasta' => '2026-10-14'];

        foreach (['cash-closing.history-pdf', 'cash-closing.report-pdf'] as $ruta) {
            $respuesta = $this->get(route($ruta, $rango));

            $respuesta->assertOk();
            $this->assertSame('application/pdf', $respuesta->headers->get('Content-Type'));
            $this->assertStringContainsString('2026-10-01-a-2026-10-14', $respuesta->headers->get('Content-Disposition'));
        }
    }

    public function test_el_informe_suma_por_dia_y_por_producto(): void
    {
        $barato = $this->producto(costo: 1000, precio: 2000);
        $caro = $this->producto(costo: 1000, precio: 5000);

        $this->venta($barato, 3, '2026-10-13 09:00');
        $this->venta($caro, 1, '2026-10-14 09:00');

        $periodo = SalesSummary::days('2026-10-13', '2026-10-14');
        $dias = $periodo->daily();
        $productos = $periodo->topProducts();

        $this->assertCount(2, $dias);
        $this->assertSame('2026-10-14', $dias[0]->day->toDateString(), 'El día más reciente va primero.');
        $this->assertSame(4000.0, $dias[0]->profit);
        $this->assertSame(3000.0, $dias[1]->profit);

        $this->assertSame(4000.0, $productos[0]->profit, 'Primero el que más ganancia dejó.');
        $this->assertSame(3, $productos[1]->units);
    }
}
