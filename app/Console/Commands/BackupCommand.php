<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Copia de seguridad de la base de datos.
 *
 * En la droguería todo el negocio vive en un solo archivo SQLite, así que el
 * respaldo es literalmente copiarlo. Copiarlo "a mano" mientras el programa
 * está abierto sale corrupto: con WAL activo parte de los datos todavía está
 * en el archivo -wal. VACUUM INTO deja una copia consistente sin cerrar nada.
 */
class BackupCommand extends Command
{
    protected $signature = 'drogueria:respaldar
                            {--destino= : Carpeta donde dejar la copia (por defecto, respaldos/)}
                            {--conservar=30 : Cuántas copias mantener antes de borrar las más viejas}';

    protected $description = 'Crea una copia de seguridad de la base de datos';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('El respaldo automático sólo está previsto para SQLite.');

            return self::FAILURE;
        }

        $folder = $this->option('destino') ?: base_path('respaldos');

        if (! is_dir($folder) && ! mkdir($folder, 0777, true) && ! is_dir($folder)) {
            $this->error("No se pudo crear la carpeta de respaldos: {$folder}");

            return self::FAILURE;
        }

        $file = rtrim($folder, '\\/').DIRECTORY_SEPARATOR
            .'drogueria-'.now()->format('Y-m-d_His').'.sqlite';

        try {
            // VACUUM INTO falla si el archivo ya existe, cosa que aquí sólo
            // pasaría con dos respaldos en el mismo segundo.
            DB::statement('VACUUM INTO ?', [$file]);
        } catch (Throwable $e) {
            $this->error('No se pudo crear el respaldo: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Respaldo creado:');
        $this->line('  '.$file);
        $this->line('  '.$this->humanSize((int) filesize($file)));

        $deleted = $this->prune($folder, max(1, (int) $this->option('conservar')));

        if ($deleted > 0) {
            $this->line('');
            $this->line("  Se borraron {$deleted} respaldo(s) antiguo(s).");
        }

        $this->line('');
        $this->warn('Guarde una copia fuera de este equipo (USB o nube):');
        $this->warn('si el disco se daña, los respaldos se pierden con él.');

        return self::SUCCESS;
    }

    /** Deja sólo los N respaldos más recientes, para que no llenen el disco. */
    protected function prune(string $folder, int $keep): int
    {
        $files = glob(rtrim($folder, '\\/').DIRECTORY_SEPARATOR.'drogueria-*.sqlite') ?: [];

        if (count($files) <= $keep) {
            return 0;
        }

        // El nombre lleva la fecha en formato ordenable, así que basta con
        // ordenar alfabéticamente para tener los más viejos primero.
        sort($files);

        $deleted = 0;

        foreach (array_slice($files, 0, count($files) - $keep) as $old) {
            if (@unlink($old)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    protected function humanSize(int $bytes): string
    {
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }
}
