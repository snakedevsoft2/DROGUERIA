<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deja la droguería en cero para empezar a cargar datos reales.
 *
 * Borra el contenido de las tablas del negocio sin tocar el esquema, así que
 * no hay que volver a migrar. Los contadores vuelven a 1: la primera venta
 * después de vaciar arranca otra vez en la factura número uno.
 */
class WipeCommand extends Command
{
    protected $signature = 'drogueria:vaciar
                            {--ventas : Borrar sólo las ventas y conservar el inventario}
                            {--force : No pedir confirmación}';

    protected $description = 'Vacía los datos de la droguería (inventario y ventas)';

    /** Orden de borrado: primero lo que apunta, después lo apuntado. */
    protected const TABLES = ['sale_details', 'sales', 'batches', 'products'];

    public function handle(): int
    {
        $tables = $this->option('ventas')
            ? ['sale_details', 'sales']
            : self::TABLES;

        $connection = DB::connection();

        $this->line('');
        $this->line('  Base de datos: <options=bold>'.$connection->getDriverName().'</> — '.$this->describeDatabase());
        $this->line('');

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Falta la tabla {$table}: ¿corriste las migraciones?");

                return self::FAILURE;
            }

            $this->line(sprintf('  %-14s %s registros', $table, number_format(DB::table($table)->count())));
        }

        $this->line('');

        if (! $this->option('force') && ! $this->confirm('Se borran definitivamente. ¿Continuar?')) {
            $this->comment('Cancelado. No se borró nada.');

            return self::SUCCESS;
        }

        $this->truncate($tables);

        $this->info($this->option('ventas')
            ? 'Ventas borradas. El inventario quedó intacto.'
            : 'Base vacía. El inventario y las ventas arrancan de cero.');

        return self::SUCCESS;
    }

    /**
     * TRUNCATE reinicia las secuencias de Postgres de una vez; en el resto se
     * borra fila por fila con las llaves foráneas apagadas y se reinician los
     * contadores a mano.
     */
    protected function truncate(array $tables): void
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $quoted = implode(', ', array_map(fn ($table) => '"'.$table.'"', $tables));

            DB::statement("TRUNCATE TABLE {$quoted} RESTART IDENTITY CASCADE");

            return;
        }

        Schema::disableForeignKeyConstraints();

        foreach ($tables as $table) {
            DB::table($table)->delete();
        }

        if ($driver === 'sqlite' && Schema::hasTable('sqlite_sequence')) {
            DB::table('sqlite_sequence')->whereIn('name', $tables)->delete();
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            foreach ($tables as $table) {
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }
        }

        Schema::enableForeignKeyConstraints();
    }

    /** Nombre o host de la base, para que se vea contra cuál se va a correr. */
    protected function describeDatabase(): string
    {
        $config = DB::connection()->getConfig();

        if (! empty($config['url'])) {
            return (string) (parse_url($config['url'], PHP_URL_HOST) ?: 'conexión por URL');
        }

        if (! empty($config['host'])) {
            return $config['host'].'/'.($config['database'] ?? '');
        }

        return (string) ($config['database'] ?? 'sin nombre');
    }
}
