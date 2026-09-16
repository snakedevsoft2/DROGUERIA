<?php

/*
|--------------------------------------------------------------------------
| Punto de entrada para Vercel
|--------------------------------------------------------------------------
|
| El disco de la función es de sólo lectura salvo /tmp. Laravel escribe en
| bootstrap/cache durante el arranque (packages.php, services.php), así que
| hay que redirigir esas rutas a /tmp con las variables APP_*_CACHE, que el
| framework lee antes de registrar cualquier servicio.
|
*/

$cache = '/tmp/bootstrap/cache';

foreach ([
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    $cache,
] as $dir) {
    if (! is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

foreach ([
    'APP_PACKAGES_CACHE' => $cache . '/packages.php',
    'APP_SERVICES_CACHE' => $cache . '/services.php',
    'APP_CONFIG_CACHE'   => $cache . '/config.php',
    'APP_ROUTES_CACHE'   => $cache . '/routes.php',
    'APP_EVENTS_CACHE'   => $cache . '/events.php',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

// Diagnóstico: /?diag=1 responde sin arrancar Laravel.
if (isset($_GET['diag'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP: ' . PHP_VERSION . "\n";
    echo 'pdo_pgsql: ' . (extension_loaded('pdo_pgsql') ? 'si' : 'NO') . "\n";
    echo '/tmp escribible: ' . (is_writable('/tmp') ? 'si' : 'NO') . "\n";

    foreach (['bootstrap/cache', 'storage', 'vendor', 'public/build'] as $p) {
        $full = __DIR__ . '/../' . $p;
        printf("%-16s existe:%-3s escribible:%s\n", $p,
            is_dir($full) ? 'si' : 'NO',
            is_writable($full) ? 'si' : 'no');
    }

    $url = getenv('DB_URL');
    if (! $url) {
        echo "DB_URL: NO DEFINIDA\n";
    } else {
        try {
            $u = parse_url($url);
            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
                $u['host'], $u['port'] ?? 5432, ltrim($u['path'], '/'));
            $pdo = new PDO($dsn, $u['user'], urldecode($u['pass'] ?? ''),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo 'DB: conectado, productos = '
                . $pdo->query('select count(*) from products')->fetchColumn() . "\n";
        } catch (Throwable $e) {
            echo 'DB ERROR: ' . $e->getMessage() . "\n";
        }
    }
    exit;
}

require __DIR__ . '/../public/index.php';