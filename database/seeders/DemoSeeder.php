<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Catálogo, lotes e historial de ventas para la demostración al cliente.
 *
 * Los lotes están repartidos a propósito: algunos vencen dentro de los
 * próximos 90 días y otros quedan por debajo del mínimo, para que las
 * alertas del inventario se vean funcionando y no en cero.
 */
class DemoSeeder extends Seeder
{
    /** Porcentaje de IVA con el que trabaja el punto de venta. */
    protected const TAX_RATE = 19.0;

    /**
     * barcode, nombre, presentación, costo, precio, mínimo, fórmula médica.
     *
     * @var array<int, array{0:string,1:string,2:string,3:int,4:int,5:int,6:bool}>
     */
    protected array $catalog = [
        ['7702057001018', 'Acetaminofén 500 mg', 'Caja x 10 tabletas', 1800, 3500, 20, false],
        ['7702057002015', 'Ibuprofeno 400 mg', 'Caja x 10 tabletas', 2400, 4800, 20, false],
        ['7702057003012', 'Naproxeno 250 mg', 'Caja x 10 tabletas', 3100, 6200, 15, false],
        ['7702057004019', 'Diclofenaco 50 mg', 'Caja x 20 tabletas', 3600, 7200, 15, false],
        ['7702057005016', 'Loratadina 10 mg', 'Caja x 10 tabletas', 2200, 4500, 15, false],
        ['7702057006013', 'Omeprazol 20 mg', 'Caja x 14 cápsulas', 4200, 8500, 12, false],
        ['7702057007010', 'Amoxicilina 500 mg', 'Caja x 15 cápsulas', 6800, 13500, 10, true],
        ['7702057008017', 'Azitromicina 500 mg', 'Caja x 3 tabletas', 9500, 18900, 8, true],
        ['7702057009014', 'Losartán 50 mg', 'Caja x 30 tabletas', 7400, 14800, 12, true],
        ['7702057010010', 'Metformina 850 mg', 'Caja x 30 tabletas', 6100, 12200, 12, true],
        ['7702057011017', 'Enalapril 20 mg', 'Caja x 30 tabletas', 5200, 10400, 10, true],
        ['7702057012014', 'Levotiroxina 50 mcg', 'Caja x 30 tabletas', 8300, 16500, 8, true],
        ['7702057013011', 'Salbutamol inhalador', 'Frasco 100 mcg x 200 dosis', 14500, 27900, 6, true],
        ['7702057014018', 'Suero fisiológico 0.9%', 'Frasco 500 ml', 3900, 7800, 10, false],
        ['7702057015015', 'Sales de rehidratación oral', 'Sobre 25 g', 1500, 3200, 25, false],
        ['7702057016012', 'Vitamina C 1 g', 'Tubo x 10 efervescentes', 5600, 11200, 15, false],
        ['7702057017019', 'Complejo B', 'Caja x 30 tabletas', 6900, 13800, 10, false],
        ['7702057018016', 'Alcohol antiséptico 70%', 'Frasco 700 ml', 4300, 8600, 12, false],
        ['7702057019013', 'Agua oxigenada', 'Frasco 500 ml', 2700, 5400, 12, false],
        ['7702057020019', 'Gasa estéril', 'Paquete x 10 unidades', 2100, 4300, 20, false],
        ['7702057021016', 'Curas adhesivas', 'Caja x 20 unidades', 2500, 5200, 20, false],
        ['7702057022013', 'Jeringa desechable 5 ml', 'Unidad', 600, 1400, 40, false],
        ['7702057023010', 'Tapabocas quirúrgico', 'Caja x 50 unidades', 8500, 16900, 10, false],
        ['7702057024017', 'Termómetro digital', 'Unidad', 11000, 22500, 5, false],
        ['7702057025014', 'Tensiómetro digital de brazo', 'Unidad', 78000, 149000, 3, false],
        ['7702057026011', 'Crema antimicótica', 'Tubo 30 g', 7200, 14400, 8, false],
        ['7702057027018', 'Gotas oftálmicas lubricantes', 'Frasco 15 ml', 9800, 19500, 8, false],
        ['7702057028015', 'Jarabe para la tos', 'Frasco 120 ml', 6400, 12800, 10, false],
    ];

