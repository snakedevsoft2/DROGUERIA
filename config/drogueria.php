<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos de la droguería
    |--------------------------------------------------------------------------
    |
    | Encabezado y pie de los comprobantes de venta. Se pueden sobreescribir
    | desde el .env sin tocar este archivo.
    |
    */

    // env() y no config('app.name'): dentro de un archivo de configuración
    // eso dependería del orden alfabético en que Laravel los carga.
    'name' => env('STORE_NAME', env('APP_NAME', 'Droguería')),
    'legal_name' => env('STORE_LEGAL_NAME', ''),
    'nit' => env('STORE_NIT', ''),
    'address' => env('STORE_ADDRESS', ''),
    'city' => env('STORE_CITY', ''),
    'phone' => env('STORE_PHONE', ''),
    'email' => env('STORE_EMAIL', ''),

    /*
    |--------------------------------------------------------------------------
    | Logo
    |--------------------------------------------------------------------------
    |
    | Rutas relativas a public/. Dejar en blanco para mostrar sólo el nombre.
    | La variante "print" va en escala de grises y con más contraste: la
    | térmica reduce todo a 1 bit y el color sale sucio.
    |
    */

    'logo' => env('STORE_LOGO', 'images/logo.png'),
    'logo_print' => env('STORE_LOGO_PRINT', 'images/logo-print.png'),

    /*
    |--------------------------------------------------------------------------
    | Comprobante
    |--------------------------------------------------------------------------
    */

    'receipt' => [
        // Ancho del rollo: 80mm o 58mm.
        'width' => env('RECEIPT_WIDTH', '80mm'),

        'footer' => env('RECEIPT_FOOTER', '¡Gracias por su compra!'),

        'legal_note' => env(
            'RECEIPT_LEGAL_NOTE',
            'Los medicamentos no tienen devolución. Conserve este comprobante.'
        ),
    ],

];
