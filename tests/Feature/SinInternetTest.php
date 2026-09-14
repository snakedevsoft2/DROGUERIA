<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El punto de venta corre en el equipo de la droguería y tiene que funcionar
 * con el internet caído. Ninguna pantalla puede pedir estilos, scripts,
 * fuentes o imágenes a un servidor de afuera: si lo hiciera, sin conexión
 * se vería rota o sin funcionar.
 */
class SinInternetTest extends TestCase
{
    use RefreshDatabase;

    /** Recursos que cargan solos al abrir la página (no los enlaces a los que se hace clic). */
    protected function recursosExternos(string $html): array
    {
        preg_match_all(
            '/<(?:script|img|iframe|source|audio|video)\b[^>]*\bsrc\s*=\s*["\']([^"\']+)["\']'
            .'|<link\b[^>]*\bhref\s*=\s*["\']([^"\']+)["\']'
            .'|url\(\s*["\']?([^"\')]+)["\']?\s*\)'
            .'|@import\s+["\']([^"\']+)["\']/i',
            $html,
            $coincidencias,
            PREG_SET_ORDER
        );

        $propio = parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return collect($coincidencias)
            ->map(fn ($m) => collect(array_slice($m, 1))->first(fn ($v) => $v !== ''))
            ->filter(fn ($url) => $url && preg_match('#^(https?:)?//#i', $url))
            ->reject(fn ($url) => in_array(parse_url($url, PHP_URL_HOST), [$propio, 'localhost', '127.0.0.1'], true))
            ->values()
            ->all();
    }

    protected function venta(): Sale
    {
        $product = Product::create([
            'barcode' => '7701234567890',
            'name' => 'Amoxicilina 500 mg',
            'presentation' => 'Caja x 12',
            'units_per_package' => 12,
            'cost_price' => 4000,
            'selling_price' => 1000,
            'min_stock' => 5,
            'requires_prescription' => true,
            'is_active' => true,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'L-0001',
            'expiration_date' => now()->addYear()->toDateString(),
            'stock' => 10,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'invoice_number' => 'FAC-00000001',
            'subtotal' => 2000, 'tax' => 0, 'discount' => 0, 'total' => 2000,
            'paid_amount' => 2000, 'change_amount' => 0, 'payment_method' => 'cash',
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'batch_id' => $batch->id,
            'quantity' => 2,
            'unit_price' => 1000,
            'subtotal' => 2000,
        ]);

        return $sale;
    }

    public function test_ninguna_pantalla_depende_de_internet(): void
    {
        $sale = $this->venta();

        $paginas = [
            'Punto de venta' => route('pos'),
            'Inventario' => route('inventory'),
            'Lista de precios' => route('prices'),
            'Reportes' => route('reports'),
            'Comprobante' => route('receipt', $sale),
        ];

        foreach ($paginas as $nombre => $url) {
            $respuesta = $this->get($url)->assertOk();

            $this->assertSame(
                [],
                $this->recursosExternos($respuesta->getContent()),
                "La pantalla «{$nombre}» carga recursos de internet."
            );
        }
    }

    public function test_los_estilos_compilados_no_piden_nada_a_internet(): void
    {
        $manifiesto = public_path('build/manifest.json');

        $this->assertFileExists($manifiesto, 'Falta compilar los estilos con npm run build.');

        foreach (json_decode(file_get_contents($manifiesto), true) as $entrada) {
            foreach (array_merge([$entrada['file']], $entrada['css'] ?? []) as $archivo) {
                $this->assertSame(
                    [],
                    $this->recursosExternos(file_get_contents(public_path('build/'.$archivo))),
                    "El archivo compilado {$archivo} carga recursos de internet."
                );
            }
        }
    }

    public function test_el_detector_encuentra_recursos_de_afuera(): void
    {
        // Control del propio detector: si dejara de ver estos, la prueba de
        // arriba pasaría aunque las pantallas dependieran de internet.
        $html = '<link href="https://fonts.bunny.net/css?family=x" rel="stylesheet">'
            .'<script src="https://cdn.example.com/lib.js"></script>'
            .'<style>@font-face{src:url(//fonts.gstatic.com/a.woff2)}</style>'
            .'<script src="http://localhost/livewire/livewire.js"></script>'
            .'<a href="https://laravel.com/docs">enlace</a>';

        $this->assertSame([
            'https://fonts.bunny.net/css?family=x',
            'https://cdn.example.com/lib.js',
            '//fonts.gstatic.com/a.woff2',
        ], $this->recursosExternos($html));
    }
}