    /**
     * Índices del catálogo que quedan con el stock por debajo del mínimo.
     * No coinciden con los de vencimiento cercano para que cada alerta del
     * inventario se vea por separado.
     *
     * @var array<int, int>
     */
    protected array $lowStock = [4, 13, 22];

    /** Índices del catálogo cuyo primer lote vence dentro de tres meses. */
    protected array $nearExpiry = [0, 6, 12, 18, 24];

    public function run(): void
    {
        $this->reset();

        $products = $this->seedProducts();

        $this->seedSales($products);

        $this->tuneShowcaseStock($products);
    }

    /** Deja las tablas de la demo vacías para poder re-sembrar sin duplicar. */
    protected function reset(): void
    {
        SaleDetail::truncate();
        Sale::truncate();
        Batch::truncate();
        Product::truncate();
    }

    /** @return \Illuminate\Support\Collection<int, Product> */
    protected function seedProducts()
    {
        $products = collect();

        foreach ($this->catalog as $index => [$barcode, $name, $presentation, $cost, $price, $min, $rx]) {
            $product = Product::create([
                'barcode' => $barcode,
                'name' => $name,
                'presentation' => $presentation,
                'description' => null,
                'cost_price' => $cost,
                'selling_price' => $price,
                'min_stock' => $min,
                'requires_prescription' => $rx,
                'is_active' => true,
            ]);

            $this->seedBatchesFor($product, $index);

            $products->push($product);
        }

        return $products;
    }

