<?php

namespace Tests\Feature;

use App\Livewire\InventoryComponent;
use App\Livewire\ReportsComponent;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Support\AdminPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Consultar, vender y registrar mercancía nueva no piden nada; modificar o
 * borrar el inventario sí pide la clave del administrador.
 */
class ClaveParaEliminarTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(): Product
    {
        return Product::create([
            'barcode' => '7701234567890',
            'name' => 'Naproxeno 250 mg',
            'presentation' => 'Caja x 10',
            'units_per_package' => 10,
            'cost_price' => 4000,
            'selling_price' => 600,
            'min_stock' => 5,
            'requires_prescription' => false,
            'is_active' => true,
        ]);
    }

    protected function lote(Product $product): Batch
    {
        return Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 40,
            'is_active' => true,
        ]);
    }

    public function test_sin_clave_no_se_borra_el_producto(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', $product->id)
            ->call('deleteProduct')
            ->assertHasErrors('deletePassword');

        $this->assertNotNull(Product::find($product->id));
    }

    public function test_con_una_clave_equivocada_tampoco(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', $product->id)
            ->set('deletePassword', 'loquesea')
            ->call('deleteProduct')
            ->assertHasErrors('deletePassword');

        $this->assertNotNull(Product::find($product->id));
    }

    public function test_con_la_clave_correcta_se_borra(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', $product->id)
            ->set('deletePassword', '1234')
            ->call('deleteProduct')
            ->assertHasNoErrors();

        $this->assertNull(Product::find($product->id));
    }

    public function test_el_lote_tambien_pide_clave(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);

        Livewire::test(InventoryComponent::class)
            ->call('confirmDeleteBatch', $batch->id)
            ->call('deleteBatch')
            ->assertHasErrors('deletePassword');

        $this->assertNotNull(Batch::find($batch->id));

        Livewire::test(InventoryComponent::class)
            ->call('confirmDeleteBatch', $batch->id)
            ->set('deletePassword', '1234')
            ->call('deleteBatch')
            ->assertHasNoErrors();

        $this->assertNull(Batch::find($batch->id));
    }

    public function test_vender_y_consultar_no_piden_clave(): void
    {
        Sale::create([
            'invoice_number' => 'FAC-00000001',
            'subtotal' => 50000, 'tax' => 0, 'discount' => 0, 'total' => 50000,
            'paid_amount' => 50000, 'change_amount' => 0, 'payment_method' => 'cash',
        ]);

        // Los reportes se abren directo: muestran las ventas sin pedir nada.
        Livewire::test(ReportsComponent::class)
            ->assertSee('50.000')
            ->assertDontSee('Reportes protegidos');

        $this->get(route('pos'))->assertOk();
        $this->get(route('reports'))->assertOk();
    }

    public function test_desactivar_pide_clave(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);

        // Sin clave no cambia nada.
        Livewire::test(InventoryComponent::class)
            ->call('toggleProduct', $product->id)
            ->set('clavePassword', 'equivocada')
            ->call('confirmarClave')
            ->assertHasErrors('clavePassword');

        $this->assertTrue((bool) $product->fresh()->is_active);

        Livewire::test(InventoryComponent::class)
            ->call('toggleProduct', $product->id)
            ->set('clavePassword', AdminPassword::INICIAL)
            ->call('confirmarClave')
            ->call('toggleBatch', $batch->id)
            ->set('clavePassword', AdminPassword::INICIAL)
            ->call('confirmarClave')
            ->assertHasNoErrors();

        $this->assertFalse((bool) $product->fresh()->is_active);
        $this->assertFalse((bool) $batch->fresh()->is_active);
    }

    public function test_editar_un_producto_pide_clave(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('editProduct', $product->id)
            ->assertSet('showProductModal', false)
            ->set('clavePassword', 'equivocada')
            ->call('confirmarClave')
            ->assertHasErrors('clavePassword')
            ->assertSet('showProductModal', false)
            ->set('clavePassword', AdminPassword::INICIAL)
            ->call('confirmarClave')
            ->assertSet('showProductModal', true)
            ->set('selling_price', 900)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertEquals(900, $product->fresh()->selling_price);
    }

    public function test_no_se_guarda_una_ficha_existente_sin_clave(): void
    {
        $product = $this->producto();

        // Aunque alguien ponga el id a mano, sin la clave no se guarda.
        Livewire::test(InventoryComponent::class)
            ->set('productId', $product->id)
            ->set('barcode', $product->barcode)
            ->set('name', 'Otro nombre')
            ->set('presentation', 'Caja x 10')
            ->set('units_per_package', 10)
            ->set('cost_price', 4000)
            ->set('selling_price', 450)
            ->call('saveProduct')
            ->assertHasErrors('batchDeletePassword');

        $this->assertSame('Naproxeno 250 mg', $product->fresh()->name);
    }

    public function test_editar_un_lote_pide_clave(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);

        $componente = Livewire::test(InventoryComponent::class)
            ->call('editBatch', $batch->id)
            ->assertSet('showBatchModal', false)
            ->set('clavePassword', AdminPassword::INICIAL)
            ->call('confirmarClave')
            ->assertSet('showBatchModal', true);

        $filas = $componente->get('batchRows');
        $filas[0]['stock'] = 3;
        $componente->set('batchRows', $filas)->call('saveBatch')->assertHasNoErrors();

        $this->assertSame(3, (int) $batch->fresh()->stock);
    }

    public function test_cambiar_un_lote_guardado_sin_clave_no_se_permite(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);

        // Se cuela un lote guardado en el formulario de "agregar lote".
        $componente = Livewire::test(InventoryComponent::class)->call('createBatch', $product->id);
        $filas = $componente->get('batchRows');
        $filas[0] = array_merge($filas[0], [
            'id' => $batch->id,
            'batch_number' => $batch->batch_number,
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 999,
        ]);

        $componente->set('batchRows', $filas)->call('saveBatch')->assertHasErrors('batchDeletePassword');

        $this->assertNotSame(999, (int) $batch->fresh()->stock);
    }

    public function test_agregar_lotes_nuevos_no_pide_clave(): void
    {
        $product = $this->producto();

        $componente = Livewire::test(InventoryComponent::class)->call('createBatch', $product->id);
        $filas = $componente->get('batchRows');
        $filas[0] = array_merge($filas[0], [
            'batch_number' => 'L-NUEVO',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 12,
        ]);

        $componente->set('batchRows', $filas)->call('saveBatch')->assertHasNoErrors();

        $this->assertSame(12, (int) $product->batches()->where('batch_number', 'L-NUEVO')->value('stock'));
    }

    public function test_la_clave_se_cambia_desde_reportes(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('current_password', '1234')
            ->set('new_password', 'susalud2026')
            ->set('new_password_confirmation', 'susalud2026')
            ->call('changePassword')
            ->assertHasNoErrors();

        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', $product->id)
            ->set('deletePassword', '1234')
            ->call('deleteProduct')
            ->assertHasErrors('deletePassword');

        Livewire::test(InventoryComponent::class)
            ->call('confirmDelete', $product->id)
            ->set('deletePassword', 'susalud2026')
            ->call('deleteProduct')
            ->assertHasNoErrors();

        $this->assertNull(Product::find($product->id));
    }

    public function test_no_cambia_la_clave_sin_la_actual(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('current_password', 'equivocada')
            ->set('new_password', 'nueva1234')
            ->set('new_password_confirmation', 'nueva1234')
            ->call('changePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(AdminPassword::check('1234'));
    }

    public function test_la_confirmacion_debe_coincidir(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('current_password', '1234')
            ->set('new_password', 'nueva1234')
            ->set('new_password_confirmation', 'otracosa')
            ->call('changePassword')
            ->assertHasErrors('new_password');
    }

    public function test_la_clave_queda_guardada_cifrada(): void
    {
        AdminPassword::hash();

        $guardado = \App\Models\Setting::get(AdminPassword::KEY);

        $this->assertNotSame('1234', $guardado, 'La clave no puede quedar en texto plano.');
        $this->assertTrue(Hash::check('1234', $guardado));
    }

    public function test_avisa_mientras_siga_la_clave_de_fabrica(): void
    {
        Livewire::test(ReportsComponent::class)->assertSee('es la de fábrica');

        AdminPassword::set('otra-clave-distinta');

        Livewire::test(ReportsComponent::class)->assertDontSee('es la de fábrica');
    }
}
