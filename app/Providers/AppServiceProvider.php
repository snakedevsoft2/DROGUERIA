<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // "artisan serve" le quita al servidor de PHP las variables de entorno
        // que no estén en su lista, y la compara distinguiendo mayúsculas:
        // trae 'SYSTEMROOT', pero Windows la llama 'SystemRoot'. Sin ella,
        // Windows no inicia los sockets y PHP muere con "Failed to listen on
        // 127.0.0.1:8347 (reason: ?)" en cualquier puerto. Desde Git Bash no
        // pasa (exporta los nombres en mayúsculas); desde el icono, siempre.
        if (PHP_OS_FAMILY === 'Windows') {
            ServeCommand::$passthroughVariables = array_values(array_unique(array_merge(
                ServeCommand::$passthroughVariables,
                ['SystemRoot', 'windir', 'TEMP', 'TMP']
            )));
        }
    }
}