    /**
     * Cada producto arranca con dos lotes y stock holgado: el mes de ventas
     * que se siembra después consume de ahí, y el estado final se ajusta en
     * tuneShowcaseStock(). Sembrar ya escaso dejaría los lotes en cero.
     */
    protected function seedBatchesFor(Product $product, int $index): void
    {
        // Un piso absoluto además del múltiplo: hay productos con mínimos de
        // 3 unidades que si no se quedarían sin existencias a mitad del mes.
        $stock = (int) max(60, $product->min_stock * random_int(4, 7));

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => sprintf('L%04d-A', 1000 + $index),
            'expiration_date' => in_array($index, $this->nearExpiry, true)
                ? now()->addDays(random_int(20, 85))->toDateString()
                : now()->addMonths(random_int(8, 26))->toDateString(),
            'stock' => $stock,
            'is_active' => true,
        ]);

        // Un segundo lote, más nuevo, para que se note el consumo FEFO.
        Batch::create([
            'product_id' => $product->id,
            'batch_number' => sprintf('L%04d-B', 1000 + $index),
            'expiration_date' => now()->addMonths(random_int(18, 34))->toDateString(),
            'stock' => (int) max(40, $product->min_stock * random_int(2, 4)),
            'is_active' => true,
        ]);
    }

    /**
     * Deja el inventario en el estado que se quiere enseñar: unos pocos
     * productos por debajo del mínimo, ninguno agotado y los lotes próximos
     * a vencer todavía con existencias.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    protected function tuneShowcaseStock($products): void
    {
        foreach ($products as $index => $product) {
            $batches = Batch::where('product_id', $product->id)
                ->orderBy('expiration_date')
                ->get();

            if (in_array($index, $this->lowStock, true)) {
                // Todo el saldo en un solo lote y por debajo del mínimo.
                $remaining = random_int(1, (int) max(1, floor($product->min_stock * 0.6)));

                foreach ($batches as $position => $batch) {
                    $batch->update(['stock' => $position === 0 ? $remaining : 0]);
                }

                continue;
            }

            foreach ($batches as $batch) {
                // Un lote en cero no se ve mal, pero un producto entero sin
                // existencias no se puede vender durante la demostración.
                if ($batch->stock <= 0) {
                    $batch->update(['stock' => random_int(6, 24)]);
                }
            }
        }
    }

    /**
     * Historial de los últimos 30 días. Las ventas descuentan del stock igual
     * que en el punto de venta (primero el lote que vence antes), así el
     * inventario que ve el cliente cuadra con los reportes.
     *
     * @param  \Illuminate\Support\Collection<int, Product>  $products
     */
    protected function seedSales($products): void
    {
        $methods = ['cash', 'cash', 'cash', 'card', 'card', 'transfer'];

        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $day = now()->subDays($daysAgo);

            // Domingo con menos movimiento, sábado con más.
            $salesToday = match ($day->dayOfWeek) {
                Carbon::SUNDAY => random_int(1, 3),
                Carbon::SATURDAY => random_int(5, 9),
                default => random_int(3, 7),
            };

            for ($i = 0; $i < $salesToday; $i++) {
                $at = $day->copy()->setTime(random_int(8, 19), random_int(0, 59), random_int(0, 59));

                $this->createSale($products, $methods[array_rand($methods)], $at);
            }
        }
    }

    /** @param  \Illuminate\Support\Collection<int, Product>  $products */
    protected function createSale($products, string $method, Carbon $at): void
    {
        $lines = [];

        foreach ($products->random(random_int(1, 4)) as $product) {
            $taken = $this->takeFromBatches($product, random_int(1, 3));

            if ($taken !== []) {
                $lines[] = [$product, $taken];
            }
        }

        if ($lines === []) {
            return;
        }

        $subtotal = 0.0;

        foreach ($lines as [$product, $taken]) {
            $subtotal += array_sum($taken) * (float) $product->selling_price;
        }

        $subtotal = round($subtotal, 2);

        // Un descuento ocasional, para que el reporte no muestre siempre cero.
        $discount = random_int(1, 10) === 1 ? round($subtotal * 0.05, 2) : 0.0;
        $tax = round(($subtotal - $discount) * (self::TAX_RATE / 100), 2);
        $total = round($subtotal - $discount + $tax, 2);

        // En efectivo se paga con billete redondo; con tarjeta, el monto exacto.
        $paid = $method === 'cash'
            ? (float) (ceil($total / 5000) * 5000)
            : $total;

        $sale = Sale::create([
            'invoice_number' => 'TMP-'.uniqid(),
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'paid_amount' => $paid,
            'change_amount' => round($paid - $total, 2),
            'payment_method' => $method,
            'user_id' => null,
        ]);

        // El consecutivo del POS se deriva del id, así que se fija después.
        $sale->forceFill([
            'invoice_number' => 'FAC-'.str_pad((string) $sale->id, 8, '0', STR_PAD_LEFT),
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        foreach ($lines as [$product, $taken]) {
            foreach ($taken as $batchId => $quantity) {
                // Las marcas de tiempo no son asignables en masa; se fuerzan
                // para que el detalle quede fechado igual que su venta.
                (new SaleDetail([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'batch_id' => $batchId,
                    'quantity' => $quantity,
                    'unit_price' => $product->selling_price,
                    'subtotal' => round($quantity * (float) $product->selling_price, 2),
                ]))->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
            }
        }
    }

    /**
     * Descuenta del lote que vence primero, igual que PosComponent. Devuelve
     * las unidades tomadas por lote, o un arreglo vacío si no había stock.
     *
     * @return array<int, int>
     */
    protected function takeFromBatches(Product $product, int $quantity): array
    {
        $taken = [];

        $batches = Batch::where('product_id', $product->id)
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->orderBy('expiration_date')
            ->get();

        foreach ($batches as $batch) {
            if ($quantity <= 0) {
                break;
            }

            $units = min($quantity, $batch->stock);

            $batch->decrement('stock', $units);

            $taken[$batch->id] = $units;
            $quantity -= $units;
        }

        return $taken;
    }
}
