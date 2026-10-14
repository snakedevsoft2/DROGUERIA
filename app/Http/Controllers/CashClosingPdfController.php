<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\Sale;
use App\Services\SalesSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Los PDF del cierre de caja: el de un cierre, el historial de cierres y el
 * informe de ventas y ganancias de un rango de fechas.
 *
 * Se entregan como descarga y no se abren en otra ventana: el programa de
 * escritorio no tiene pestañas y así la pantalla se queda donde estaba.
 */
class CashClosingPdfController extends Controller
{
    public function closing(CashClosing $closing): Response
    {
        $sales = Sale::whereBetween('created_at', [$closing->opened_at, $closing->closed_at])
            ->orderBy('created_at')
            ->get();

        return $this->pdf('pdf.cierre', [
            'closing' => $closing,
            'sales' => $sales,
        ], "cierre-{$closing->number}.pdf");
    }

    public function history(Request $request): Response
    {
        $periodo = $this->range($request);

        $closings = CashClosing::whereBetween('closed_at', [$periodo->from, $periodo->to])
            ->orderBy('closed_at')
            ->get();

        return $this->pdf('pdf.historial-cierres', [
            'from' => $periodo->from,
            'to' => $periodo->to,
            'closings' => $closings,
        ], 'historial-cierres-'.$this->fileRange($periodo).'.pdf');
    }

    public function report(Request $request): Response
    {
        $periodo = $this->range($request);

        return $this->pdf('pdf.informe', [
            'from' => $periodo->from,
            'to' => $periodo->to,
            'totals' => $periodo->totals(),
            'payments' => $periodo->byPaymentMethod(),
            'daily' => $periodo->daily(),
            'topProducts' => $periodo->topProducts(15),
            'closings' => CashClosing::whereBetween('closed_at', [$periodo->from, $periodo->to])
                ->orderBy('closed_at')
                ->get(),
        ], 'informe-ventas-'.$this->fileRange($periodo).'.pdf');
    }

    /** Días completos; sin fechas, el mes en curso hasta hoy. */
    protected function range(Request $request): SalesSummary
    {
        $data = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date'],
        ]);

        return SalesSummary::days(
            $data['desde'] ?? now()->startOfMonth()->toDateString(),
            $data['hasta'] ?? now()->toDateString(),
        );
    }

    protected function fileRange(SalesSummary $periodo): string
    {
        $desde = $periodo->from->toDateString();
        $hasta = $periodo->to->toDateString();

        return $desde === $hasta ? $desde : "{$desde}-a-{$hasta}";
    }

    protected function pdf(string $view, array $data, string $file): Response
    {
        $pdf = Pdf::loadView($view, $data + [
            'store' => config('drogueria'),
            'logo' => $this->logo(),
            'generatedAt' => now(),
            'pesos' => fn ($valor) => ($valor < 0 ? '-' : '').'$'.number_format(abs((float) $valor), 0, ',', '.'),
        ])
            ->setPaper('letter')
            // Sólo las letras usadas: con la fuente entera cada PDF pesa casi 1 MB.
            ->setOption('isFontSubsettingEnabled', true);

        // "Página 1 de 3": el total de páginas sólo se sabe al terminar de
        // armar el documento, así que lo escribe dompdf y no la plantilla.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $dompdf->getCanvas()->page_text(
            514, 758, 'Página {PAGE_NUM} de {PAGE_COUNT}',
            $dompdf->getFontMetrics()->getFont('DejaVu Sans'), 7, [0.58, 0.64, 0.72]
        );

        return $pdf->download($file);
    }

    /**
     * El logo va incrustado: dompdf no tiene que salir a buscar archivos y no
     * importa en qué carpeta esté instalado el programa.
     */
    protected function logo(): ?string
    {
        $path = config('drogueria.logo');

        if (! $path || ! is_file(public_path($path))) {
            return null;
        }

        $archivo = public_path($path);

        return 'data:'.(mime_content_type($archivo) ?: 'image/png').';base64,'.base64_encode(file_get_contents($archivo));
    }
}
