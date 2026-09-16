<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function __invoke(Request $request, Sale $sale): View
    {
        $sale->load(['details.product', 'user']);

        // Dos presentaciones del mismo comprobante: la tirilla de 80mm para la
        // tiquetera y una factura en hoja carta para la impresora de oficina.
        $sheet = $request->query('formato') === 'carta';

        return view($sheet ? 'pdf.invoice-sheet' : 'pdf.invoice', [
            'sale' => $sale,
            'lines' => $this->lines($sale),
            // Sólo se imprime solo si se pide explícitamente (?print=1).
            'autoPrint' => $request->boolean('print'),
            // Dentro del modal del POS la barra de botones sobra: el modal
            // tiene los suyos.
            'embedded' => $request->boolean('embed'),
            'logo' => $this->logoUrl(),
        ]);
    }

    /**
     * Prefiere la variante en escala de grises; si no existe cae a la de
     * pantalla, y si tampoco está el comprobante imprime sólo el nombre.
     */
    protected function logoUrl(): ?string
    {
        foreach ([config('drogueria.logo_print'), config('drogueria.logo')] as $path) {
            if ($path && is_file(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    }

    /**
     * El descuento de stock es FEFO, así que un mismo producto puede quedar
     * repartido en varios sale_details (uno por lote). En el comprobante el
     * cliente debe ver una sola línea por producto y precio.
     */
    protected function lines(Sale $sale)
    {
        return $sale->details
            ->groupBy(fn ($detail) => $detail->product_id.'-'.$detail->unit_price)
            ->map(fn ($group) => (object) [
                'name' => $group->first()->product?->name
                    ?? $group->first()->product_name
                    ?? 'Producto eliminado',
                'presentation' => $group->first()->product?->presentation,
                'unit_price' => (float) $group->first()->unit_price,
                'quantity' => (int) $group->sum('quantity'),
                'subtotal' => (float) $group->sum('subtotal'),
            ])
            ->values();
    }
}
