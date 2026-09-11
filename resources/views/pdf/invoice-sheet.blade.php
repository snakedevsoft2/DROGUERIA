@php
    $store = config('drogueria');

    $methods = ['cash' => 'EFECTIVO', 'card' => 'TARJETA', 'transfer' => 'TRANSFERENCIA'];

    // Mismo formato que la tirilla: miles con punto y sin decimales.
    $money = fn ($value) => '$'.number_format((float) $value, 0, ',', '.');

    $location = collect([$store['address'], $store['city']])->filter()->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Factura {{ $sale->invoice_number }}</title>

    <style>
        @page {
            size: letter portrait;
            margin: 14mm 14mm 16mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f1f5f9;
            color: #0f172a;
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        /* En pantalla la hoja se ve como tal; al imprimir, el papel manda. */
        .sheet {
            width: 216mm;
            min-height: 279mm;
            margin: 16px auto;
            padding: 16mm 14mm;
            background: #fff;
            box-shadow: 0 2px 12px rgba(15, 23, 42, .15);
        }

        .right  { text-align: right; }
        .center { text-align: center; }
        .bold   { font-weight: 700; }
        .upper  { text-transform: uppercase; }
        .muted  { color: #64748b; }

        /* Encabezado: datos de la droguería a la izquierda, factura a la derecha. */
        .head {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }

        .head td { vertical-align: top; }

        .logo {
            display: block;
            max-width: 52mm;
            max-height: 22mm;
            height: auto;
            margin-bottom: 6px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .store-name {
            font-size: 19px;
            font-weight: 700;
            letter-spacing: .3px;
            margin-bottom: 2px;
        }

        .doc-box {
            border: 1px solid #0f172a;
            padding: 8px 12px;
            min-width: 62mm;
        }

        .doc-title {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .doc-number {
            font-size: 17px;
            font-weight: 700;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 11.5px;
        }

        .meta td {
            border: 1px solid #cbd5e1;
            padding: 5px 8px;
        }

        .meta .label {
            background: #f8fafc;
            font-weight: 600;
            width: 22mm;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Tabla de productos: ancho fijo para que las columnas no bailen. */
        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th {
            background: #0f172a;
            color: #fff;
            font-size: 11px;
            letter-spacing: .5px;
            text-align: left;
            padding: 7px 8px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .items td {
            border-bottom: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: top;
        }

        /* Fila completa por producto, nunca partida entre páginas. */
        .items tr { page-break-inside: avoid; break-inside: avoid; }

        .items tbody tr:nth-child(even) td {
            background: #f8fafc;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .c-item  { width: 8%;  text-align: right; }
        .c-name  { width: 46%; word-break: break-word; overflow-wrap: anywhere; }
        .c-qty   { width: 10%; text-align: right; white-space: nowrap; }
        .c-price { width: 18%; text-align: right; white-space: nowrap; }
        .c-total { width: 18%; text-align: right; white-space: nowrap; }

        .presentation {
            display: block;
            font-size: 10.5px;
            color: #64748b;
        }

        /* Bloque de totales: pegado a la derecha, alineado con la tabla. */
        .totals-wrap {
            width: 100%;
            margin-top: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .totals {
            width: 82mm;
            margin-left: auto;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .totals td {
            padding: 4px 8px;
        }

        .totals .t-label { text-align: left; }
        .totals .t-value { text-align: right; white-space: nowrap; }

        .totals .grand td {
            border-top: 1.5px solid #0f172a;
            border-bottom: 1.5px solid #0f172a;
            font-size: 15px;
            font-weight: 700;
            padding: 7px 8px;
        }

        .totals .paid td {
            padding-top: 8px;
            color: #475569;
        }

        .footer {
            margin-top: 22px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 10.5px;
            color: #475569;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .sign {
            margin-top: 26px;
            width: 100%;
            border-collapse: collapse;
        }

        .sign td {
            width: 50%;
            padding-top: 24px;
            font-size: 10.5px;
            text-align: center;
            color: #475569;
        }

        .sign .line {
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            margin: 0 10mm;
        }

        .toolbar {
            text-align: center;
            margin: 0 0 18px;
        }

        .toolbar button,
        .toolbar a {
            display: inline-block;
            font: inherit;
            padding: 8px 16px;
            margin: 0 3px;
            border: 1px solid #0f172a;
            background: #fff;
            color: #0f172a;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
        }

        @media print {
            .no-print { display: none !important; }

            body { background: #fff; }

            .sheet {
                width: auto;
                min-height: 0;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }

            /* El encabezado de la tabla se repite en cada hoja. */
            .items thead { display: table-header-group; }
        }
    </style>
</head>
<body>

    @unless ($embedded)
        <div class="toolbar no-print" style="padding-top:16px">
            <button type="button" onclick="window.print()">Imprimir</button>
            <a href="{{ route('receipt', $sale->id) }}">Ver tirilla 80mm</a>
            <button type="button" onclick="window.close()">Cerrar</button>
        </div>
    @endunless

    <div class="sheet">

        <table class="head">
            <tr>
                <td>
                    @if ($logo)
                        <img class="logo" src="{{ $logo }}" alt="{{ $store['name'] }}">
                    @endif
                    <div class="store-name upper">{{ $store['name'] }}</div>
                    @if ($store['legal_name'])
                        <div class="muted">{{ $store['legal_name'] }}</div>
                    @endif
                    @if ($store['nit'])
                        <div class="muted">NIT: {{ $store['nit'] }}</div>
                    @endif
                    @if ($location)
                        <div class="muted">{{ $location }}</div>
                    @endif
                    @if ($store['phone'])
                        <div class="muted">Cel: {{ $store['phone'] }}</div>
                    @endif
                    @if ($store['email'])
                        <div class="muted">{{ $store['email'] }}</div>
                    @endif
                </td>
                <td class="right" style="width:66mm">
                    <div class="doc-box">
                        <div class="doc-title upper">Comprobante de venta</div>
                        <div class="doc-number">{{ $sale->invoice_number }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="meta">
            <tr>
                <td class="label">Fecha</td>
                <td>{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                <td class="label">Pago</td>
                <td class="bold">{{ $methods[$sale->payment_method] ?? strtoupper($sale->payment_method) }}</td>
            </tr>
            <tr>
                <td class="label">Atendió</td>
                <td>{{ $sale->user?->name ?? '—' }}</td>
                <td class="label">Artículos</td>
                <td>{{ $lines->sum('quantity') }}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th class="c-item">#</th>
                    <th class="c-name">Producto</th>
                    <th class="c-qty">Cant</th>
                    <th class="c-price">Precio</th>
                    <th class="c-total">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td class="c-item">{{ $loop->iteration }}</td>
                        <td class="c-name">
                            {{ $line->name }}
                            @if ($line->presentation)
                                <span class="presentation">{{ $line->presentation }}</span>
                            @endif
                        </td>
                        <td class="c-qty">{{ $line->quantity }}</td>
                        <td class="c-price">{{ $money($line->unit_price) }}</td>
                        <td class="c-total bold">{{ $money($line->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-wrap">
            <table class="totals">
                <tr>
                    <td class="t-label">Subtotal</td>
                    <td class="t-value">{{ $money($sale->subtotal) }}</td>
                </tr>

                @if ((float) $sale->discount > 0)
                    <tr>
                        <td class="t-label">Descuento</td>
                        <td class="t-value">-{{ $money($sale->discount) }}</td>
                    </tr>
                @endif

                <tr class="grand">
                    <td class="t-label upper">Total</td>
                    <td class="t-value">{{ $money($sale->total) }}</td>
                </tr>

                <tr class="paid">
                    <td class="t-label">Recibido</td>
                    <td class="t-value">{{ $money($sale->paid_amount) }}</td>
                </tr>
                <tr>
                    <td class="t-label bold">Cambio</td>
                    <td class="t-value bold">{{ $money($sale->change_amount) }}</td>
                </tr>
            </table>
        </div>

        <div class="footer">
            @if ($store['receipt']['legal_note'])
                <div>{{ $store['receipt']['legal_note'] }}</div>
            @endif
            @if ($store['receipt']['footer'])
                <div class="bold" style="margin-top:4px">{{ $store['receipt']['footer'] }}</div>
            @endif
        </div>

        <table class="sign">
            <tr>
                <td><div class="line">Entregado por</div></td>
                <td><div class="line">Recibido por</div></td>
            </tr>
        </table>

    </div>

    <script>
        @if ($autoPrint)
            window.addEventListener('load', function () {
                window.focus();
                window.print();
            });
        @endif
    </script>

</body>
</html>
