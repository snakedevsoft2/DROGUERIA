<div class="p-6 print:p-0">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

        {{-- Encabezado --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Lista de precios</h1>
                <p class="text-sm text-slate-500">
                    Precio al público por unidad y por caja.
                    <span class="hidden print:inline">{{ config('drogueria.name') }} &middot; {{ now()->format('d/m/Y') }}</span>
                </p>
            </div>

            <button
                type="button"
                x-on:click="window.print()"
                class="print:hidden px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-sm transition"
            >
                Imprimir lista
            </button>
        </div>

        {{-- Filtros --}}
        <div class="print:hidden bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-64">
                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nombre, código o presentación..."
                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-sm"
                >
                <svg class="w-5 h-5 absolute left-3 top-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model.live="showInactive" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                Incluir inactivos
            </label>

            <span class="text-sm text-slate-400">{{ $products->count() }} productos</span>
        </div>

        {{-- Tabla de precios --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden print:shadow-none print:rounded-none">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="p-4 print:p-2">Producto</th>
                            <th class="p-4 print:p-2">Presentación</th>
                            <th class="p-4 print:p-2 text-center">Unidades por caja</th>
                            <th class="p-4 print:p-2 text-right">Precio por unidad</th>
                            <th class="p-4 print:p-2 text-right">Precio por caja</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm">
                        @forelse ($products as $product)
                            <tr wire:key="price-{{ $product->id }}" @class([
                                'hover:bg-slate-50 transition break-inside-avoid',
                                'opacity-60' => ! $product->is_active,
                            ])>
                                <td class="p-4 print:p-2">
                                    <p class="font-semibold text-slate-800">
                                        {{ $product->name }}
                                        @if ($product->requires_prescription)
                                            <span class="ml-1 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 align-middle">Rx</span>
                                        @endif
                                        @unless ($product->is_active)
                                            <span class="ml-1 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 align-middle">Inactivo</span>
                                        @endunless
                                    </p>
                                    <p class="text-xs text-slate-400">{{ $product->barcode }}</p>
                                </td>
                                <td class="p-4 print:p-2 text-slate-500">{{ $product->presentation ?: '—' }}</td>
                                <td class="p-4 print:p-2 text-center">{{ $product->units_per_package }}</td>
                                <td class="p-4 print:p-2 text-right font-semibold text-slate-800">
                                    ${{ number_format($product->selling_price, 0, ',', '.') }}
                                </td>
                                <td class="p-4 print:p-2 text-right font-bold text-blue-700">
                                    ${{ number_format($product->package_price, 0, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-10 text-center text-slate-400">
                                    No hay productos con precio registrado. Se agregan desde Inventario.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
