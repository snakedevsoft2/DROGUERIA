@extends('pdf.layouts.documento')

@section('title', 'Informe de ventas y ganancias')

@section('content')
    <h1>Informe de ventas y ganancias</h1>
    <p class="sub">Del {{ $from->translatedFormat('j \d\e F \d\e Y') }} al {{ $to->translatedFormat('j \d\e F \d\e Y') }}</p>

    <table class="cifras">
        <tr>
            <td><span class="etiqueta">Vendido</span><span class="valor">{{ $pesos($totals['revenue']) }}</span></td>
            <td><span class="etiqueta">Costo de lo vendido</span><span class="valor">{{ $pesos($totals['cost']) }}</span></td>
            <td><span class="etiqueta">Ganancia</span><span class="valor {{ $totals['profit'] < 0 ? 'perdida' : 'ganancia' }}">{{ $pesos($totals['profit']) }}</span></td>
            <td><span class="etiqueta">Margen</span><span class="valor">{{ number_format($totals['margin'], 1, ',', '.') }}%</span></td>
        </tr>
        <tr>
            <td><span class="etiqueta">Ventas</span><span class="valor">{{ number_format($totals['sales_count'], 0, ',', '.') }}</span></td>
            <td><span class="etiqueta">Unidades vendidas</span><span class="valor">{{ number_format($totals['units'], 0, ',', '.') }}</span></td>
            <td><span class="etiqueta">Venta promedio</span><span class="valor">{{ $pesos($totals['average']) }}</span></td>
            <td><span class="etiqueta">Descuentos</span><span class="valor">{{ $pesos($totals['discount']) }}</span></td>
        </tr>
    </table>
    <p class="tenue">Ganancia = lo vendido (ya con descuentos) menos lo que costaron las unidades vendidas.</p>

    <h2>Por método de pago</h2>
    <table class="datos">
        <tr><th>Método</th><th class="centro">Ventas</th><th class="num">Valor</th><th class="num">Participación</th></tr>
        @foreach ($payments as $pago)
            <tr>
                <td>{{ $pago['label'] }}</td>
                <td class="centro">{{ $pago['sales_count'] }}</td>
                <td class="num">{{ $pesos($pago['revenue']) }}</td>
                <td class="num">{{ $totals['revenue'] > 0 ? number_format($pago['revenue'] / $totals['revenue'] * 100, 1, ',', '.') : '0,0' }}%</td>
            </tr>
        @endforeach
    </table>

    <h2>Ganancia por día</h2>
    @if ($daily->isEmpty())
        <div class="vacio">No hubo ventas en estas fechas.</div>
    @else
        <table class="datos">
            <tr>
                <th>Fecha</th>
                <th class="centro">Ventas</th>
                <th class="num">Vendido</th>
                <th class="num">Costo</th>
                <th class="num">Ganancia</th>
            </tr>
            @foreach ($daily as $dia)
                <tr>
                    <td>{{ ucfirst($dia->day->translatedFormat('D j M Y')) }}</td>
                    <td class="centro">{{ $dia->sales_count }}</td>
                    <td class="num">{{ $pesos($dia->revenue) }}</td>
                    <td class="num">{{ $pesos($dia->cost) }}</td>
                    <td class="num {{ $dia->profit < 0 ? 'perdida' : 'ganancia' }}">{{ $pesos($dia->profit) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="centro">{{ $totals['sales_count'] }}</td>
                <td class="num">{{ $pesos($totals['revenue']) }}</td>
                <td class="num">{{ $pesos($totals['cost']) }}</td>
                <td class="num">{{ $pesos($totals['profit']) }}</td>
            </tr>
        </table>
    @endif

    @if ($topProducts->isNotEmpty())
        <h2>Productos que más ganancia dejaron</h2>
        <table class="datos">
            <tr>
                <th>Producto</th>
                <th class="centro">Unidades</th>
                <th class="num">Vendido</th>
                <th class="num">Costo</th>
                <th class="num">Ganancia</th>
            </tr>
            @foreach ($topProducts as $producto)
                <tr>
                    <td>
                        {{ $producto->name }}
                        @if ($producto->presentation) <span class="tenue">&middot; {{ $producto->presentation }}</span> @endif
                    </td>
                    <td class="centro">{{ $producto->units }}</td>
                    <td class="num">{{ $pesos($producto->revenue) }}</td>
                    <td class="num">{{ $pesos($producto->cost) }}</td>
                    <td class="num {{ $producto->profit < 0 ? 'perdida' : 'ganancia' }}">{{ $pesos($producto->profit) }}</td>
                </tr>
            @endforeach
        </table>
        <p class="tenue">Por producto la ganancia se calcula antes de los descuentos hechos a la venta completa.</p>
    @endif

    <h2>Cierres de caja</h2>
    @if ($closings->isEmpty())
        <div class="vacio">No hubo cierres de caja en estas fechas.</div>
    @else
        <table class="datos">
            <tr>
                <th>Cierre</th>
                <th>Cerrado</th>
                <th class="centro">Ventas</th>
                <th class="num">Vendido</th>
                <th class="num">Ganancia</th>
                <th class="num">Diferencia de caja</th>
            </tr>
            @foreach ($closings as $cierre)
                <tr>
                    <td class="sin-corte">{{ $cierre->number }}</td>
                    <td>{{ $cierre->closed_at->format('d/m/Y h:i a') }}</td>
                    <td class="centro">{{ $cierre->sales_count }}</td>
                    <td class="num">{{ $pesos($cierre->revenue) }}</td>
                    <td class="num">{{ $pesos($cierre->profit) }}</td>
                    <td class="num {{ $cierre->difference !== null && $cierre->difference <= -1 ? 'perdida' : '' }}">{{ $cierre->difference === null ? '—' : $pesos($cierre->difference) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
