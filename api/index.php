<?php

/*
|--------------------------------------------------------------------------
| Punto de entrada para Vercel
|--------------------------------------------------------------------------
|
| Vercel sólo ejecuta archivos PHP que estén dentro de /api, así que este
| archivo se limita a delegar en el front controller de Laravel. Toda la
| aplicación sigue arrancando desde public/index.php como siempre.
|
| El disco de la función es de sólo lectura salvo /tmp, y esa carpeta se
| borra en cada arranque en frío. Laravel espera que los directorios de
| storage existan antes de resolver el servicio de vistas, así que hay que
| crearlos aquí. Si faltan, el contenedor falla con
| "Target class [view] does not exist".
|
*/

foreach ([
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    '/tmp/bootstrap/cache',
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

require __DIR__ . '/../public/index.php';