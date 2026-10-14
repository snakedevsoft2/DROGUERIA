<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    {{-- Lo lee dompdf, no un navegador: tablas en vez de flex o grid, y la
         fuente DejaVu, que trae las tildes y la ñ. --}}
    <style>
        @page {
            margin: 16mm 14mm 20mm;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9pt;
            color: #1e293b;
        }

        .encabezado {
            width: 100%;
            border-bottom: 2px solid #b8913a;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .encabezado td {
            vertical-align: middle;
        }

        .logo {
            height: 44px;
        }

        .tienda-nombre {
            font-size: 12pt;
            font-weight: bold;
            color: #0b4d32;
        }

        .tienda {
            font-size: 7.5pt;
            color: #64748b;
            text-align: right;
            line-height: 1.5;
        }

        h1 {
            font-size: 15pt;
            margin: 0 0 2px;
            color: #0f172a;
        }

        .sub {
            color: #64748b;
            margin: 0 0 12px;
        }

        h2 {
            font-size: 9.5pt;
            margin: 16px 0 6px;
            color: #0b4d32;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
        }

        table.datos th {
            background: #f1f5f9;
            color: #475569;
            font-size: 7pt;
            text-transform: uppercase;
            text-align: left;
            padding: 5px 6px;
            border-bottom: 1px solid #cbd5e1;
        }

        table.datos td {
            padding: 4px 6px;
            border-bottom: 1px solid #e2e8f0;
        }

        table.datos tr {
            page-break-inside: avoid;
        }

        .num {
            text-align: right !important;
            white-space: nowrap;
        }

        .sin-corte {
            white-space: nowrap;
        }

        .centro {
            text-align: center !important;
        }

        .tenue {
            color: #94a3b8;
        }

        tr.total td {
            font-weight: bold;
            border-top: 1.5px solid #94a3b8;
            background: #f8fafc;
        }

        table.cifras {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px 5px;
            margin: 0 -5px;
        }

        table.cifras td {
            border: 1px solid #e2e8f0;
            padding: 7px 9px;
            width: 25%;
        }

        .etiqueta {
            display: block;
            font-size: 7pt;
            color: #64748b;
            text-transform: uppercase;
        }

        .valor {
            display: block;
            font-size: 12.5pt;
            font-weight: bold;
            margin-top: 2px;
        }

        .ganancia {
            color: #047857;
        }

        .perdida {
            color: #b91c1c;
        }

        .sobra {
            color: #b45309;
        }

        .pie {
            position: fixed;
            bottom: -12mm;
            left: 0;
            right: 0;
            font-size: 7pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }

        .vacio {
            padding: 14px;
            text-align: center;
            color: #94a3b8;
            border: 1px dashed #cbd5e1;
        }
    </style>
</head>
<body>

    <div class="pie">
        {{-- El número de página lo escribe dompdf a la derecha de esta línea. --}}
        Generado el {{ $generatedAt->format('d/m/Y h:i a') }} &middot; {{ $store['name'] }}
    </div>

    <table class="encabezado">
        <tr>
            <td>
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="{{ $store['name'] }}">
                @else
                    <span class="tienda-nombre">{{ $store['name'] }}</span>
                @endif
            </td>
            <td class="tienda">
                @if ($store['legal_name']) {{ $store['legal_name'] }}<br> @endif
                @if ($store['nit']) NIT {{ $store['nit'] }}<br> @endif
                @if ($store['address']) {{ $store['address'] }}<br> @endif
                @if ($store['city']) {{ $store['city'] }}<br> @endif
                @if ($store['phone']) Cel. {{ $store['phone'] }} @endif
            </td>
        </tr>
    </table>

    @yield('content')

</body>
</html>
