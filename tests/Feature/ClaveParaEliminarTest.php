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
 * Consultar y vender no piden nada; borrar inventario sí, porque es lo único
 * que no tiene vuelta atrás.
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

    public function test_desactivar_no_pide_clave(): void
    {
        $product = $this->producto();
        $batch = $this->lote($product);

        // Desactivar se deshace; por eso no exige clave.
        Livewire::test(InventoryComponent::class)
            ->call('toggleProduct', $product->id)
            ->call('toggleBatch', $batch->id)
            ->assertHasNoErrors();

        $this->assertFalse((bool) $product->fresh()->is_active);
        $this->assertFalse((bool) $batch->fresh()->is_active);
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
