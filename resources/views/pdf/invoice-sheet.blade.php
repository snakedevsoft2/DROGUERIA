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
        .marco { padding: 0; }

        .cabecera { padding: 0 0 6mm; }

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

        .documento { margin-top: 5mm; }

        .doc-tipo {
            font-size: .82em;
            letter-spacing: 2.5px;
            text-transform: uppercase;
        }

        .doc-numero {
            font-size: 1.55em;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Datos de la venta en dos columnas con puntos guía. */
        .datos { padding: 0 0 6mm; }

        .datos table { width: 100%; border-collapse: collapse; }
        .datos td { padding: 1.5px 0; }
        .datos .etiqueta { width: 30mm; color: #555; }

        .datos table { width: 92mm; }

        /* La tabla no se estira a toda la hoja: así las cifras quedan al lado
           del producto y la factura se lee como una sola columna. */
        .items {
            width: 125mm;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .items th {
            font-size: .82em;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-align: left;
            font-weight: 700;
            padding: 0 3mm 2mm;
        }

        .items td {
            padding: 2.4mm 3mm;
            vertical-align: top;
        }

        .items tr { page-break-inside: avoid; break-inside: avoid; }

        .c-item  { width: 7%;  text-align: left; padding-left: 0 !important; }
        .c-name  { width: 53%; word-break: break-word; overflow-wrap: anywhere; }
        .c-qty   { width: 14%; text-align: right; white-space: nowrap; }
        .c-total { width: 26%; text-align: right; white-space: nowrap; padding-right: 0 !important; }

        .presentation {
            display: block;
            font-size: .84em;
            font-style: italic;
            color: #555;
        }

        .totales {
            padding: 5mm 0 0;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .totales table {
            width: 88mm;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .totales td { padding: 1.5mm 0; }
        .totales .valor { text-align: right; white-space: nowrap; }

        /* Doble línea sobre el total: convención de la factura impresa. */
        .totales .grand td {
            font-size: 1.25em;
            font-weight: 700;
            padding: 3mm 0 1mm;
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

        .pie-hoja { padding: 6mm 0 0; }

        .nota { font-size: .84em; color: #444; }

        .firmas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12mm;
        }

        .firmas td {
            width: 50%;
            text-align: left;
            font-size: .84em;
            color: #444;
            padding: 0 12mm 0 0;
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
                <div>
                    <div>
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
                    </div>

                    <div class="documento">
                        <div class="doc-tipo">Comprobante de venta</div>
                        <div class="doc-numero">{{ $sale->invoice_number }}</div>
                    </div>
                </div>
            </div>

            <div class="datos">
                <table>
                    <tr>
                        <td class="etiqueta">Fecha</td>
                        <td>{{ $sale->created_at->format('d/m/Y') }} &middot; {{ $sale->created_at->format('H:i') }}</td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Forma de pago</td>
                        <td class="bold">{{ $methods[$sale->payment_method] ?? strtoupper($sale->payment_method) }}</td>
                    </tr>
                    <tr>
                        <td class="etiqueta">Atendió</td>
                        <td>{{ $sale->user?->name ?? '—' }}</td>
                    </tr>
                    <tr>
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
                            <td class="c-total">{{ $money($line->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="totales">
                @php
                    // Una sola cifra grande: el subtotal sólo aparece si hubo
                    // descuento, y lo recibido sólo cuando hay vuelto que dar.
                    $hayDescuento = (float) $sale->discount > 0;
                    $hayVuelto = (float) $sale->change_amount > 0 || $sale->payment_method === 'cash';
                @endphp

                <table>
                    @if ($hayDescuento)
                        <tr>
                            <td>Subtotal</td>
                            <td class="valor">{{ $money($sale->subtotal) }}</td>
                        </tr>
                        <tr>
                            <td>Descuento</td>
                            <td class="valor">-{{ $money($sale->discount) }}</td>
                        </tr>
                    @endif

                    <tr class="grand">
                        <td class="upper">Total a pagar</td>
                        <td class="valor">{{ $money($sale->total) }}</td>
                    </tr>

                    @if ($hayVuelto)
                        <tr class="entrega">
                            <td>Recibido</td>
                            <td class="valor">{{ $money($sale->paid_amount) }}</td>
                        </tr>
                        <tr>
                            <td class="bold">Cambio</td>
                            <td class="valor bold">{{ $money($sale->change_amount) }}</td>
                        </tr>
                    @endif
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
