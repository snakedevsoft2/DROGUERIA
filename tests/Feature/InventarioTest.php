<?php

namespace Tests\Feature;

use App\Livewire\InventoryComponent;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(string $nombre = 'Ibuprofeno 400 mg'): Product
    {
        return Product::create([
            'barcode' => '770'.fake()->unique()->numerify('##########'),
            'name' => $nombre,
            'presentation' => 'Caja x 10',
            'cost_price' => 1000,
            'selling_price' => 2500,
            'min_stock' => 5,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
    }

    protected function lote(Product $product, int $stock = 10): Batch
    {
        return Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-'.fake()->numerify('####'),
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => $stock,
            'is_active' => true,
        ]);
    }

    /** Una venta ya registrada de ese producto y ese lote. */
    protected function venta(Product $product, Batch $batch): Sale
    {
        $sale = Sale::create([
            'invoice_number' => 'FAC-00000001',
            'subtotal' => 5000, 'tax' => 0, 'discount' => 0, 'total' => 5000,
            'paid_amount' => 5000, 'change_amount' => 0, 'payment_method' => 'cash',
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'batch_id' => $batch->id,
            'quantity' => 2,
            'unit_price' => 2500,
            'subtotal' => 5000,
        ]);

        return $sale;
    }

    public function test_elimina_un_producto_aunque_tenga_lotes(): void
    {
        $product = $this->producto();
        $this->lote($product);

        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', $product->id)
            ->set('deletePassword', '1234')
            ->call('deleteProduct');

        $this->assertNull(Product::find($product->id));
        $this->assertSame(0, Batch::where('product_id', $product->id)->count());
    }

    public function test_elimina_un_producto_que_ya_fue_vendido_sin_tocar_la_venta(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);
        $sale = $this->venta($product, $batch);

        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', $product->id)
            ->set('deletePassword', '1234')
            ->call('deleteProduct');

        $this->assertNull(Product::find($product->id));

        $detail = SaleDetail::first();
        $this->assertNull($detail->product_id);
        $this->assertSame('Ibuprofeno 400 mg', $detail->product_name);
        $this->assertEquals(5000, $sale->fresh()->total);
    }

    public function test_elimina_un_lote_que_ya_fue_vendido_sin_tocar_la_venta(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);
        $sale = $this->venta($product, $batch);

        Livewire::test(InventoryComponent::class)
            ->call('confirmDeleteBatch', $batch->id)
            ->set('deletePassword', '1234')
            ->call('deleteBatch');

        $this->assertNull(Batch::find($batch->id));

        $detail = SaleDetail::first();
        $this->assertNull($detail->batch_id);
        $this->assertSame($product->id, $detail->product_id);
        $this->assertEquals(5000, $sale->fresh()->total);
    }

    public function test_activa_y_desactiva_un_lote(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);

        Livewire::test(InventoryComponent::class)->call('toggleBatch', $batch->id);
        $this->assertFalse((bool) $batch->fresh()->is_active);

        Livewire::test(InventoryComponent::class)->call('toggleBatch', $batch->id);
        $this->assertTrue((bool) $batch->fresh()->is_active);
    }

    public function test_activa_y_desactiva_un_producto(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)->call('toggleProduct', $product->id);
        $this->assertFalse((bool) $product->fresh()->is_active);

        Livewire::test(InventoryComponent::class)->call('toggleProduct', $product->id);
        $this->assertTrue((bool) $product->fresh()->is_active);
    }

    public function test_guarda_un_producto_nuevo_desde_el_formulario(): void
    {
        Livewire::test(InventoryComponent::class)
            ->set('barcode', '7701234567890')
            ->set('name', 'Loratadina 10 mg')
            ->set('presentation', 'Caja x 10')
            ->set('cost_price', 1500)
            ->set('selling_price', 3000)
            ->set('min_stock', 3)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Loratadina 10 mg', 'selling_price' => 3000]);
    }

    public function test_no_deja_vender_por_debajo_del_costo(): void
    {
        Livewire::test(InventoryComponent::class)
            ->set('barcode', '7701234567891')
            ->set('name', 'Producto mal tarifado')
            ->set('cost_price', 5000)
            ->set('selling_price', 1000)
            ->set('min_stock', 1)
            ->call('saveProduct')
            ->assertHasErrors('selling_price');
    }

    public function test_borrar_un_producto_inexistente_no_rompe_nada(): void
    {
        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', 9999)
            ->set('deletePassword', '1234')
            ->call('deleteProduct')
            ->assertOk();
    }

    public function test_la_pagina_de_inventario_responde(): void
    {
        $this->get(route('inventory'))->assertOk();
    }
}
