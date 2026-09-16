<?php

namespace Tests\Feature;

use App\Livewire\InventoryComponent;
use App\Livewire\PosComponent;
use App\Models\Batch;
use App\Models\Product;
use App\Support\AdminPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Un pedido no llega con un lote sino con varios, cada uno con su vencimiento y
 * a veces con un código de barras distinto en el empaque. Todos se cargan de
 * una sola vez, y quitar uno pide la clave porque borra mercancía.
 */
class LotesTest extends TestCase
{
    use RefreshDatabase;

    protected function producto(string $barcode = '7701234567890'): Product
    {
        return Product::create([
            'barcode' => $barcode,
            'name' => 'Acetaminofén 500 mg',
            'presentation' => 'Caja x 10',
            'units_per_package' => 10,
            'cost_price' => 3000,
            'selling_price' => 500,
            'min_stock' => 5,
            'is_active' => true,
        ]);
    }

    /** Fila del formulario, como la arma la vista. */
    protected function fila(array $datos = []): array
    {
        return array_merge([
            'uid' => uniqid('lote-', true),
            'id' => null,
            'batch_number' => 'L-0001',
            'barcode' => '',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 10,
            'is_active' => true,
        ], $datos);
    }

    public function test_un_producto_nuevo_se_guarda_con_varios_lotes_a_la_vez(): void
    {
        Livewire::test(InventoryComponent::class)
            ->call('createProduct')
            ->set('barcode', '7709999999999')
            ->set('name', 'Ibuprofeno 400 mg')
            ->set('units_per_package', 10)
            ->set('cost_price', 2000)
            ->set('selling_price', 400)
            ->set('min_stock', 3)
            ->set('batchRows', [
                $this->fila(['batch_number' => 'L-A', 'expiration_date' => now()->addYear()->toDateString(), 'stock' => 12]),
                $this->fila(['batch_number' => 'L-B', 'expiration_date' => now()->addYears(2)->toDateString(), 'stock' => 30]),
            ])
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('barcode', '7709999999999')->first();

        $this->assertCount(2, $product->batches);
        $this->assertSame(42, (int) $product->batches->sum('stock'));
        $this->assertNotSame(
            (string) $product->batches[0]->expiration_date,
            (string) $product->batches[1]->expiration_date
        );
    }

    public function test_agrega_varios_lotes_a_un_producto_que_ya_existe(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('createBatch', $product->id)
            ->set('batchRows', [
                $this->fila(['batch_number' => 'L-100', 'stock' => 5]),
                $this->fila(['batch_number' => 'L-200', 'expiration_date' => now()->addMonths(18)->toDateString(), 'stock' => 8]),
            ])
            ->call('saveBatch')
            ->assertHasNoErrors();

        $this->assertSame(2, $product->batches()->count());
        $this->assertSame(13, (int) $product->batches()->sum('stock'));
    }

    public function test_un_lote_puede_traer_su_propio_codigo_de_barras(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('createBatch', $product->id)
            ->set('batchRows', [
                $this->fila(['batch_number' => 'L-100', 'barcode' => '7705555555555']),
            ])
            ->call('saveBatch')
            ->assertHasNoErrors();

        $this->assertSame('7705555555555', $product->batches()->first()->barcode);
    }

