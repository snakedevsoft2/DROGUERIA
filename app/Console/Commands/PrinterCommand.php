<?php

namespace App\Console\Commands;

use App\Services\ReceiptPrinter;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnóstico de la tiquetera. Lo usa el instalador después de compartir la
 * impresora, y sirve de primera parada cuando el cajero llama diciendo que
 * "no imprime".
 */
class PrinterCommand extends Command
{
    protected $signature = 'drogueria:impresora
                            {--listar : Muestra las impresoras instaladas en Windows}';

    protected $description = 'Prueba la impresora térmica imprimiendo un tiquete de diagnóstico';

    public function handle(ReceiptPrinter $printer): int
    {
        if ($this->option('listar')) {
            return $this->listPrinters();
        }

        $name = trim((string) config('drogueria.printer.connector'));

        $this->line('');
        $this->line('  Impresora : '.($name !== '' ? $name : '(sin configurar)'));
        $this->line('  Perfil    : '.config('drogueria.printer.profile'));
        $this->line('  Columnas  : '.config('drogueria.printer.columns').'  (42 = rollo 80mm, 32 = rollo 58mm)');
        $this->line('  Corte     : '.(config('drogueria.printer.cut') ? 'sí' : 'no'));
        $this->line('');

        if (! $printer->isConfigured()) {
            $this->error('No hay impresora configurada.');
            $this->line('');
            $this->line('  1. Comparta la impresora en Windows con un nombre simple, p. ej. TIQUETERA.');
            $this->line('  2. Escriba ese nombre en PRINTER_NAME dentro del archivo .env');
            $this->line('  3. Vuelva a ejecutar: php artisan drogueria:impresora');
            $this->line('');
            $this->line('  Para ver las impresoras instaladas: php artisan drogueria:impresora --listar');
            $this->line('');

            return self::FAILURE;
        }

        $this->info('Enviando tiquete de prueba...');

        try {
            $printer->test();
        } catch (Throwable $e) {
            $this->line('');
            $this->error($e->getMessage());
            $this->line('');

            return self::FAILURE;
        }

        $this->line('');
        $this->info('Listo. Si salió el papel, la impresora quedó configurada.');
        $this->line('Revise en el tiquete que las tildes se lean bien y que la regla');
        $this->line('de números termine justo en el borde del papel.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Las impresoras compartidas son las únicas a las que escpos-php les
     * puede mandar bytes crudos, así que el listado marca cuáles lo están.
     */
    protected function listPrinters(): int
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->error('El listado de impresoras sólo funciona en Windows.');

            return self::FAILURE;
        }

        $script = 'Get-Printer | Select-Object Name,Shared,ShareName | ConvertTo-Json -Compress';

        exec('powershell -NoProfile -Command "'.$script.'" 2>&1', $output, $status);

        $printers = json_decode(implode('', $output), true);

        if ($status !== 0 || ! is_array($printers)) {
            $this->error('No se pudo consultar la lista de impresoras de Windows.');

            return self::FAILURE;
        }

        // Con una sola impresora ConvertTo-Json devuelve el objeto pelado.
        if (isset($printers['Name'])) {
            $printers = [$printers];
        }

        $this->line('');
        $this->table(
            ['Impresora', 'Compartida', 'Nombre para PRINTER_NAME'],
            array_map(fn ($p) => [
                $p['Name'] ?? '',
                ! empty($p['Shared']) ? 'sí' : 'NO',
                $p['ShareName'] ?? '—',
            ], $printers)
        );
        $this->line('');
        $this->line('  Use en PRINTER_NAME el valor de la última columna.');
        $this->line('  Si la impresora no está compartida, compártala primero:');
        $this->line('  Panel de control > Dispositivos e impresoras > clic derecho >');
        $this->line('  Propiedades de impresora > Compartir.');
        $this->line('');

        return self::SUCCESS;
    }
}
