@extends('pdf.layouts.documento')

@section('title', 'Historial de cierres de caja')

@section('content')
    @php
        $diferencias = $closings->whereNotNull('difference');
    @endphp

    <h1>Historial de cierres de caja</h1>
    <p class="sub">
        Del {{ $from->translatedFormat('j \d\e F \d\e Y') }} al {{ $to->translatedFormat('j \d\e F \d\e Y') }}
        &middot; {{ $closings->count() }} {{ $closings->count() === 1 ? 'cierre' : 'cierres' }}
    </p>

    <table class="cifras">
        <tr>
            <td><span class="etiqueta">Vendido</span><span class="valor">{{ $pesos($closings->sum('revenue')) }}</span></td>
            <td><span class="etiqueta">Costo</span><span class="valor">{{ $pesos($closings->sum('cost')) }}</span></td>
            <td><span class="etiqueta">Ganancia</span><span class="valor {{ $closings->sum('profit') < 0 ? 'perdida' : 'ganancia' }}">{{ $pesos($closings->sum('profit')) }}</span></td>
            <td><span class="etiqueta">Diferencias de caja</span><span class="valor {{ $diferencias->sum('difference') <= -1 ? 'perdida' : '' }}">{{ $diferencias->isEmpty() ? '—' : $pesos($diferencias->sum('difference')) }}</span></td>
        </tr>
    </table>

    <h2>Cierres</h2>
    @if ($closings->isEmpty())
        <div class="vacio">No hubo cierres de caja en estas fechas.</div>
    @else
        <table class="datos" style="font-size: 8pt;">
            <tr>
                <th>Cierre</th>
                <th>Cerrado</th>
                <th>Cerró</th>
                <th class="centro">Ventas</th>
                <th class="num">Vendido</th>
                <th class="num">Costo</th>
                <th class="num">Ganancia</th>
                <th class="num">Esperado</th>
                <th class="num">Contado</th>
                <th class="num">Diferencia</th>
            </tr>
            @foreach ($closings as $cierre)
                <tr>
                    <td class="sin-corte">{{ $cierre->number }}</td>
                    <td>{{ $cierre->closed_at->format('d/m/Y h:i a') }}</td>
                    <td>{{ $cierre->cashier ?: '—' }}</td>
                    <td class="centro">{{ $cierre->sales_count }}</td>
                    <td class="num">{{ $pesos($cierre->revenue) }}</td>
                    <td class="num">{{ $pesos($cierre->cost) }}</td>
                    <td class="num {{ $cierre->profit < 0 ? 'perdida' : 'ganancia' }}">{{ $pesos($cierre->profit) }}</td>
                    <td class="num">{{ $pesos($cierre->expected_cash) }}</td>
                    <td class="num">{{ $cierre->counted_cash === null ? '—' : $pesos($cierre->counted_cash) }}</td>
                    <td class="num {{ $cierre->difference !== null && $cierre->difference <= -1 ? 'perdida' : ($cierre->difference !== null && $cierre->difference >= 1 ? 'sobra' : '') }}">
                        {{ $cierre->difference === null ? '—' : $pesos($cierre->difference) }}
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">Total</td>
                <td class="centro">{{ $closings->sum('sales_count') }}</td>
                <td class="num">{{ $pesos($closings->sum('revenue')) }}</td>
                <td class="num">{{ $pesos($closings->sum('cost')) }}</td>
                <td class="num">{{ $pesos($closings->sum('profit')) }}</td>
                <td class="num">{{ $pesos($closings->sum('expected_cash')) }}</td>
                <td class="num"></td>
                <td class="num">{{ $diferencias->isEmpty() ? '' : $pesos($diferencias->sum('difference')) }}</td>
            </tr>
        </table>
        <p class="tenue">Diferencia = efectivo contado menos el que debía haber. Positiva: sobró; negativa: faltó.</p>
    @endif
@endsection
