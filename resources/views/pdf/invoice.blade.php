@php
    $store = config('drogueria');
    $width = $store['receipt']['width'] ?? '80mm';

    $methods = ['cash' => 'EFECTIVO', 'card' => 'TARJETA', 'transfer' => 'TRANSFERENCIA'];

    // Mismo formato que usa el POS en pantalla, para que cuadren a la vista.
    $money = fn ($value) => '$'.number_format((float) $value, 2);
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
            padding: 4mm 3mm;
            width: {{ $width }};
            background: #fff;
            color: #000;
            /* Monoespaciada: las columnas de precios quedan alineadas. */
            font-family: 'Consolas', 'DejaVu Sans Mono', 'Courier New', monospace;
            font-size: 11px;
            line-height: 1.35;
            -webkit-font-smoothing: none;
        }

        .center { text-align: center; }
        .right  { text-align: right; }
        .bold   { font-weight: 700; }
        .upper  { text-transform: uppercase; }

        .logo {
            display: block;
            width: 60mm;      /* el rollo útil son ~72mm; deja aire a los lados */
            max-width: 100%;
            height: auto;
            margin: 0 auto 4px;
            /* Que el navegador no lo aclare al imprimir. */
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .store-name {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: .5px;
            margin-bottom: 2px;
        }

        .muted { font-size: 10px; }

        .rule {
            border: 0;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 1px 0;
            vertical-align: top;
        }

        thead th {
            font-size: 10px;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }

        .col-qty   { width: 12%; }
        .col-price { width: 26%; }
        .col-total { width: 28%; }

        .item-name {
            padding-top: 3px;
            word-break: break-word;
        }

        .totals td { padding: 1px 0; }

        .grand td {
            font-size: 14px;
            font-weight: 700;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
        }

        .footer {
            margin-top: 8px;
            font-size: 10px;
        }

        /* Corte de papel: espacio en blanco al final del rollo. */
        .cut { height: 12mm; }

        .toolbar {
            margin-top: 10px;
            text-align: center;
        }

        .toolbar button {
            font: inherit;
            padding: 6px 14px;
            margin: 0 2px;
            border: 1px solid #000;
            background: #fff;
            cursor: pointer;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: 0 3mm; }
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
        <tbody>
            @foreach ($lines as $line)
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
            @endforeach
        </tbody>
    </table>

    <hr class="rule">

    <table class="totals">
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

        <tr>
            <td>IVA</td>
            <td class="right">{{ $money($sale->tax) }}</td>
        </tr>

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
