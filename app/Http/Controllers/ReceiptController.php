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
            'lines' => $sale->receiptLines(),
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
}
