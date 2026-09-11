<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprobanteTest extends TestCase
{
    use RefreshDatabase;

    protected Sale $sale;

    protected Product $product;

    protected Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::create([
            'barcode' => '7701234567890',
            'name' => 'Amoxicilina 500 mg',
            'presentation' => 'Caja x 12',
            'cost_price' => 4000,
            'selling_price' => 12500,
            'min_stock' => 5,
            'requires_prescription' => true,
            'is_active' => true,
        ]);

        $this->batch = Batch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 10,
            'is_active' => true,
        ]);

        $this->sale = Sale::create([
            'invoice_number' => 'FAC-00000001',
            'subtotal' => 25000, 'tax' => 0, 'discount' => 0, 'total' => 25000,
            'paid_amount' => 30000, 'change_amount' => 5000, 'payment_method' => 'cash',
        ]);

        SaleDetail::create([
            'sale_id' => $this->sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'batch_id' => $this->batch->id,
            'quantity' => 2,
            'unit_price' => 12500,
            'subtotal' => 25000,
        ]);
    }

    public function test_la_tirilla_no_muestra_iva_y_cuadra(): void
    {
        $response = $this->get(route('receipt', $this->sale));

        $response->assertOk()
            ->assertDontSee('IVA')
            ->assertSee('FAC-00000001')
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('$25.000')   // total sin decimales, miles con punto
            ->assertSee('$5.000');   // cambio
    }

    public function test_la_factura_en_hoja_responde_y_trae_los_mismos_totales(): void
    {
        $response = $this->get(route('receipt', ['sale' => $this->sale, 'formato' => 'carta']));

        $response->assertOk()
            ->assertDontSee('IVA')
            ->assertSee('FAC-00000001')
            ->assertSee('Amoxicilina 500 mg')
            ->assertSee('$25.000')
            ->assertSee('A4 portrait', false); // hoja A4, sin reescalar al imprimir
    }

    public function test_el_comprobante_se_sigue_leyendo_si_el_producto_fue_eliminado(): void
    {
        $this->product->delete();

        foreach ([[], ['formato' => 'carta']] as $extra) {
            $this->get(route('receipt', ['sale' => $this->sale] + $extra))
                ->assertOk()
                ->assertSee('Amoxicilina 500 mg')
                ->assertSee('$25.000');
        }
    }

    public function test_agrupa_en_una_linea_el_mismo_producto_tomado_de_varios_lotes(): void
    {
        $otro = Batch::create([
            'product_id' => $this->product->id,
            'batch_number' => 'L-0002',
            'expiration_date' => now()->addYears(2)->toDateString(),
            'stock' => 5,
            'is_active' => true,
        ]);

        SaleDetail::create([
            'sale_id' => $this->sale->id,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'batch_id' => $otro->id,
            'quantity' => 1,
            'unit_price' => 12500,
            'subtotal' => 12500,
        ]);

        $html = $this->get(route('receipt', $this->sale))->getContent();

        $this->assertSame(1, substr_count($html, 'Amoxicilina 500 mg'), 'El cliente debe ver una sola línea por producto.');
        // Las tres unidades quedan en un renglón, con el precio de cada una.
        $this->assertStringContainsString('$12.500', $html);
    }
}
