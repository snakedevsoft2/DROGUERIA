<div class="p-6">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

        <div class="flex flex-wrap justify-between items-start gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Reportes de ventas</h1>
                <p class="text-sm text-slate-500">Resumen de ingresos e historial de transacciones.</p>
            </div>

            <div class="flex items-center gap-2">
                <button type="button" wire:click="$toggle('showPasswordForm')" class="px-3 py-2 rounded-xl border border-slate-300 text-slate-600 text-sm font-semibold hover:bg-white transition">
                    Clave para eliminar
                </button>
            </div>
        </div>

        @if ($this->usingDefaultPassword)
            <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-2xl px-4 py-3 text-sm">
La clave para borrar inventario es la de fábrica (<strong>1234</strong>). Cámbiela para que nadie más pueda eliminar productos ni lotes.
            </div>
        @endif

        @if ($showPasswordForm)
            <form wire:submit="changePassword" class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Clave actual</label>
                    <input type="password" wire:model="current_password" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                    @error('current_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Clave nueva</label>
                    <input type="password" wire:model="new_password" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                    @error('new_password') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Repita la clave nueva</label>
                    <input type="password" wire:model="new_password_confirmation" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                </div>
                <div class="md:col-span-3 flex justify-end gap-3">
                    <button type="button" wire:click="$set('showPasswordForm', false)" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition">Guardar clave</button>
                </div>
            </form>
        @endif

        {{-- Filtros de rango --}}
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap gap-4 items-end">
            <div class="flex gap-1">
                @foreach (['today' => 'Hoy', 'yesterday' => 'Ayer', 'week' => 'Esta semana', 'month' => 'Este mes'] as $value => $label)
                    <button
                        type="button"
                        wire:click="setRange('{{ $value }}')"
                        class="px-3 py-2 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 text-sm font-semibold transition"
                    >{{ $label }}</button>
                @endforeach
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Desde</label>
                <input type="date" wire:model.live="from" class="px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Hasta</label>
                <input type="date" wire:model.live="to" class="px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Método de pago</label>
                <select wire:model.live="paymentMethod" class="px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                    <option value="all">Todos</option>
                    <option value="cash">Efectivo</option>
                    <option value="card">Tarjeta</option>
                    <option value="transfer">Transferencia</option>
                </select>
            </div>
        </div>

        {{-- Indicadores --}}
        @php $summary = $this->summary; @endphp
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @php
                $cards = [
                    ['label' => 'Ingresos', 'value' => '$'.number_format($summary['revenue'], 0, ',', '.'), 'accent' => 'text-blue-600'],
                    ['label' => 'Transacciones', 'value' => number_format($summary['transactions']), 'accent' => 'text-slate-900'],
                    ['label' => 'Ticket promedio', 'value' => '$'.number_format($summary['average'], 0, ',', '.'), 'accent' => 'text-slate-900'],
                    ['label' => 'Descuentos', 'value' => '$'.number_format($summary['discount'], 0, ',', '.'), 'accent' => 'text-amber-600'],
                ];
            @endphp

            @foreach ($cards as $card)
                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200">
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $card['label'] }}</span>
                    <span class="block text-2xl font-black mt-1 {{ $card['accent'] }}">{{ $card['value'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Resumen diario --}}
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200">
                    <h2 class="font-bold text-slate-700">Resumen diario</h2>
                </div>
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="p-4">Fecha</th>
                            <th class="p-4 text-center">Transacciones</th>
                            <th class="p-4 text-right">Ingresos</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($this->daily as $day)
                            <tr wire:key="day-{{ $day->day }}" class="hover:bg-slate-50 transition">
                                <td class="p-4 font-semibold text-slate-800">
                                    {{ \Illuminate\Support\Carbon::parse($day->day)->translatedFormat('D d M Y') }}
                                </td>
                                <td class="p-4 text-center">{{ $day->transactions }}</td>
                                <td class="p-4 text-right font-bold text-slate-800">${{ number_format($day->revenue, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-8 text-center text-slate-400">Sin ventas en el rango seleccionado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Métodos de pago + top productos --}}
            <div class="flex flex-col gap-6">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200">
                        <h2 class="font-bold text-slate-700">Por método de pago</h2>
                    </div>
                    <div class="p-4 space-y-3 text-sm">
                        @php $labels = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia']; @endphp
                        @forelse ($this->byPaymentMethod as $row)
                            @php $share = $summary['revenue'] > 0 ? ($row->revenue / $summary['revenue']) * 100 : 0; @endphp
                            <div wire:key="pm-{{ $row->payment_method }}">
                                <div class="flex justify-between mb-1">
                                    <span class="text-slate-600">{{ $labels[$row->payment_method] ?? $row->payment_method }}</span>
                                    <span class="font-semibold text-slate-800">${{ number_format($row->revenue, 0, ',', '.') }}</span>
                                </div>
                                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-blue-500 rounded-full" style="width: {{ round($share, 1) }}%"></div>
                                </div>
                                <span class="text-[11px] text-slate-400">{{ $row->transactions }} transacciones &middot; {{ number_format($share, 1) }}%</span>
                            </div>
                        @empty
                            <p class="text-slate-400 text-center py-4">Sin datos.</p>
                        @endforelse
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="p-4 bg-slate-50 border-b border-slate-200">
                        <h2 class="font-bold text-slate-700">Más vendidos</h2>
                    </div>
                    <ul class="divide-y divide-slate-100 text-sm">
                        @forelse ($this->topProducts as $product)
                            <li wire:key="top-{{ $loop->index }}" class="p-4 flex justify-between items-center gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800 truncate">{{ $product->name }}</p>
                                    @if ($product->presentation)
                                        <p class="text-xs text-slate-400 truncate">{{ $product->presentation }}</p>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-bold text-slate-800">{{ $product->units }} u.</p>
                                    <p class="text-xs text-slate-400">${{ number_format($product->revenue, 0, ',', '.') }}</p>
                                </div>
                            </li>
                        @empty
                            <li class="p-8 text-center text-slate-400">Sin datos.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Historial de transacciones --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 bg-slate-50 border-b border-slate-200">
                <h2 class="font-bold text-slate-700">Historial de transacciones</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="p-4">Factura</th>
                            <th class="p-4">Fecha</th>
                            <th class="p-4 text-center">Pago</th>
                            <th class="p-4 text-right">Subtotal</th>
                            <th class="p-4 text-right">Total</th>
                            <th class="p-4 text-center">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($sales as $sale)
                            <tr wire:key="sale-{{ $sale->id }}" class="hover:bg-slate-50 transition">
                                <td class="p-4 font-semibold text-slate-800">{{ $sale->invoice_number }}</td>
                                <td class="p-4 text-slate-500">{{ $sale->created_at->format('d/m/Y H:i') }}</td>
                                <td class="p-4 text-center">
                                    <span class="px-2 py-1 rounded-full bg-slate-100 text-slate-600 text-xs font-semibold">
                                        {{ ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia'][$sale->payment_method] ?? $sale->payment_method }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">${{ number_format($sale->subtotal, 0, ',', '.') }}</td>
                                <td class="p-4 text-right font-bold text-slate-900">${{ number_format($sale->total, 0, ',', '.') }}</td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <button type="button" wire:click="toggleSale({{ $sale->id }})" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">
                                        {{ $expandedSaleId === $sale->id ? 'Ocultar' : 'Ver' }}
                                    </button>
                                    <span class="text-slate-300 mx-1">|</span>
                                    <a href="{{ route('receipt', $sale->id) }}" target="_blank" class="text-slate-500 hover:text-blue-600 font-semibold text-xs">
                                        Tirilla
                                    </a>
                                    <span class="text-slate-300 mx-1">|</span>
                                    <a href="{{ route('receipt', ['sale' => $sale->id, 'formato' => 'carta']) }}" target="_blank" class="text-slate-500 hover:text-blue-600 font-semibold text-xs">
                                        Factura
                                    </a>
                                    <span class="text-slate-300 mx-1">|</span>
                                    <button type="button" wire:click="confirmDeleteSale({{ $sale->id }})" class="text-red-500 hover:text-red-700 font-semibold text-xs">
                                        Eliminar
                                    </button>
                                </td>
                            </tr>

                            @if ($expandedSaleId === $sale->id)
                                <tr wire:key="sale-detail-{{ $sale->id }}" class="bg-slate-50">
                                    <td colspan="6" class="p-4">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="text-slate-400 uppercase tracking-wider">
                                                    <th class="py-2 text-left">Producto</th>
                                                    <th class="py-2 text-left">Lote</th>
                                                    <th class="py-2 text-center">Cant.</th>
                                                    <th class="py-2 text-right">Precio</th>
                                                    <th class="py-2 text-right">Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody class="text-slate-600">
                                                @foreach ($sale->details as $detail)
                                                    <tr wire:key="detail-{{ $detail->id }}" class="border-t border-slate-200">
                                                        <td class="py-2">{{ $detail->product?->name ?? $detail->product_name ?? 'Producto eliminado' }}</td>
                                                        <td class="py-2">{{ $detail->batch?->batch_number ?? '—' }}</td>
                                                        <td class="py-2 text-center">{{ $detail->quantity }}</td>
                                                        <td class="py-2 text-right">${{ number_format($detail->unit_price, 0, ',', '.') }}</td>
                                                        <td class="py-2 text-right font-semibold">${{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>

                                        <div class="mt-3 pt-3 border-t border-slate-200 flex justify-end gap-6 text-xs text-slate-500">
                                            <span>Descuento: <strong class="text-slate-700">${{ number_format($sale->discount, 0, ',', '.') }}</strong></span>
                                            <span>Recibido: <strong class="text-slate-700">${{ number_format($sale->paid_amount, 0, ',', '.') }}</strong></span>
                                            <span>Cambio: <strong class="text-slate-700">${{ number_format($sale->change_amount, 0, ',', '.') }}</strong></span>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="p-10 text-center text-slate-400">Sin transacciones en el rango seleccionado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($sales->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $sales->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Eliminar una venta: pide la clave del dueño y devuelve el stock. --}}
    @if ($confirmingSaleId)
        @php $ventaABorrar = \App\Models\Sale::find($confirmingSaleId); @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="cancelDeleteSale"></div>

            <form wire:submit="deleteSale" class="relative bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-red-50 flex items-center justify-center text-2xl">&#9888;</div>

                <h3 class="font-bold text-slate-800 mb-2">¿Eliminar la venta {{ $ventaABorrar?->invoice_number }}?</h3>
                <p class="text-sm text-slate-500 mb-5">
                    Se borra del historial por ${{ number_format($ventaABorrar?->total ?? 0, 0, ',', '.') }} y las unidades vuelven a su lote en el inventario.
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
                    <button type="button" wire:click="cancelDeleteSale" class="flex-1 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold transition">Eliminar</button>
                </div>
            </form>
        </div>
    @endif
</div>
