<?php

namespace Tests\Feature;

use App\Livewire\InventoryComponent;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La droguería no repone por unidades sueltas: un lote al que le quedan menos
 * de seis meses ya no sirve de respaldo, así que su producto entra en la
 * alerta de stock bajo aunque en bodega haya cajas de sobra.
 */
class AlertasInventarioTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(string $nombre = 'Losartán 50 mg', int $minimo = 5): Product
    {
        return Product::create([
            'barcode' => '770'.fake()->unique()->numerify('##########'),
            'name' => $nombre,
            'presentation' => 'Caja x 30',
            'cost_price' => 3000,
            'selling_price' => 7000,
            'min_stock' => $minimo,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
    }

    protected function lote(Product $product, int $stock, string $vence): Batch
    {
        return Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-'.fake()->numerify('####'),
            'expiration_date' => now()->add($vence)->toDateString(),
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    public function test_un_producto_que_vence_dentro_de_seis_meses_cuenta_como_stock_bajo(): void
    {
        $product = $this->producto();
        $this->lote($product, 100, '+4 months');

        $component = Livewire::test(InventoryComponent::class);

        $this->assertSame(1, $component->instance()->lowStockCount, 'Cien unidades que vencen en cuatro meses no son respaldo.');
        $this->assertSame(1, $component->instance()->expiringCount);
    }

    public function test_un_producto_con_vigencia_larga_no_aparece_en_ninguna_alerta(): void
    {
        $product = $this->producto();
        $this->lote($product, 100, '+2 years');

        $component = Livewire::test(InventoryComponent::class);

        $this->assertSame(0, $component->instance()->lowStockCount);
        $this->assertSame(0, $component->instance()->expiringCount);
    }

    public function test_el_limite_son_seis_meses_justos(): void
    {
        $antes = $this->producto('Justo antes del límite');
        $this->lote($antes, 50, '+5 months');

        $despues = $this->producto('Justo después del límite');
        $this->lote($despues, 50, '+7 months');

        $component = Livewire::test(InventoryComponent::class);

        $this->assertSame(1, $component->instance()->lowStockCount);
        $this->assertSame(1, $component->instance()->expiringCount);
    }

    public function test_la_ventana_de_alerta_es_configurable(): void
    {
        config(['drogueria.inventory.expiry_alert_months' => 12]);

        $product = $this->producto();
        $this->lote($product, 80, '+9 months');

        $component = Livewire::test(InventoryComponent::class);

        $this->assertSame(12, $component->instance()->expiryAlertMonths());
        $this->assertSame(1, $component->instance()->lowStockCount, 'Con la ventana en un año, nueve meses ya es poco.');
    }

    public function test_las_existencias_con_vigencia_larga_sacan_al_producto_de_stock_bajo(): void
    {
        $product = $this->producto(minimo: 10);
        $this->lote($product, 20, '+2 months');   // por vencer
        $this->lote($product, 40, '+18 months');  // respaldo real

        $component = Livewire::test(InventoryComponent::class);

        $this->assertSame(0, $component->instance()->lowStockCount);
        $this->assertSame(1, $component->instance()->expiringCount, 'El lote corto se sigue avisando aparte.');
    }

    public function test_el_filtro_por_vencer_lista_el_producto(): void
    {
        $product = $this->producto('Vence pronto');
        $this->lote($product, 30, '+3 months');

        $otro = $this->producto('Vence lejos');
        $this->lote($otro, 30, '+3 years');

        Livewire::test(InventoryComponent::class)
            ->set('filter', 'expiring')
            ->assertSee('Vence pronto')
            ->assertDontSee('Vence lejos');
    }

    public function test_el_filtro_de_stock_bajo_lista_el_producto_por_vencer(): void
    {
        $product = $this->producto('Reponer ya');
        $this->lote($product, 200, '+1 month');

        Livewire::test(InventoryComponent::class)
            ->set('filter', 'low')
            ->assertSee('Reponer ya');
    }

    public function test_la_lista_avisa_cuantas_unidades_estan_por_vencer(): void
    {
        $product = $this->producto(minimo: 2);
        $this->lote($product, 15, '+2 months');
        $this->lote($product, 40, '+2 years');

        Livewire::test(InventoryComponent::class)
            ->assertSee('15 por vencer')
            ->assertSee('Por vencer (6 meses)');
    }

    public function test_los_lotes_vencidos_no_entran_en_la_alerta_de_por_vencer(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product, 10, '+1 day');
        $batch->update(['expiration_date' => now()->subDay()->toDateString()]);

        $component = Livewire::test(InventoryComponent::class);

        $this->assertSame(0, $component->instance()->expiringCount, 'Lo vencido tiene su propio contador.');
        $this->assertSame(1, $component->instance()->expiredCount);
        $this->assertSame(1, $component->instance()->lowStockCount);
    }
}
