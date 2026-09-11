<?php

namespace Tests\Feature;

use App\Livewire\InventoryComponent;
use App\Livewire\PosComponent;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La droguería compra por caja y vende por tableta: el costo se reparte entre
 * las unidades de la presentación, y tanto el inventario como la venta se
 * llevan en unidades sueltas.
 */
class CostoPorUnidadTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(int $unidades = 10, float $costoCaja = 3500, float $precioUnidad = 500): Product
    {
        return Product::create([
            'barcode' => '770'.fake()->unique()->numerify('##########'),
            'name' => 'Acetaminofén 500 mg',
            'presentation' => 'Caja x '.$unidades,
            'units_per_package' => $unidades,
            'cost_price' => $costoCaja,
            'selling_price' => $precioUnidad,
            'min_stock' => 5,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
    }

    public function test_el_costo_por_unidad_reparte_el_costo_de_la_caja(): void
    {
        $product = $this->producto(unidades: 10, costoCaja: 3500);

        $this->assertSame(350.0, $product->unit_cost);
    }

    public function test_una_presentacion_de_una_unidad_cuesta_lo_mismo_que_la_caja(): void
    {
        $product = $this->producto(unidades: 1, costoCaja: 12500, precioUnidad: 18000);

        $this->assertSame(12500.0, $product->unit_cost);
    }

    public function test_el_formulario_calcula_el_costo_por_unidad_mientras_se_escribe(): void
    {
        $component = Livewire::test(InventoryComponent::class)
            ->set('cost_price', 9000)
            ->set('units_per_package', 30);

        $this->assertSame(300.0, $component->instance()->unitCost);
    }

    public function test_propone_un_precio_de_venta_por_unidad(): void
    {
        $component = Livewire::test(InventoryComponent::class)
            ->set('cost_price', 3500)
            ->set('units_per_package', 10);

        // 350 de costo + 30% = 455, redondeado a la centena de arriba.
        $this->assertSame(500.0, $component->instance()->suggestedPrice);

        $component->call('applySuggestedPrice')->assertSet('selling_price', 500.0);
    }

    public function test_rechaza_vender_la_unidad_por_debajo_de_su_costo(): void
    {
        Livewire::test(InventoryComponent::class)
            ->set('barcode', '7701111111111')
            ->set('name', 'Vitamina C')
            ->set('units_per_package', 10)
            ->set('cost_price', 3500)   // 350 por unidad
            ->set('selling_price', 300) // por debajo del costo unitario
            ->set('min_stock', 1)
            ->call('saveProduct')
            ->assertHasErrors('selling_price');
    }

    public function test_acepta_un_precio_por_unidad_menor_al_costo_de_la_caja(): void
    {
        Livewire::test(InventoryComponent::class)
            ->set('barcode', '7702222222222')
            ->set('name', 'Vitamina C')
            ->set('units_per_package', 10)
            ->set('cost_price', 3500)   // la caja cuesta 3.500
            ->set('selling_price', 500) // la tableta se vende a 500
            ->set('min_stock', 1)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Vitamina C', 'units_per_package' => 10]);
    }

    public function test_la_venta_cobra_el_precio_por_unidad_y_descuenta_unidades(): void
    {
        $product = $this->producto(unidades: 10, costoCaja: 3500, precioUnidad: 500);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 100,   // cien tabletas sueltas
            'is_active' => true,
        ]);

        Livewire::test(PosComponent::class)
            ->call('addToCart', $product->id)
            ->call('updateQuantity', $product->id, 3)
            ->set('paidAmount', 2000)
            ->call('completeSale');

        $this->assertEquals(1500, Sale::first()->total);
        $this->assertSame(97, $batch->fresh()->stock);
    }

    public function test_por_defecto_la_presentacion_es_de_una_unidad(): void
    {
        Livewire::test(InventoryComponent::class)
            ->set('barcode', '7703333333333')
            ->set('name', 'Jeringa 5 ml')
            ->set('cost_price', 800)
            ->set('selling_price', 1500)
            ->set('min_stock', 1)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('name', 'Jeringa 5 ml')->first();

        $this->assertSame(1, $product->units_per_package);
        $this->assertSame(800.0, $product->unit_cost);
    }

    public function test_el_inventario_muestra_el_costo_por_unidad(): void
    {
        $this->producto(unidades: 10, costoCaja: 3500, precioUnidad: 500);

        Livewire::test(InventoryComponent::class)
            ->assertSee('$350')        // costo de cada tableta
            ->assertSee('caja $3.500') // referencia de lo que cuesta la caja
            ->assertSee('10 u.');
    }
}
