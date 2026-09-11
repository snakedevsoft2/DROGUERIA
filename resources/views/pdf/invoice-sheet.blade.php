@php
    $store = config('drogueria');

    $methods = ['cash' => 'EFECTIVO', 'card' => 'TARJETA', 'transfer' => 'TRANSFERENCIA'];

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
            size: A4 portrait;
            margin: 14mm 13mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: #f4f4f2;
            color: #1a1a1a;
            /* Serif: la factura de toda la vida, la que el cliente reconoce. */
            font-family: 'Georgia', 'Cambria', 'Times New Roman', serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            padding: 14mm 13mm;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0, 0, 0, .12);
        }

        .right  { text-align: right; }
        .center { text-align: center; }
        .bold   { font-weight: 700; }
        .upper  { text-transform: uppercase; }
        .muted  { color: #555; font-size: .88em; }

        /* Marco completo: todo el documento vive dentro de un recuadro. */
        .marco {
            border: 1.5px solid #1a1a1a;
            padding: 0;
        }

        .cabecera {
            border-bottom: 1.5px solid #1a1a1a;
            padding: 7mm 7mm 5mm;
        }

        .cabecera table { width: 100%; border-collapse: collapse; }
        .cabecera td { vertical-align: top; }

        .logo {
            display: block;
            max-width: 44mm;
            max-height: 16mm;
            height: auto;
            margin-bottom: 4px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .store-name {
            font-size: 1.5em;
            font-weight: 700;
            letter-spacing: .5px;
        }

        .doc-tipo {
            font-size: .82em;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            border-bottom: 1px solid #1a1a1a;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .doc-numero {
            font-size: 1.55em;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Datos de la venta en dos columnas con puntos guía. */
        .datos {
            padding: 5mm 7mm;
            border-bottom: 1.5px solid #1a1a1a;
        }

        .datos table { width: 100%; border-collapse: collapse; }
        .datos td { padding: 1.5px 0; }
        .datos .etiqueta { width: 24mm; color: #555; }

        .items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th {
            font-size: .82em;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-align: left;
            padding: 4mm 3mm 2mm;
            border-bottom: 1px solid #1a1a1a;
        }

        .items td {
            padding: 2.4mm 3mm;
            vertical-align: top;
            border-bottom: 1px dotted #b9b9b9;
        }

        .items tr { page-break-inside: avoid; break-inside: avoid; }

        .c-item  { width: 9%;  text-align: right; padding-left: 7mm !important; }
        .c-name  { width: 45%; word-break: break-word; overflow-wrap: anywhere; }
        .c-qty   { width: 10%; text-align: right; white-space: nowrap; }
        .c-price { width: 18%; text-align: right; white-space: nowrap; }
        .c-total { width: 18%; text-align: right; white-space: nowrap; padding-right: 7mm !important; }

        .presentation {
            display: block;
            font-size: .84em;
            font-style: italic;
            color: #555;
        }

        .totales {
            padding: 4mm 7mm 5mm;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .totales table {
            width: 88mm;
            margin-left: auto;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .totales td { padding: 1.5mm 0; }
        .totales .valor { text-align: right; white-space: nowrap; }

        /* Doble línea sobre el total: convención de la factura impresa. */
        .totales .grand td {
            border-top: 3px double #1a1a1a;
            border-bottom: 1px solid #1a1a1a;
            font-size: 1.25em;
            font-weight: 700;
            padding: 2.5mm 0;
            letter-spacing: .5px;
        }

        .totales .entrega td {
            padding-top: 3mm;
            color: #555;
        }

        .letras {
            padding: 0 7mm 5mm;
            font-style: italic;
            color: #333;
            font-size: .9em;
        }

        .pie-hoja {
            border-top: 1.5px solid #1a1a1a;
            padding: 4mm 7mm 6mm;
        }

        .nota { font-size: .84em; color: #444; }

        .firmas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12mm;
        }

        .firmas td {
            width: 50%;
            text-align: center;
            font-size: .84em;
            color: #444;
            padding: 0 6mm;
        }

        .firmas .linea {
            border-top: 1px solid #1a1a1a;
            padding-top: 2px;
        }

        .toolbar { text-align: center; margin: 0 0 18px; }

        .toolbar button,
        .toolbar a {
            display: inline-block;
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 13px;
            padding: 8px 16px;
            margin: 0 3px;
            border: 1px solid #1a1a1a;
            background: #fff;
            color: #1a1a1a;
            text-decoration: none;
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
        <div class="marco">

            <div class="cabecera">
                <table>
                    <tr>
                        <td>
                            @if ($logo)
                                <img class="logo" src="{{ $logo }}" alt="{{ $store['name'] }}">
                            @else
                                <div class="store-name upper">{{ $store['name'] }}</div>
                            @endif

                            @php
                                $datos = collect([
                                    $store['legal_name'],
                                    $store['nit'] ? 'NIT: '.$store['nit'] : null,
                                    $location,
                                    $store['phone'] ? 'Cel: '.$store['phone'] : null,
                                    $store['email'],
                                ])->filter();
                            @endphp

                            @foreach ($datos as $dato)
                                <div class="muted">{{ $dato }}</div>
                            @endforeach
                        </td>
                        <td class="right" style="width:62mm">
                            <div class="doc-tipo">Comprobante de venta</div>
                            <div class="doc-numero">{{ $sale->invoice_number }}</div>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="datos">
                <table>
                    <tr>
                        <td class="etiqueta">Fecha</td>
                        <td>{{ $sale->created_at->format('d/m/Y') }} &middot; {{ $sale->created_at->format('H:i') }}</td>
                        <td class="etiqueta">Forma de pago</td>
                        <td class="bold">{{ $methods[$sale->payment_method] ?? strtoupper($sale->payment_method) }}</td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Atendió</td>
                        <td>{{ $sale->user?->name ?? '—' }}</td>
                        <td class="etiqueta">Artículos</td>
                        <td>{{ $lines->sum('quantity') }}</td>
                    </tr>
                </table>
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th class="c-item">N.º</th>
                        <th class="c-name">Descripción</th>
                        <th class="c-qty">Cant.</th>
                        <th class="c-price">V. unitario</th>
                        <th class="c-total">Valor</th>
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
                            <td class="c-total">{{ $money($line->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totales">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td class="valor">{{ $money($sale->subtotal) }}</td>
                    </tr>

                    @if ((float) $sale->discount > 0)
                        <tr>
                            <td>Descuento</td>
                            <td class="valor">-{{ $money($sale->discount) }}</td>
                        </tr>
                    @endif

                    <tr class="grand">
                        <td class="upper">Total a pagar</td>
                        <td class="valor">{{ $money($sale->total) }}</td>
                    </tr>

                    <tr class="entrega">
                        <td>Recibido</td>
                        <td class="valor">{{ $money($sale->paid_amount) }}</td>
                    </tr>
                    <tr>
                        <td class="bold">Cambio</td>
                        <td class="valor bold">{{ $money($sale->change_amount) }}</td>
                    </tr>
                </table>
            </div>

            <div class="pie-hoja">
                @if ($store['receipt']['legal_note'])
                    <div class="nota">{{ $store['receipt']['legal_note'] }}</div>
                @endif
                @if ($store['receipt']['footer'])
                    <div class="bold" style="margin-top:3px">{{ $store['receipt']['footer'] }}</div>
                @endif

                <table class="firmas">
                    <tr>
                        <td><div class="linea">Entregado por</div></td>
                        <td><div class="linea">Recibido por</div></td>
                    </tr>
                </table>
            </div>

        </div>
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
