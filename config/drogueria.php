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
    | Impresora térmica (ESC/POS)
    |--------------------------------------------------------------------------
    |
    | Impresión directa a la tiquetera, sin pasar por el diálogo del navegador.
    | Probado sobre una Epson TM-T20II conectada por USB.
    |
    | 'connector' acepta tres formas:
    |   - Nombre del recurso compartido en Windows ....... TIQUETERA
    |   - Puerto local .................................. COM1 / LPT1
    |   - Ruta de red ................................... smb://EQUIPO/TIQUETERA
    |
    | En USB hay que compartir la impresora en Windows y usar el nombre del
    | recurso compartido: es la única vía por la que PHP puede escribirle
    | bytes crudos. El instalador lo hace automáticamente.
    |
    */

    'printer' => [

        // En false la venta se cierra igual y sólo queda imprimir desde el
        // navegador. Útil si un día la tiquetera se daña.
        'enabled' => env('PRINTER_ENABLED', true),

        'connector' => env('PRINTER_NAME', ''),

        // 'default' cubre las Epson (TM-T20II incluida) con sus 62 páginas de
        // códigos, necesarias para las tildes y la ñ.
        'profile' => env('PRINTER_PROFILE', 'default'),

        // Caracteres por línea en fuente A: 42 en rollo de 80mm, 32 en 58mm.
        'columns' => (int) env('PRINTER_COLUMNS', 42),

        // Imprime al confirmar la venta, sin que el cajero tenga que pulsar
        // nada. La vista previa se sigue mostrando para reimprimir.
        'auto_print' => env('PRINTER_AUTO_PRINT', true),

        // Corte automático del papel al terminar el tiquete.
        'cut' => env('PRINTER_CUT', true),

        // Pulso al cajón monedero (conector RJ-11 de la impresora).
        'cash_drawer' => env('PRINTER_CASH_DRAWER', false),

        // Logo como imagen en el tiquete. Requiere la extensión gd; si falta,
        // se cae solo al nombre en texto.
        'logo' => env('PRINTER_LOGO', true),

        // Código de barras del número de factura al pie del tiquete.
        'barcode' => env('PRINTER_BARCODE', true),
    ],

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
