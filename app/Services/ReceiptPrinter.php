<?php

namespace App\Services;

use App\Models\Sale;
use Exception;
use Illuminate\Support\Collection;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer;
use RuntimeException;

/**
 * Envía el comprobante directamente a la tiquetera térmica por ESC/POS.
 *
 * El comprobante en HTML (resources/views/pdf/invoice.blade.php) sigue
 * existiendo como vista previa en pantalla y como plan B si la impresora
 * falla; esta clase es la que gasta papel.
 */
class ReceiptPrinter
{
    /** Ancho útil en caracteres de la fuente A. */
    protected int $columns;

    public function __construct()
    {
        // Por debajo de 24 columnas no cabe ni "TOTAL" con su cifra.
        $this->columns = max(24, (int) config('drogueria.printer.columns', 42));
    }

    /** ¿Hay una impresora configurada a la que valga la pena intentar? */
    public function isConfigured(): bool
    {
        return (bool) config('drogueria.printer.enabled')
            && trim((string) config('drogueria.printer.connector')) !== '';
    }

    /**
     * Imprime el comprobante de una venta.
     *
     * @throws RuntimeException si no hay impresora configurada o falla el envío.
     */
    public function print(Sale $sale): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'No hay una impresora configurada. Revise PRINTER_NAME en el archivo de ajustes.'
            );
        }

        $sale->loadMissing(['details.product', 'user']);

        $printer = null;

        try {
            $printer = new Printer($this->connector(), $this->profile());

            $this->compose($printer, $sale, $this->lines($sale));
        } catch (Exception $e) {
            // El destructor del conector avisa por E_USER_NOTICE si el búfer
            // quedó sin vaciar; cerrarlo aquí evita ese ruido en el log.
            $this->closeQuietly($printer);

            throw new RuntimeException($this->friendlyError($e), 0, $e);
        }

        // El close() real va fuera del try anterior: es el que manda el
        // trabajo a la cola de impresión, y su error tiene otra causa que el
        // de armar el tiquete.
        try {
            $printer->close();
        } catch (Exception $e) {
            throw new RuntimeException($this->friendlyError($e), 0, $e);
        }
    }

    /**
     * Tiquete de prueba para la instalación: confirma el nombre de la
     * impresora, el ancho del rollo y que las tildes salgan bien.
     *
     * @throws RuntimeException
     */
    public function test(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'No hay una impresora configurada. Revise PRINTER_NAME en el archivo de ajustes.'
            );
        }

        $printer = null;

        try {
            $printer = new Printer($this->connector(), $this->profile());

            $printer->initialize();
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text("PRUEBA DE IMPRESION\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->text($this->wrap(config('drogueria.name'))."\n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);

            $printer->text($this->rule());
            $printer->text($this->columnsLine('Impresora:', (string) config('drogueria.printer.connector')));
            $printer->text($this->columnsLine('Columnas:', (string) $this->columns));
            $printer->text($this->columnsLine('Fecha:', now()->format('d/m/Y H:i')));
            $printer->text($this->rule());

            // Si alguna de estas dos líneas sale con símbolos raros, la página
            // de códigos del perfil no es la correcta para la impresora.
            $printer->text($this->wrap('Acentos: áéíóú ÁÉÍÓÚ ñÑ üÜ ¿? ¡!')."\n");
            $printer->text($this->wrap('Cifras: '.$this->money(1234567.89))."\n");

            // Regla de ancho: la última columna debe quedar justo en el borde
            // del papel. Si se dobla, sobran columnas en la configuración.
            $printer->text($this->rule());
            $printer->text($this->ruler());
            $printer->text($this->rule());

            $printer->feed(2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text($this->wrap('Si lee esto, la impresora quedó lista.').PHP_EOL);

            if (config('drogueria.printer.cut')) {
                $printer->cut();
            } else {
                $printer->feed(4);
            }
        } catch (Exception $e) {
            $this->closeQuietly($printer);

            throw new RuntimeException($this->friendlyError($e), 0, $e);
        }

        try {
            $printer->close();
        } catch (Exception $e) {
            throw new RuntimeException($this->friendlyError($e), 0, $e);
        }
    }

    /** Regla '1234567890123...' del ancho exacto del rollo. */
    protected function ruler(): string
    {
        $ruler = '';

        for ($i = 1; $i <= $this->columns; $i++) {
            $ruler .= $i % 10;
        }

        return $ruler."\n";
    }

    protected function connector(): PrintConnector
    {
        return new WindowsPrintConnector(trim((string) config('drogueria.printer.connector')));
    }

    protected function profile(): CapabilityProfile
    {
        try {
            return CapabilityProfile::load((string) config('drogueria.printer.profile', 'default'));
        } catch (Exception $e) {
            return CapabilityProfile::load('default');
        }
    }

    /** Arma el tiquete completo sobre una impresora ya abierta. */
    protected function compose(Printer $printer, Sale $sale, Collection $lines): void
    {
        $printer->initialize();

        $this->header($printer);
        $this->saleData($printer, $sale);
        $this->items($printer, $lines);
        $this->totals($printer, $sale);
        $this->footer($printer, $sale, $lines);

        if (config('drogueria.printer.cash_drawer') && $sale->payment_method === 'cash') {
            $printer->pulse();
        }

        if (config('drogueria.printer.cut')) {
            $printer->cut();
        } else {
            // Sin guillotina el papel se arranca a mano por encima del
            // cabezal, y sin este avance el corte parte la última línea.
            $printer->feed(4);
        }
    }

    protected function header(Printer $printer): void
    {
        $store = config('drogueria');

        $printer->setJustification(Printer::JUSTIFY_LEFT);

        if (! $this->logo($printer)) {
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text($this->wrap(mb_strtoupper($store['name']))."\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
        }

        foreach (['legal_name', 'nit', 'address', 'city', 'phone', 'email'] as $field) {
            $value = trim((string) ($store[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $value = match ($field) {
                'nit' => "NIT: {$value}",
                'phone' => "Cel: {$value}",
                default => $value,
            };

            $printer->text($this->wrap($value)."\n");
        }

        $printer->feed();
        $printer->setEmphasis(true);
        $printer->text($this->wrap('COMPROBANTE DE VENTA')."\n");
        $printer->setEmphasis(false);
    }

    /**
     * Imprime el logo. Devuelve false si no hay imagen utilizable, para que
     * el encabezado caiga al nombre en texto.
     */
    protected function logo(Printer $printer): bool
    {
        if (! config('drogueria.printer.logo')) {
            return false;
        }

        // EscposImage necesita gd o imagick; en un PHP pelado no están.
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            return false;
        }

        foreach ([config('drogueria.logo_print'), config('drogueria.logo')] as $path) {
            if (! $path || ! is_file(public_path($path))) {
                continue;
            }

            try {
                $printer->graphics(EscposImage::load(public_path($path), false));

                return true;
            } catch (Exception $e) {
                // Imagen corrupta o en un formato que gd no abre: mejor un
                // tiquete con el nombre en texto que una venta sin tiquete.
                report($e);
            }
        }

        return false;
    }

    protected function saleData(Printer $printer, Sale $sale): void
    {
        $methods = ['cash' => 'EFECTIVO', 'card' => 'TARJETA', 'transfer' => 'TRANSFERENCIA'];

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($sale->invoice_number."\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->feed();

        $printer->text($this->columnsLine('Fecha:', $sale->created_at->format('d/m/Y H:i')));
        $printer->text($this->columnsLine(
            'Pago:',
            $methods[$sale->payment_method] ?? mb_strtoupper((string) $sale->payment_method)
        ));

        if ($sale->user) {
            $printer->text($this->columnsLine('Atendió:', $sale->user->name));
        }
    }

    protected function items(Printer $printer, Collection $lines): void
    {
        $printer->feed();

        foreach ($lines as $line) {
            $name = $line->name;

            if ($line->presentation) {
                $name .= " ({$line->presentation})";
            }

            // El nombre ocupa sus propias líneas: en un rollo angosto no cabe
            // junto a la cantidad y el total sin quedar recortado.
            $printer->text($this->wrap($name)."\n");

            // Sólo cantidad y total: el precio unitario repite la cifra en
            // los renglones de una unidad y estorba en un rollo angosto.
            $printer->text($this->columnsLine(
                '  '.$line->quantity,
                $this->money($line->subtotal)
            ));
        }
    }

    protected function totals(Printer $printer, Sale $sale): void
    {
        $printer->feed();

        // Sin descuento el subtotal repite el total: en un tiquete angosto
        // esa línea sólo gasta papel.
        if ((float) $sale->discount > 0) {
            $printer->text($this->columnsLine('Subtotal', $this->money($sale->subtotal)));
            $printer->text($this->columnsLine('Descuento', '-'.$this->money($sale->discount)));
            $printer->feed();
        }

        // El total va a doble alto: es lo único que el cliente busca de lejos.
        // A doble ancho no cabría la cifra, así que sólo se estira en vertical.
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 2);
        $printer->text($this->columnsLine('TOTAL', $this->money($sale->total)));
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);

        $printer->feed();

        // Lo recibido sólo interesa cuando hay vuelto que contar.
        if ((float) $sale->change_amount > 0 || $sale->payment_method === 'cash') {
            $printer->text($this->columnsLine('Recibido', $this->money($sale->paid_amount)));

            $printer->setEmphasis(true);
            $printer->text($this->columnsLine('Cambio', $this->money($sale->change_amount)));
            $printer->setEmphasis(false);
        }
    }

    protected function footer(Printer $printer, Sale $sale, Collection $lines): void
    {
        $receipt = config('drogueria.receipt');

        $printer->feed();
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->text($this->wrap('Artículos: '.$lines->sum('quantity'))."\n");

        if (! empty($receipt['legal_note'])) {
            $printer->feed();
            $printer->text($this->wrap($receipt['legal_note'])."\n");
        }

        if (! empty($receipt['footer'])) {
            $printer->feed();
            $printer->setEmphasis(true);
            $printer->text($this->wrap($receipt['footer'])."\n");
            $printer->setEmphasis(false);
        }

        $this->barcode($printer, $sale);

        $printer->setJustification(Printer::JUSTIFY_LEFT);
    }

    protected function barcode(Printer $printer, Sale $sale): void
    {
        if (! config('drogueria.printer.barcode')) {
            return;
        }

        // CODE39 sólo admite mayúsculas, dígitos y unos pocos símbolos; el
        // consecutivo 'FAC-00000001' entra tal cual.
        $code = mb_strtoupper($sale->invoice_number);

        if (! preg_match('/^[A-Z0-9\-. $\/+%]+$/', $code)) {
            return;
        }

        try {
            $printer->feed();
            $printer->setBarcodeHeight(50);
            $printer->setBarcodeTextPosition(Printer::BARCODE_TEXT_BELOW);
            $printer->barcode($code, Printer::BARCODE_CODE39);
        } catch (Exception $e) {
            // Una impresora sin códigos de barras no debe tumbar el resto del
            // tiquete, que a estas alturas ya está armado.
            report($e);
        }
    }

    /**
     * El descuento de stock es FEFO, así que un producto puede quedar
     * repartido en varios sale_details. El cliente ve una línea por producto.
     *
     * Mismo agrupamiento que ReceiptController, para que el papel y la vista
     * previa en pantalla digan exactamente lo mismo.
     */
    protected function lines(Sale $sale): Collection
    {
        return $sale->details
            ->groupBy(fn ($detail) => $detail->product_id.'-'.$detail->unit_price)
            ->map(fn ($group) => (object) [
                'name' => $group->first()->product?->name ?? 'Producto eliminado',
                'presentation' => $group->first()->product?->presentation,
                'unit_price' => (float) $group->first()->unit_price,
                'quantity' => (int) $group->sum('quantity'),
                'subtotal' => (float) $group->sum('subtotal'),
            ])
            ->values();
    }

    /** Mismo formato de moneda que el comprobante: miles con punto, sin centavos. */
    protected function money(float|int|string $value): string
    {
        return '$'.number_format((float) $value, 0, ',', '.');
    }

    protected function rule(string $char = '-'): string
    {
        return str_repeat($char, $this->columns)."\n";
    }

    /**
     * Etiqueta a la izquierda y cifra a la derecha, rellenando el medio.
     * Si no caben las dos, se recorta la etiqueta: la cifra es lo que el
     * cliente revisa.
     */
    protected function columnsLine(string $left, string $right): string
    {
        $right = $this->clip($right, $this->columns);
        $space = $this->columns - mb_strlen($right);

        $left = $this->clip($left, max(0, $space - 1));

        return $left.str_repeat(' ', max(1, $space - mb_strlen($left))).$right."\n";
    }

    /**
     * Parte un texto largo en líneas que quepan en el rollo.
     *
     * wordwrap() cuenta bytes y en UTF-8 cada tilde vale dos, así que cortaría
     * las líneas antes de tiempo: hay que envolver palabra por palabra.
     */
    protected function wrap(string $text): string
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', trim($text)) ?: [] as $word) {
            if ($word === '') {
                continue;
            }

            // Una palabra más ancha que el rollo (un código largo) se parte en
            // trozos; si no, desbordaría la línea.
            while (mb_strlen($word) > $this->columns) {
                if ($current !== '') {
                    $lines[] = $current;
                    $current = '';
                }

                $lines[] = mb_substr($word, 0, $this->columns);
                $word = mb_substr($word, $this->columns);
            }

            $candidate = $current === '' ? $word : $current.' '.$word;

            if (mb_strlen($candidate) > $this->columns) {
                $lines[] = $current;
                $current = $word;

                continue;
            }

            $current = $candidate;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return implode("\n", $lines);
    }

    protected function clip(string $text, int $length): string
    {
        return mb_strlen($text) > $length ? mb_substr($text, 0, $length) : $text;
    }

    protected function closeQuietly(?Printer $printer): void
    {
        if (! $printer) {
            return;
        }

        try {
            $printer->close();
        } catch (Exception $e) {
            // Ya venimos de un error; este no aporta nada.
        }
    }

    /**
     * Los errores de escpos-php hablan de comandos de Windows que no le dicen
     * nada al cajero. Se traducen a la causa real más probable.
     */
    protected function friendlyError(Exception $e): string
    {
        $message = $e->getMessage();
        $name = trim((string) config('drogueria.printer.connector'));

        if (str_contains($message, 'not a valid printer name')) {
            return "El nombre de impresora \"{$name}\" no es válido: use sólo letras, números y guiones, sin tildes ni puntos.";
        }

        // Cuando el recurso compartido no existe, escpos-php no llega ni a
        // intentar imprimir: falla el copy() al UNC y el aviso de PHP se
        // cuela tal cual en el mensaje.
        if (str_contains($message, 'Failed to open stream')
            || str_contains($message, 'Failed to copy file to printer')) {
            return "No se encontró la impresora \"{$name}\" en este equipo. "
                .'Revise que esté compartida con ese nombre exacto '
                .'(use la herramienta "Configurar impresora").';
        }

        if (str_contains($message, 'Failed to print')) {
            return "No se pudo enviar el tiquete a \"{$name}\". Verifique que la impresora esté encendida, con papel y con la tapa cerrada.";
        }

        return 'No se pudo imprimir: '.$message;
    }
}
