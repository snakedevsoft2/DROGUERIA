@extends('pdf.layouts.documento')

@section('title', "Cierre de caja {$closing->number}")

@section('content')
    @php
        $metodos = \App\Services\SalesSummary::PAYMENT_METHODS;
        $dif = $closing->difference;
    @endphp

    <h1>Cierre de caja {{ $closing->number }}</h1>
    <p class="sub">
        Cerrado el {{ $closing->closed_at->translatedFormat('l j \d\e F \d\e Y, h:i a') }}<br>
        Ventas desde el {{ $closing->opened_at->translatedFormat('j \d\e F, h:i a') }}
        @if ($closing->cashier) &middot; Cerró: {{ $closing->cashier }} @endif
    </p>

    <table class="cifras">
        <tr>
            <td><span class="etiqueta">Vendido</span><span class="valor">{{ $pesos($closing->revenue) }}</span></td>
            <td><span class="etiqueta">Costo</span><span class="valor">{{ $pesos($closing->cost) }}</span></td>
            <td><span class="etiqueta">Ganancia</span><span class="valor {{ $closing->profit < 0 ? 'perdida' : 'ganancia' }}">{{ $pesos($closing->profit) }}</span></td>
            <td><span class="etiqueta">Ventas &middot; unidades</span><span class="valor">{{ $closing->sales_count }} &middot; {{ $closing->units_sold }}</span></td>
        </tr>
    </table>

    @if ($closing->discount > 0)
        <p class="tenue">Lo vendido ya descuenta {{ $pesos($closing->discount) }} de descuentos.</p>
    @endif

    <h2>Por método de pago</h2>
    <table class="datos">
        <tr><th>Método</th><th class="num">Valor</th></tr>
        <tr><td>{{ $metodos['cash'] }}</td><td class="num">{{ $pesos($closing->cash_sales) }}</td></tr>
        <tr><td>{{ $metodos['card'] }}</td><td class="num">{{ $pesos($closing->card_sales) }}</td></tr>
        <tr><td>{{ $metodos['transfer'] }}</td><td class="num">{{ $pesos($closing->transfer_sales) }}</td></tr>
        <tr class="total"><td>Total</td><td class="num">{{ $pesos($closing->revenue) }}</td></tr>
    </table>

    <h2>Arqueo de caja</h2>
    <table class="datos">
        <tr><td>Base con la que abrió</td><td class="num">{{ $pesos($closing->opening_cash) }}</td></tr>
        <tr><td>Ventas en efectivo</td><td class="num">{{ $pesos($closing->cash_sales) }}</td></tr>
        <tr class="total"><td>Efectivo que debe haber</td><td class="num">{{ $pesos($closing->expected_cash) }}</td></tr>
        <tr><td>Efectivo contado</td><td class="num">{{ $closing->counted_cash === null ? 'No se contó' : $pesos($closing->counted_cash) }}</td></tr>
        @if ($dif !== null)
            <tr class="total">
                <td>
                    @if (abs($dif) < 1) Diferencia: la caja cuadra
                    @elseif ($dif > 0) Sobrante
                    @else Faltante
                    @endif
                </td>
                <td class="num {{ abs($dif) < 1 ? '' : ($dif > 0 ? 'sobra' : 'perdida') }}">{{ $pesos(abs($dif)) }}</td>
            </tr>
        @endif
    </table>

    @if ($closing->notes)
        <h2>Notas</h2>
        <p>{{ $closing->notes }}</p>
    @endif

    <h2>Ventas del turno</h2>
    @if ($sales->isEmpty())
        <div class="vacio">No hubo ventas en este turno.</div>
    @else
        <table class="datos">
            <tr>
                <th>Factura</th>
                <th>Fecha y hora</th>
                <th>Pago</th>
                <th class="num">Descuento</th>
                <th class="num">Total</th>
            </tr>
            @foreach ($sales as $sale)
                <tr>
                    <td>{{ $sale->invoice_number }}</td>
                    <td>{{ $sale->created_at->format('d/m/Y h:i a') }}</td>
                    <td>{{ $metodos[$sale->payment_method] ?? $sale->payment_method }}</td>
                    <td class="num">{{ (float) $sale->discount > 0 ? $pesos($sale->discount) : '' }}</td>
                    <td class="num">{{ $pesos($sale->total) }}</td>
                </tr>
            @endforeach
        </table>

        {{-- Una venta borrada del historial después del cierre ya no sale en
             la lista, pero el cierre conserva las cifras con que se entregó. --}}
        @if (round((float) $sales->sum('total'), 2) !== round($closing->revenue, 2))
            <p class="tenue">Después del cierre se eliminaron ventas del historial: la lista suma {{ $pesos($sales->sum('total')) }} y el cierre se entregó con {{ $pesos($closing->revenue) }}.</p>
        @endif
    @endif

    <table style="width: 100%; margin-top: 40px;">
        <tr>
            <td style="width: 45%; border-top: 1px solid #64748b; padding-top: 4px; text-align: center; color: #64748b;">Entrega</td>
            <td style="width: 10%;"></td>
            <td style="width: 45%; border-top: 1px solid #64748b; padding-top: 4px; text-align: center; color: #64748b;">Recibe</td>
        </tr>
    </table>
@endsection
