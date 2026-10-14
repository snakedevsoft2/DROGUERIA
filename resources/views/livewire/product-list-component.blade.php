<div class="p-6 print:p-0">
    <div class="max-w-7xl mx-auto flex flex-col gap-6 tirilla:gap-3">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl tirilla:text-lg font-extrabold text-slate-900">Listado de productos</h1>
                <p class="text-sm tirilla:text-xs text-slate-500">
                    Todos los productos guardados con su costo y precio de venta por unidad.
                    <span class="hidden print:inline tirilla:block">{{ config('drogueria.name') }} &middot; {{ now()->format('d/m/Y') }}</span>
                </p>
            </div>

            <button
                type="button"
                x-on:click="window.print()"
                class="print:hidden px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-sm transition"
            >
                Imprimir listado
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 print:grid-cols-3 tirilla:grid-cols-1 gap-4 tirilla:gap-1">
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 print:shadow-none tirilla:p-0 tirilla:border-0 tirilla:rounded-none tirilla:flex tirilla:flex-wrap tirilla:items-baseline tirilla:justify-between tirilla:gap-x-2">
                <p class="text-xs tirilla:text-[10px] font-bold uppercase tracking-wider tirilla:tracking-normal text-slate-500">Costo total del inventario</p>
                <p class="text-2xl tirilla:text-sm font-extrabold text-slate-900">${{ number_format($this->inventoryCost, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 tirilla:hidden">Unidades en stock &times; costo por unidad</p>
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 print:shadow-none tirilla:p-0 tirilla:border-0 tirilla:rounded-none tirilla:flex tirilla:flex-wrap tirilla:items-baseline tirilla:justify-between tirilla:gap-x-2">
                <p class="text-xs tirilla:text-[10px] font-bold uppercase tracking-wider tirilla:tracking-normal text-slate-500">Total de venta del inventario</p>
                <p class="text-2xl tirilla:text-sm font-extrabold text-slate-900">${{ number_format($this->inventorySaleValue, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 tirilla:hidden">Unidades en stock &times; precio de venta</p>
            </div>
            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 print:shadow-none tirilla:p-0 tirilla:border-0 tirilla:rounded-none tirilla:flex tirilla:flex-wrap tirilla:items-baseline tirilla:justify-between tirilla:gap-x-2">
                <p class="text-xs tirilla:text-[10px] font-bold uppercase tracking-wider tirilla:tracking-normal text-slate-500">Ganancia estimada</p>
                <p class="text-2xl tirilla:text-sm font-extrabold text-emerald-600">${{ number_format($this->inventorySaleValue - $this->inventoryCost, 0, ',', '.') }}</p>
                <p class="text-xs text-slate-400 tirilla:hidden">Venta menos costo{{ trim($search) !== '' ? ' (solo productos filtrados)' : '' }}</p>
            </div>
        </div>

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

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden print:shadow-none print:rounded-none tirilla:border-0">
            <div class="overflow-x-auto">
                {{-- En el rollo no caben cinco columnas: cada producto pasa a
                     ser un bloque, con el nombre arriba y las cifras debajo
                     en dos columnas, cada una con su etiqueta. --}}
                <table class="w-full text-left border-collapse tirilla:block">
                    <thead class="tirilla:hidden">
                        <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="p-4 print:p-2">Producto</th>
                            <th class="p-4 print:p-2 text-right">Stock</th>
                            <th class="p-4 print:p-2 text-right">Costo por unidad</th>
                            <th class="p-4 print:p-2 text-right">Precio de venta por unidad</th>
                            <th class="p-4 print:p-2 text-right">Ganancia por unidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm tirilla:block tirilla:text-xs tirilla:border-t tirilla:border-slate-300">
                        @forelse ($products as $product)
                            <tr wire:key="list-{{ $product->id }}" @class([
                                'hover:bg-slate-50 transition break-inside-avoid',
                                'tirilla:grid tirilla:grid-cols-2 tirilla:gap-x-3 tirilla:py-1.5',
                                'opacity-60' => ! $product->is_active,
                            ])>
                                <td class="p-4 print:p-2 tirilla:p-0 tirilla:col-span-2">
                                    <p class="font-semibold text-slate-800">
                                        {{ $product->name }}
                                        @unless ($product->is_active)
                                            <span class="ml-1 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-slate-200 text-slate-600 align-middle">Inactivo</span>
                                        @endunless
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ $product->barcode }} @if ($product->presentation) &middot; {{ $product->presentation }} @endif
                                    </p>
                                </td>
                                <td class="p-4 print:p-2 tirilla:p-0 tirilla:flex tirilla:flex-wrap tirilla:gap-x-1 text-right text-slate-600">
                                    <span class="hidden tirilla:inline">Stock</span>
                                    <span class="tirilla:ml-auto">{{ number_format((int) $product->total_stock, 0, ',', '.') }}</span>
                                </td>
                                <td class="p-4 print:p-2 tirilla:p-0 tirilla:flex tirilla:flex-wrap tirilla:gap-x-1 text-right text-slate-600">
                                    <span class="hidden tirilla:inline">Costo</span>
                                    <span class="tirilla:ml-auto">${{ number_format($product->unit_cost, 0, ',', '.') }}</span>
                                </td>
                                <td class="p-4 print:p-2 tirilla:p-0 tirilla:flex tirilla:flex-wrap tirilla:gap-x-1 text-right font-semibold text-slate-800">
                                    <span class="hidden tirilla:inline font-normal text-slate-600">Venta</span>
                                    <span class="tirilla:ml-auto">${{ number_format($product->selling_price, 0, ',', '.') }}</span>
                                </td>
                                <td class="p-4 print:p-2 tirilla:p-0 tirilla:flex tirilla:flex-wrap tirilla:gap-x-1 text-right font-semibold text-emerald-600">
                                    <span class="hidden tirilla:inline font-normal text-slate-600">Ganancia</span>
                                    <span class="tirilla:ml-auto">${{ number_format($product->selling_price - $product->unit_cost, 0, ',', '.') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-10 text-center text-slate-400">
                                    No se encontraron productos.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
