<?php

namespace Tests\Feature;

use App\Livewire\PosComponent;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PuntoDeVentaTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(array $attributes = [], int $stock = 10, string $vence = '+1 year'): Product
    {
        $product = Product::create(array_merge([
            'barcode' => '770'.fake()->unique()->numerify('##########'),
            'name' => 'Acetaminofén 500 mg',
            'presentation' => 'Caja x 10',
            'cost_price' => 1000,
            'selling_price' => 2500,
            'min_stock' => 5,
            'requires_prescription' => false,
            'is_active' => true,
        ], $attributes));

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-'.fake()->numerify('####'),
            'expiration_date' => now()->add($vence)->toDateString(),
            'stock' => $stock,
            'is_active' => true,
        ]);

        return $product;
    }

    public function test_el_total_es_el_subtotal_menos_el_descuento_sin_iva(): void
    {
        $product = $this->producto();

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 4)
            ->set('discount', 1000)
            ->assertSet('discount', 1000)
            ->tap(function ($component) {
                $this->assertSame(10000.0, $component->instance()->subtotal);
                $this->assertSame(1000.0, $component->instance()->discountAmount);
                $this->assertSame(9000.0, $component->instance()->total);
            });
    }

    public function test_la_venta_se_guarda_sin_iva(): void
    {
        $product = $this->producto();

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 2)
            ->set('paidAmount', 5000)
            ->call('completeSale');

        $sale = Sale::first();

        $this->assertNotNull($sale);
        $this->assertEquals(0, $sale->tax);
        $this->assertEquals(5000, $sale->subtotal);
        $this->assertEquals(5000, $sale->total);
        $this->assertEquals(0, $sale->change_amount);
    }

    public function test_la_numeracion_de_facturas_arranca_en_uno_y_avanza(): void
    {
        $product = $this->producto(stock: 20);

        foreach (range(1, 3) as $i) {
            Livewire::test(PosComponent::class)
                ->call('addToCart', $product->id)
                ->set('paidAmount', 2500)
                ->call('completeSale');
        }

        $this->assertSame(
            ['FAC-00000001', 'FAC-00000002', 'FAC-00000003'],
            Sale::orderBy('id')->pluck('invoice_number')->all()
        );
    }

    public function test_el_stock_sale_del_lote_que_vence_primero(): void
    {
        $product = $this->producto(stock: 3, vence: '+2 years');

        $proximo = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-PROXIMO',
            'expiration_date' => now()->addMonth()->toDateString(),
            'stock' => 2,
            'is_active' => true,
        ]);

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 2)
            ->set('paidAmount', 5000)
            ->call('completeSale');

        $this->assertSame(0, $proximo->fresh()->stock, 'Debe consumirse primero el lote más próximo a vencer.');
        $this->assertSame(3, Batch::where('batch_number', '!=', 'L-PROXIMO')->first()->stock);
    }

    public function test_no_vende_mas_de_lo_que_hay_en_stock(): void
    {
        $product = $this->producto(stock: 2);

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 99)
            ->tap(function ($component) {
                $this->assertSame(2, $component->get('cart')[array_key_first($component->get('cart'))]['quantity']);
            });
    }

    public function test_ignora_los_lotes_vencidos_e_inactivos(): void
    {
        $product = $this->producto(stock: 5, vence: '-1 day');

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->assertSet('cart', []);

        Batch::query()->update(['expiration_date' => now()->addYear()->toDateString(), 'is_active' => false]);

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->assertSet('cart', []);
    }

    public function test_no_cierra_la_venta_si_el_pago_no_alcanza(): void
    {
        $product = $this->producto();

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->set('paidAmount', 100)
            ->call('completeSale');

        $this->assertSame(0, Sale::count());
    }

    public function test_el_detalle_guarda_el_nombre_del_producto_vendido(): void
    {
        $product = $this->producto();

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->set('paidAmount', 2500)
            ->call('completeSale');

        $this->assertSame('Acetaminofén 500 mg', SaleDetail::first()->product_name);
    }

    public function test_el_descuento_no_deja_el_total_en_negativo(): void
    {
        $product = $this->producto();

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->set('discount', 999999)
            ->tap(function ($component) {
                $this->assertSame(0.0, $component->instance()->total);
            });
    }
}
