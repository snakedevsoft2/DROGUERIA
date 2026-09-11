<?php

namespace Tests\Feature;

use App\Livewire\ReportsComponent;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Support\AdminPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Anular una venta del historial: sólo con la clave del dueño, y devolviendo
 * la mercancía al lote del que salió.
 */
class EliminarVentaTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected Batch $batch;

    protected Sale $sale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'barcode' => '7701234567890',
            'name' => 'Dolex 500 mg',
            'presentation' => 'Caja x 10',
            'units_per_package' => 10,
            'cost_price' => 3000,
            'selling_price' => 500,
            'min_stock' => 5,
            'requires_prescription' => false,
            'is_active' => true,
        ]);

        $this->batch = Batch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 47,   // ya salieron 3 unidades en la venta
            'is_active' => true,
        ]);

        $this->sale = Sale::create([
            'invoice_number' => 'FAC-00000001',
            'subtotal' => 1500, 'tax' => 0, 'discount' => 0, 'total' => 1500,
            'paid_amount' => 2000, 'change_amount' => 500, 'payment_method' => 'cash',
        ]);

        SaleDetail::create([
            'sale_id' => $this->sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'batch_id' => $this->batch->id,
            'quantity' => 3,
            'unit_price' => 500,
            'subtotal' => 1500,
        ]);
    }

    public function test_el_historial_ofrece_eliminar_cada_venta(): void
    {
        Livewire::test(ReportsComponent::class)->assertSee('Eliminar');
    }

    public function test_sin_clave_la_venta_no_se_borra(): void
    {
        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->call('deleteSale')
            ->assertHasErrors('deletePassword');

        $this->assertSame(1, Sale::count());
        $this->assertSame(47, $this->batch->fresh()->stock);
    }

    public function test_con_clave_equivocada_tampoco(): void
    {
        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->set('deletePassword', 'nolase')
            ->call('deleteSale')
            ->assertHasErrors('deletePassword');

        $this->assertSame(1, Sale::count());
    }

    public function test_con_la_clave_se_borra_y_el_stock_regresa(): void
    {
        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->set('deletePassword', '1234')
            ->call('deleteSale')
            ->assertHasNoErrors();

        $this->assertSame(0, Sale::count());
        $this->assertSame(0, SaleDetail::count(), 'El detalle se va con la venta.');
        $this->assertSame(50, $this->batch->fresh()->stock, 'Las 3 unidades vuelven al lote.');
    }

    public function test_borrar_no_falla_si_el_lote_ya_no_existe(): void
    {
        SaleDetail::query()->update(['batch_id' => null]);
        $this->batch->delete();

        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->set('deletePassword', '1234')
            ->call('deleteSale')
            ->assertHasNoErrors();

        $this->assertSame(0, Sale::count());
    }

    public function test_usa_la_clave_nueva_cuando_se_cambia(): void
    {
        AdminPassword::set('susalud2026');

        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->set('deletePassword', '1234')
            ->call('deleteSale')
            ->assertHasErrors('deletePassword');

        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->set('deletePassword', 'susalud2026')
            ->call('deleteSale')
            ->assertHasNoErrors();

        $this->assertSame(0, Sale::count());
    }

    public function test_cancelar_deja_la_venta_intacta(): void
    {
        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->call('cancelDeleteSale')
            ->assertSet('confirmingSaleId', null)
            ->assertSet('deletePassword', '');

        $this->assertSame(1, Sale::count());
    }

    public function test_las_demas_ventas_no_se_tocan(): void
    {
        $otra = Sale::create([
            'invoice_number' => 'FAC-00000002',
            'subtotal' => 9000, 'tax' => 0, 'discount' => 0, 'total' => 9000,
            'paid_amount' => 9000, 'change_amount' => 0, 'payment_method' => 'card',
        ]);

        Livewire::test(ReportsComponent::class)
            ->call('confirmDeleteSale', $this->sale->id)
            ->set('deletePassword', '1234')
            ->call('deleteSale');

        $this->assertNotNull(Sale::find($otra->id));
        $this->assertSame(1, Sale::count());
    }
}