    public function test_el_punto_de_venta_encuentra_el_producto_por_el_codigo_del_lote(): void
    {
        $product = $this->producto();

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-100',
            'barcode' => '7705555555555',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 10,
            'is_active' => true,
        ]);

        Livewire::test(PosComponent::class)
            ->set('search', '7705555555555')
            ->call('searchByBarcode')
            ->assertSet('cart.'.$product->id.'.id', $product->id);
    }

    public function test_el_punto_de_venta_no_adivina_cuando_el_codigo_es_de_dos_productos(): void
    {
        $uno = $this->producto('7704444444444');
        $uno->update(['name' => 'Producto A']);
        $dos = Product::create([
            'barcode' => '7704444444444',
            'name' => 'Producto B',
            'presentation' => 'Caja x 10',
            'units_per_package' => 10,
            'cost_price' => 3000,
            'selling_price' => 500,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $componente = Livewire::test(PosComponent::class)
            ->set('search', '7704444444444')
            ->call('searchByBarcode');

        // No agrega ninguno al carrito de una: dos productos comparten el
        // código y hay que dejar que el cajero elija.
        $componente->assertSet('cart', []);

        $resultados = $componente->get('searchResults');
        $this->assertCount(2, $resultados);
        $this->assertTrue($resultados->pluck('id')->contains($uno->id));
        $this->assertTrue($resultados->pluck('id')->contains($dos->id));
    }

    public function test_el_mismo_codigo_no_puede_ser_de_dos_productos(): void
    {
        $this->producto('7701111111111');
        $otro = $this->producto('7702222222222');

        Livewire::test(InventoryComponent::class)
            ->call('createBatch', $otro->id)
            ->set('batchRows', [
                $this->fila(['batch_number' => 'L-100', 'barcode' => '7701111111111']),
            ])
            ->call('saveBatch')
            ->assertHasErrors('batchRows.0.barcode');

        $this->assertSame(0, $otro->batches()->count());
    }

    public function test_no_acepta_dos_veces_el_mismo_numero_de_lote(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('createBatch', $product->id)
            ->set('batchRows', [
                $this->fila(['batch_number' => 'L-100']),
                $this->fila(['batch_number' => 'l-100', 'expiration_date' => now()->addMonths(20)->toDateString()]),
            ])
            ->call('saveBatch')
            ->assertHasErrors('batchRows.1.batch_number');

        $this->assertSame(0, $product->batches()->count());
    }

    public function test_quitar_un_lote_guardado_pide_la_clave(): void
    {
        $product = $this->producto();
        $lote = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 40,
            'is_active' => true,
        ]);

        // Sin clave no se borra nada.
        $componente = Livewire::test(InventoryComponent::class)
            ->call('editProduct', $product->id)
            ->call('removeBatchRow', 0)
            ->call('saveProduct')
            ->assertHasErrors('batchDeletePassword');

        $this->assertNotNull(Batch::find($lote->id));

        // Con la clave correcta sí.
        $componente
            ->set('batchDeletePassword', AdminPassword::INICIAL)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertNull(Batch::find($lote->id));
    }

    public function test_deshacer_devuelve_el_lote_quitado(): void
    {
        $product = $this->producto();
        $lote = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 40,
            'is_active' => true,
        ]);

        Livewire::test(InventoryComponent::class)
            ->call('editProduct', $product->id)
            ->call('removeBatchRow', 0)
            ->call('restoreRemovedBatch', $lote->id)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertNotNull(Batch::find($lote->id));
        $this->assertSame(40, (int) Batch::find($lote->id)->stock);
    }

    public function test_editar_el_producto_conserva_y_actualiza_sus_lotes(): void
    {
        $product = $this->producto();
        $lote = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 40,
            'is_active' => true,
        ]);

        $componente = Livewire::test(InventoryComponent::class)->call('editProduct', $product->id);

        $filas = $componente->get('batchRows');
        $filas[0]['stock'] = 55;
        $filas[] = $this->fila(['batch_number' => 'L-0002', 'expiration_date' => now()->addMonths(20)->toDateString(), 'stock' => 7]);

        $componente->set('batchRows', $filas)->call('saveProduct')->assertHasNoErrors();

        $this->assertSame(55, (int) $lote->fresh()->stock);
        $this->assertSame(2, $product->batches()->count());
    }

    public function test_un_lote_vencido_no_se_puede_registrar(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('createBatch', $product->id)
            ->set('batchRows', [
                $this->fila(['expiration_date' => now()->subDay()->toDateString()]),
            ])
            ->call('saveBatch')
            ->assertHasErrors('batchRows.0.expiration_date');
    }

    public function test_al_escribir_un_codigo_repetido_se_avisa_pero_no_se_fusiona_solo(): void
    {
        $product = $this->producto();

        // Al salir del campo se detecta la coincidencia, pero ya no se
        // cambia de ficha solo: eso ahora lo decide el usuario.
        Livewire::test(InventoryComponent::class)
            ->call('createProduct')
            ->set('barcode', $product->barcode)
            ->assertSet('productId', null)
            ->assertSet('fusionadoConExistente', false)
            ->assertSet('duplicadoProductId', $product->id)
            ->assertSet('duplicadoNombre', $product->name);
    }

    public function test_el_boton_de_fusionar_pasa_el_formulario_a_la_ficha_existente(): void
    {
        $product = $this->producto();

        // El usuario confirma que es el mismo producto: ahí sí se fusiona y
        // lo que escriba entra como lote nuevo de esa ficha.
        Livewire::test(InventoryComponent::class)
            ->call('createProduct')
            ->set('barcode', $product->barcode)
            ->call('usarProductoExistente')
            ->assertSet('productId', $product->id)
            ->assertSet('fusionadoConExistente', true)
            ->set('batchRows', [$this->fila(['batch_number' => 'L-NUEVO', 'stock' => 9])])
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertSame(1, Product::where('barcode', $product->barcode)->count());
        $this->assertSame('L-NUEVO', $product->batches()->first()->batch_number);
        $this->assertSame(9, (int) $product->batches()->first()->stock);
    }

    public function test_guardar_sin_fusionar_crea_un_producto_aparte_con_el_mismo_codigo(): void
    {
        $product = $this->producto();

        // No se toca el botón de "es el mismo": queda como producto aparte,
        // aunque comparta código de barras con el que ya existía.
        Livewire::test(InventoryComponent::class)
            ->call('createProduct')
            ->set('barcode', $product->barcode)
            ->set('name', $product->name)
            ->set('units_per_package', 10)
            ->set('cost_price', 3000)
            ->set('selling_price', 500)
            ->set('min_stock', 5)
            ->set('batchRows', [$this->fila(['batch_number' => 'L-APARTE', 'stock' => 6])])
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertSame(2, Product::where('barcode', $product->barcode)->count());

        // Mismo nombre que el ya existente: se numera para distinguirlos.
        $nuevo = Product::where('name', $product->name.' (2)')->first();
        $this->assertNotNull($nuevo);
        $this->assertSame('L-APARTE', $nuevo->batches()->first()->batch_number);
    }

    public function test_un_nombre_repetido_se_numera_solo(): void
    {
        $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('createProduct')
            ->set('barcode', '7703333333333')
            ->set('name', 'Acetaminofén 500 mg')
            ->set('units_per_package', 10)
            ->set('cost_price', 3000)
            ->set('selling_price', 500)
            ->set('min_stock', 5)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('products', ['name' => 'Acetaminofén 500 mg (2)']);
    }

    public function test_un_renglon_en_blanco_no_estorba_para_guardar_el_producto(): void
    {
        Livewire::test(InventoryComponent::class)
            ->call('createProduct')
            ->set('barcode', '7708888888888')
            ->set('name', 'Producto sin lotes todavía')
            ->set('cost_price', 1000)
            ->set('selling_price', 1500)
            ->set('min_stock', 2)
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product = Product::where('barcode', '7708888888888')->first();

        $this->assertNotNull($product);
        $this->assertSame(0, $product->batches()->count());
    }

    public function test_el_formulario_de_lotes_no_se_guarda_vacio(): void
    {
        $product = $this->producto();

        Livewire::test(InventoryComponent::class)
            ->call('createBatch', $product->id)
            ->call('saveBatch')
            ->assertHasErrors('batchRows');

        $this->assertSame(0, $product->batches()->count());
    }

    public function test_lo_tecleado_manda_sobre_la_ficha_vieja(): void
    {
        $product = $this->producto();

        // Llegó más caro: el costo nuevo es el que se está escribiendo.
        Livewire::test(InventoryComponent::class)
            ->call('createProduct')
            ->set('name', 'Acetaminofén 500 mg')
            ->set('units_per_package', 10)
            ->set('cost_price', 4500)
            ->set('selling_price', 700)
            ->set('barcode', $product->barcode)
            ->call('usarProductoExistente')
            ->set('batchRows', [$this->fila(['batch_number' => 'L-9', 'stock' => 4])])
            ->call('saveProduct')
            ->assertHasNoErrors();

        $product->refresh();

        $this->assertSame(4500.0, (float) $product->cost_price);
        $this->assertSame(700.0, (float) $product->selling_price);
        // El mínimo no se tocó: se conserva el que tenía la ficha.
        $this->assertSame(5, (int) $product->min_stock);
    }
}
