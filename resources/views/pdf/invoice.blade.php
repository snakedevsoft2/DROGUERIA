@php
    $store = config('drogueria');
    $width = $store['receipt']['width'] ?? '58mm';

    // La vista previa debe caer en las mismas columnas que imprime la
    // tiquetera. Se despeja del ancho del rollo: descontados los márgenes,
    // cada carácter mide (ancho útil / columnas), y en una monoespaciada el
    // carácter ocupa 0,6 del tamaño de letra.
    $margen = 2.5;                                          // mm a cada lado
    $columnas = max(24, (int) ($store['printer']['columns'] ?? 35));
    $utilMm = max(20, (float) filter_var($width, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) - 2 * $margen);
    $fuenteMm = round($utilMm / $columnas / 0.6, 3);

    $methods = ['cash' => 'EFECTIVO', 'card' => 'TARJETA', 'transfer' => 'TRANSFERENCIA'];

    // Formato colombiano: miles con punto y sin decimales. Las cifras del
    // rollo son angostas, y los centavos no existen en caja.
    $money = fn ($value) => '$'.number_format((float) $value, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Comprobante {{ $sale->invoice_number }}</title>

    <style>
        /* El rollo define el ancho de página y el alto es continuo. */
        @page {
            size: {{ $width }} auto;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 4mm {{ $margen }}mm;
            width: {{ $width }};
            background: #fff;
            color: #000;
            /* Monoespaciada: las columnas de precios quedan alineadas. */
            font-family: 'Consolas', 'DejaVu Sans Mono', 'Courier New', monospace;
            /* En mm y no en px: así la tirilla en pantalla cae en las mismas
               {{ $columnas }} columnas por línea que imprime la tiquetera. */
            font-size: {{ $fuenteMm }}mm;
            line-height: 1.3;
            -webkit-font-smoothing: none;
        }

        .center { text-align: center; }
        .right  { text-align: right; }
        .bold   { font-weight: 700; }
        .upper  { text-transform: uppercase; }

        .logo {
            display: block;
            width: {{ round($utilMm * 0.82, 1) }}mm;   /* deja aire a los lados del rollo */
            max-width: 100%;
            height: auto;
            margin: 0 auto 4px;
            /* Que el navegador no lo aclare al imprimir. */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .store-name {
            font-size: 1.5em;
            font-weight: 700;
            letter-spacing: .5px;
            margin-bottom: 2px;
        }

        .muted { font-size: .92em; }

        .rule {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            /* Fijo: las columnas conservan su ancho renglón a renglón, sin
               importar qué tan largo sea el nombre del producto. */
            table-layout: fixed;
        }

        th, td {
            padding: 1px 0;
            vertical-align: top;
        }

        thead th {
            font-size: .92em;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }

        .col-qty   { width: 18%; }
        .col-price { width: 40%; }
        .col-total { width: 42%; }

        /* Cada producto (nombre + cifras) viaja junto y no se parte entre
           páginas ni entre hojas del rollo. */
        .item { page-break-inside: avoid; break-inside: avoid; }

        .item-name {
            padding-top: 3px;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        /* Las cifras nunca se parten: si no caben, encogen el nombre. */
        .col-qty, .col-price, .col-total { white-space: nowrap; }

        .totals td { padding: 1px 0; }

        .grand td {
            font-size: 1.3em;
            font-weight: 700;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
        }

        .footer {
            margin-top: 8px;
            font-size: .92em;
        }

        /* Corte de papel: espacio en blanco al final del rollo. */
        .cut { height: 12mm; }

        .toolbar {
            margin-top: 10px;
            text-align: center;
        }

        .toolbar a {
            display: inline-block;
            color: #000;
            text-decoration: none;
        }

        .toolbar button,
        .toolbar a {
            /* Fuera del rollo: tamaño de pantalla, no el de la tirilla. */
            font-family: inherit;
            font-size: 12px;
            padding: 6px 14px;
            margin: 0 2px;
            border: 1px solid #000;
            background: #fff;
            cursor: pointer;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0 {{ $margen }}mm; }
        }
    </style>
</head>
<body>

    <div class="center">
        @if ($logo)
            {{-- El logo ya trae el nombre, así que sustituye al texto. --}}
            <img class="logo" src="{{ $logo }}" alt="{{ $store['name'] }}">
        @else
            <div class="store-name upper">{{ $store['name'] }}</div>
        @endif

        @if ($store['legal_name'])
            <div class="muted">{{ $store['legal_name'] }}</div>
        @endif
        @if ($store['nit'])
            <div class="muted">NIT: {{ $store['nit'] }}</div>
        @endif
        {{-- Dirección y ciudad se imprimen de forma independiente: puede
             haber una sin la otra. --}}
        @php $location = collect([$store['address'], $store['city']])->filter()->implode(', '); @endphp
        @if ($location)
            <div class="muted">{{ $location }}</div>
        @endif
        @if ($store['phone'])
            <div class="muted">Cel: {{ $store['phone'] }}</div>
        @endif
        @if ($store['email'])
            <div class="muted">{{ $store['email'] }}</div>
        @endif
    </div>

    <hr class="rule">

    <div class="bold center upper">Comprobante de venta</div>
    <div class="center">{{ $sale->invoice_number }}</div>

    <hr class="rule">

    <table>
        <tr>
            <td>Fecha:</td>
            <td class="right">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td>Pago:</td>
            <td class="right bold">{{ $methods[$sale->payment_method] ?? strtoupper($sale->payment_method) }}</td>
        </tr>
        @if ($sale->user)
            <tr>
                <td>Atendió:</td>
                <td class="right">{{ $sale->user->name }}</td>
            </tr>
        @endif
    </table>

    <hr class="rule">

    <table>
        <thead>
            <tr>
                <th class="col-qty">Cant</th>
                <th class="col-price right">Precio</th>
                <th class="col-total right">Total</th>
            </tr>
        </thead>
        @foreach ($lines as $line)
            {{-- Un tbody por producto: nombre y cifras no se separan nunca. --}}
            <tbody class="item">
                {{-- Nombre en su propia fila: en 80mm no cabe junto a las cifras. --}}
                <tr>
                    <td colspan="3" class="item-name">
                        {{ $line->name }}@if ($line->presentation) <span class="muted">({{ $line->presentation }})</span>@endif
                    </td>
                </tr>
                <tr>
                    <td class="col-qty">{{ $line->quantity }}</td>
                    <td class="col-price right">{{ $money($line->unit_price) }}</td>
                    <td class="col-total right bold">{{ $money($line->subtotal) }}</td>
                </tr>
            </tbody>
        @endforeach
    </table>

    <hr class="rule">

    <table class="totals">
        <colgroup>
            <col style="width:52%">
            <col style="width:48%">
        </colgroup>
        <tr>
            <td>Subtotal</td>
            <td class="right">{{ $money($sale->subtotal) }}</td>
        </tr>

        @if ((float) $sale->discount > 0)
            <tr>
                <td>Descuento</td>
                <td class="right">-{{ $money($sale->discount) }}</td>
            </tr>
        @endif

        <tr class="grand">
            <td class="upper">Total</td>
            <td class="right">{{ $money($sale->total) }}</td>
        </tr>

        <tr>
            <td style="padding-top:4px">Recibido</td>
            <td class="right" style="padding-top:4px">{{ $money($sale->paid_amount) }}</td>
        </tr>
        <tr>
            <td class="bold">Cambio</td>
            <td class="right bold">{{ $money($sale->change_amount) }}</td>
        </tr>
    </table>

    <hr class="rule">

    <div class="center footer">
        <div>Artículos: {{ $lines->sum('quantity') }}</div>
        @if ($store['receipt']['legal_note'])
            <div style="margin-top:4px">{{ $store['receipt']['legal_note'] }}</div>
        @endif
        @if ($store['receipt']['footer'])
            <div class="bold" style="margin-top:4px">{{ $store['receipt']['footer'] }}</div>
        @endif
    </div>

    <div class="cut"></div>

    @unless ($embedded)
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">Imprimir</button>
            <a href="{{ route('receipt', ['sale' => $sale->id, 'formato' => 'carta']) }}">Ver en hoja carta</a>
            <button type="button" onclick="window.close()">Cerrar</button>
        </div>
    @endunless

    <script>
        // Sólo con ?print=1. El POS muestra primero la vista previa y deja
        // que el cajero dispare la impresión desde el modal.
        @if ($autoPrint)
            window.addEventListener('load', function () {
                window.focus();
                window.print();
            });
        @endif
    </script>

</body>
</html>
