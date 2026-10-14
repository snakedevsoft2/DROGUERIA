@php
    $pesos = fn ($valor) => ($valor < 0 ? '-' : '').'$'.number_format(abs((float) $valor), 0, ',', '.');
    $caja = $this->register;
    $diferencia = $this->difference;
    $ultimoCierreId = \App\Models\CashClosing::latestClosing()?->id;
@endphp

<div class="p-6">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Cierre de caja</h1>
            <p class="text-sm text-slate-500">Lo vendido, lo que costó y lo que se ganó. Cierre la caja al terminar el turno.</p>
        </div>

        {{-- Cómo vamos --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($this->progress as $titulo => [$periodo, $cifras])
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $titulo }}</span>
                        <span class="text-xs text-slate-400">{{ ucfirst($periodo) }}</span>
                    </div>

                    <p class="mt-2 text-xs text-slate-400">Ganancia</p>
                    <p @class([
                        'text-3xl font-black',
                        'text-emerald-600' => $cifras['profit'] >= 0,
                        'text-red-600' => $cifras['profit'] < 0,
                    ])>{{ $pesos($cifras['profit']) }}</p>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-sm">
                        <div>
                            <dt class="text-xs text-slate-400">Vendido</dt>
                            <dd class="font-bold text-slate-800">{{ $pesos($cifras['revenue']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">Costo</dt>
                            <dd class="font-bold text-slate-800">{{ $pesos($cifras['cost']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-400">Ventas</dt>
                            <dd class="font-bold text-slate-800">{{ number_format($cifras['sales_count'], 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                    <p class="mt-2 text-xs text-slate-400">Margen {{ number_format($cifras['margin'], 1, ',', '.') }}% &middot; {{ number_format($cifras['units'], 0, ',', '.') }} unidades</p>
                </div>
            @endforeach
        </div>

        @if ($lastClosingId)
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl px-4 py-3 text-sm flex flex-wrap items-center justify-between gap-3">
                <span>Caja cerrada. Descargue el comprobante del cierre para guardarlo o imprimirlo.</span>
                <a href="{{ route('cash-closing.pdf', $lastClosingId) }}" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold transition">
                    Descargar PDF del cierre
                </a>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Caja abierta --}}
            <form wire:submit="closeRegister" class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="font-bold text-slate-700">Caja abierta</h2>
                    <span class="text-xs text-slate-500">
                        Ventas desde {{ $this->openedAt->translatedFormat('D j M, g:i a') }}
                    </span>
                </div>

                <div class="p-5 flex flex-col gap-5">
                    <dl class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        @foreach ([
                            ['Ventas', number_format($caja['totals']['sales_count'], 0, ',', '.'), 'text-slate-900'],
                            ['Unidades', number_format($caja['totals']['units'], 0, ',', '.'), 'text-slate-900'],
                            ['Vendido', $pesos($caja['totals']['revenue']), 'text-blue-600'],
                            ['Costo', $pesos($caja['totals']['cost']), 'text-slate-900'],
                            ['Ganancia', $pesos($caja['totals']['profit']), $caja['totals']['profit'] < 0 ? 'text-red-600' : 'text-emerald-600'],
                        ] as [$etiqueta, $valor, $color])
                            <div>
                                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $etiqueta }}</dt>
                                <dd class="text-xl font-black {{ $color }}">{{ $valor }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @foreach ($caja['payments'] as $metodo => $pago)
                            <div wire:key="pago-{{ $metodo }}" class="rounded-xl border border-slate-200 px-4 py-3">
                                <p class="text-xs text-slate-500">{{ $pago['label'] }}</p>
                                <p class="font-bold text-slate-800">{{ $pesos($pago['revenue']) }}</p>
                                <p class="text-[11px] text-slate-400">{{ $pago['sales_count'] }} {{ $pago['sales_count'] === 1 ? 'venta' : 'ventas' }}</p>
                            </div>
                        @endforeach
                    </div>

                    {{-- Arqueo: lo que debería haber en el cajón contra lo que hay. --}}
                    <div class="border-t border-slate-200 pt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Base con la que abrió</label>
                            <input type="number" min="0" step="any" wire:model.live.debounce.400ms="opening_cash" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                            @error('opening_cash') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Efectivo que debe haber</span>
                            <p class="px-3 py-2.5 rounded-xl bg-slate-100 text-sm font-bold text-slate-800">{{ $pesos($this->expectedCash) }}</p>
                            <p class="text-[11px] text-slate-400 mt-1">Base + ventas en efectivo</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Efectivo contado</label>
                            <input type="number" min="0" step="any" wire:model.live.debounce.400ms="counted_cash" placeholder="Lo que hay en el cajón" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                            @error('counted_cash') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <span class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Diferencia</span>
                            @if ($diferencia === null)
                                <p class="px-3 py-2.5 rounded-xl bg-slate-100 text-sm text-slate-400">Escriba lo contado</p>
                            @elseif (abs($diferencia) < 1)
                                <p class="px-3 py-2.5 rounded-xl bg-emerald-50 text-sm font-bold text-emerald-700">La caja cuadra</p>
                            @elseif ($diferencia > 0)
                                <p class="px-3 py-2.5 rounded-xl bg-amber-50 text-sm font-bold text-amber-700">Sobran {{ $pesos($diferencia) }}</p>
                            @else
                                <p class="px-3 py-2.5 rounded-xl bg-red-50 text-sm font-bold text-red-700">Faltan {{ $pesos(abs($diferencia)) }}</p>
                            @endif
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Quién cierra</label>
                            <input type="text" wire:model="cashier" maxlength="100" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Notas</label>
                            <textarea wire:model="notes" rows="1" maxlength="1000" placeholder="Opcional" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            wire:confirm="¿Cerrar la caja? Las ventas de este turno quedan cerradas y la caja nueva arranca en cero."
                            class="px-6 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-bold shadow-sm transition"
                        >
                            Cerrar caja
                        </button>
                    </div>
                </div>
            </form>

            {{-- Informes --}}
            @php $informe = $this->report; @endphp
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200">
                    <h2 class="font-bold text-slate-700">Informes</h2>
                </div>

                <div class="p-5 flex flex-col gap-4">
                    <div class="flex flex-wrap gap-1">
                        @foreach (['today' => 'Hoy', 'week' => 'Esta semana', 'month' => 'Este mes', 'last-month' => 'Mes pasado'] as $valor => $etiqueta)
                            <button type="button" wire:click="setReportRange('{{ $valor }}')" class="px-3 py-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 text-xs font-semibold transition">{{ $etiqueta }}</button>
                        @endforeach
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Desde</label>
                            <input type="date" wire:model.live="from" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Hasta</label>
                            <input type="date" wire:model.live="to" class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <dt class="text-xs text-slate-400">Vendido</dt>
                            <dd class="font-bold text-slate-800">{{ $pesos($informe['totals']['revenue']) }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <dt class="text-xs text-slate-400">Costo</dt>
                            <dd class="font-bold text-slate-800">{{ $pesos($informe['totals']['cost']) }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <dt class="text-xs text-slate-400">Ganancia</dt>
                            <dd class="font-bold {{ $informe['totals']['profit'] < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $pesos($informe['totals']['profit']) }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2">
                            <dt class="text-xs text-slate-400">Ventas &middot; cierres</dt>
                            <dd class="font-bold text-slate-800">{{ number_format($informe['totals']['sales_count'], 0, ',', '.') }} &middot; {{ $informe['closings'] }}</dd>
                        </div>
                    </dl>

                    {{-- Enlaces normales y no ventanas nuevas: el PDF se descarga
                         y la pantalla se queda donde estaba, también en el
                         programa de escritorio. --}}
                    @php $rango = ['desde' => $informe['from']->toDateString(), 'hasta' => $informe['to']->toDateString()]; @endphp
                    <a href="{{ route('cash-closing.report-pdf', $rango) }}" class="w-full text-center px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold transition">
                        Descargar informe de ventas y ganancias (PDF)
                    </a>
                    <a href="{{ route('cash-closing.history-pdf', $rango) }}" class="w-full text-center px-4 py-2.5 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-sm font-bold transition">
                        Descargar historial de cierres (PDF)
                    </a>
                </div>
            </div>
        </div>

        {{-- Historial de cierres --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200">
                <h2 class="font-bold text-slate-700">Historial de cierres</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="p-4">Cierre</th>
                            <th class="p-4">Período</th>
                            <th class="p-4 text-center">Ventas</th>
                            <th class="p-4 text-right">Vendido</th>
                            <th class="p-4 text-right">Ganancia</th>
                            <th class="p-4 text-right">Efectivo esperado</th>
                            <th class="p-4 text-right">Contado</th>
                            <th class="p-4 text-right">Diferencia</th>
                            <th class="p-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($closings as $cierre)
                            <tr wire:key="cierre-{{ $cierre->id }}" class="hover:bg-slate-50 transition">
                                <td class="p-4">
                                    <p class="font-semibold text-slate-800">{{ $cierre->number }}</p>
                                    @if ($cierre->cashier)
                                        <p class="text-xs text-slate-400">{{ $cierre->cashier }}</p>
                                    @endif
                                </td>
                                <td class="p-4 text-slate-500 whitespace-nowrap">
                                    {{ $cierre->opened_at->format('d/m/Y H:i') }}<br>
                                    {{ $cierre->closed_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="p-4 text-center">{{ $cierre->sales_count }}</td>
                                <td class="p-4 text-right font-semibold text-slate-800">{{ $pesos($cierre->revenue) }}</td>
                                <td class="p-4 text-right font-semibold {{ $cierre->profit < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ $pesos($cierre->profit) }}</td>
                                <td class="p-4 text-right">{{ $pesos($cierre->expected_cash) }}</td>
                                <td class="p-4 text-right">{{ $cierre->counted_cash === null ? '—' : $pesos($cierre->counted_cash) }}</td>
                                <td @class([
                                    'p-4 text-right font-semibold',
                                    'text-slate-400' => $cierre->difference === null || abs($cierre->difference) < 1,
                                    'text-amber-600' => $cierre->difference !== null && $cierre->difference >= 1,
                                    'text-red-600' => $cierre->difference !== null && $cierre->difference <= -1,
                                ])>
                                    {{ $cierre->difference === null ? '—' : $pesos($cierre->difference) }}
                                </td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <a href="{{ route('cash-closing.pdf', $cierre) }}" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">PDF</a>
                                    @if ($cierre->id === $ultimoCierreId)
                                        <span class="text-slate-300 mx-1">|</span>
                                        <button type="button" wire:click="confirmDeleteClosing({{ $cierre->id }})" class="text-red-500 hover:text-red-700 font-semibold text-xs">
                                            Anular
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="p-10 text-center text-slate-400">Todavía no se ha cerrado la caja.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($closings->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $closings->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Anular el último cierre: pide la clave del dueño. --}}
    @if ($confirmingClosingId)
        @php $cierreAAnular = \App\Models\CashClosing::find($confirmingClosingId); @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="cancelDeleteClosing"></div>

            <form wire:submit="deleteClosing" class="relative bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-red-50 flex items-center justify-center text-2xl">&#9888;</div>

                <h3 class="font-bold text-slate-800 mb-2">¿Anular el cierre {{ $cierreAAnular?->number }}?</h3>
                <p class="text-sm text-slate-500 mb-5">
                    Sus {{ $cierreAAnular?->sales_count }} ventas vuelven a la caja abierta para cerrarlas de nuevo. Las ventas no se borran.
                </p>

                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 text-left">Clave de administrador</label>
                <input
                    type="password"
                    wire:model="deletePassword"
                    autofocus
                    class="w-full px-3 py-2.5 mb-1 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none text-sm text-center tracking-widest"
                >
                @error('deletePassword') <p class="text-xs text-red-600 mb-2 text-left">{{ $message }}</p> @enderror

                <div class="flex gap-3 mt-4">
                    <button type="button" wire:click="cancelDeleteClosing" class="flex-1 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold transition">Anular</button>
                </div>
            </form>
        </div>
    @endif
</div>
