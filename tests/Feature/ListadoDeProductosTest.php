<?php

namespace Tests\Feature;

use App\Livewire\InventoryComponent;
use App\Livewire\ProductListComponent;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ListadoDeProductosTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(string $nombre, float $venta = 500, bool $activo = true): Product
    {
        return Product::create([
            'barcode' => '770'.fake()->unique()->numerify('##########'),
            'name' => $nombre,
            'units_per_package' => 10,
            'cost_price' => 3000,
            'selling_price' => $venta,
            'min_stock' => 5,
            'requires_prescription' => false,
            'is_active' => $activo,
        ]);
    }

    public function test_la_pagina_responde(): void
    {
        $this->get(route('product-list'))->assertOk();
    }

    public function test_muestra_costo_y_precio_por_unidad_incluso_sin_precio(): void
    {
        $this->producto('Ibuprofeno', 500);
        $this->producto('Sin precio', 0);

        Livewire::test(ProductListComponent::class)
            ->assertSee('Ibuprofeno')
            ->assertSee('Sin precio')
            ->assertSee('$300')
            ->assertSee('$500');
    }

    public function test_la_busqueda_ignora_mayusculas_y_minusculas(): void
    {
        $this->producto('ACETAMINOFEN');
        $this->producto('ibuprofeno');

        Livewire::test(ProductListComponent::class)
            ->set('search', 'acetaminofen')->assertSee('ACETAMINOFEN')->assertDontSee('ibuprofeno')
            ->set('search', 'IBUPROFENO')->assertSee('ibuprofeno')->assertDontSee('ACETAMINOFEN');

        Livewire::test(InventoryComponent::class)
            ->set('search', 'acetaminofen')->assertSee('ACETAMINOFEN')
            ->set('search', 'IBUPROFENO')->assertSee('ibuprofeno');
    }
}
