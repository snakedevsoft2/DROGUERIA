<?php

namespace Tests\Feature;

use App\Livewire\InventoryComponent;
use App\Livewire\PriceListComponent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListaDePreciosTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(string $nombre, int $unidades = 10, float $precioUnidad = 500, bool $activo = true): Product
    {
        return Product::create([
            'barcode' => '770'.fake()->unique()->numerify('##########'),
            'name' => $nombre,
            'presentation' => 'Caja x '.$unidades,
            'units_per_package' => $unidades,
            'cost_price' => 3500,
            'selling_price' => $precioUnidad,
            'min_stock' => 5,
            'requires_prescription' => false,
            'is_active' => $activo,
        ]);
    }

    public function test_la_pagina_de_precios_responde(): void
    {
        $this->get(route('prices'))->assertOk();
    }

    public function test_el_precio_de_la_caja_multiplica_la_unidad(): void
    {
        $product = $this->producto('Acetaminofén 500 mg', unidades: 10, precioUnidad: 500);

        $this->assertSame(5000.0, $product->package_price);
    }

    public function test_muestra_el_precio_por_unidad_y_por_caja(): void
    {
        $this->producto('Losartán 50 mg', unidades: 30, precioUnidad: 600);

        Livewire::test(PriceListComponent::class)
            ->assertSee('Losartán 50 mg')
            ->assertSee('$600')
            ->assertSee('$18.000');
    }

    public function test_un_producto_creado_en_inventario_aparece_en_la_lista(): void
    {
        Livewire::test(InventoryComponent::class)
            ->set('barcode', '7704444444444')
            ->set('name', 'Omeprazol 20 mg')
            ->set('presentation', 'Caja x 14')
            ->set('units_per_package', 14)
            ->set('cost_price', 4200)
            ->set('selling_price', 700)
            ->set('min_stock', 1)
            ->call('saveProduct')
            ->assertHasNoErrors();

        Livewire::test(PriceListComponent::class)
            ->assertSee('Omeprazol 20 mg')
            ->assertSee('$700')
            ->assertSee('$9.800');
    }

    public function test_oculta_los_productos_sin_precio_y_los_inactivos(): void
    {
        $this->producto('Sin precio', precioUnidad: 0);
        $this->producto('Desactivado', activo: false);
        $this->producto('Vitamina C');

        Livewire::test(PriceListComponent::class)
            ->assertSee('Vitamina C')
            ->assertDontSee('Sin precio')
            ->assertDontSee('Desactivado')
            ->set('showInactive', true)
            ->assertSee('Desactivado')
            ->assertDontSee('Sin precio');
    }

    public function test_busca_por_nombre(): void
    {
        $this->producto('Ibuprofeno 400 mg');
        $this->producto('Loratadina 10 mg');

        Livewire::test(PriceListComponent::class)
            ->set('search', 'ibupro')
            ->assertSee('Ibuprofeno 400 mg')
            ->assertDontSee('Loratadina 10 mg');
    }
}
