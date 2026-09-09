<div class="p-6">
    <div class="max-w-7xl mx-auto flex flex-col gap-6">

        {{-- Encabezado y alertas --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Inventario</h1>
                <p class="text-sm text-slate-500">Productos, lotes y fechas de vencimiento.</p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="$set('filter', 'low')"
                    class="px-3 py-2 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-sm font-semibold hover:bg-amber-100 transition"
                >
                    Stock bajo
                    <span class="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-amber-500 text-white text-xs">
                        {{ $this->lowStockCount }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="$set('filter', 'expiring')"
                    class="px-3 py-2 rounded-xl bg-orange-50 border border-orange-200 text-orange-800 text-sm font-semibold hover:bg-orange-100 transition"
                >
                    Por vencer (90d)
                    <span class="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-orange-500 text-white text-xs">
                        {{ $this->expiringCount }}
                    </span>
                </button>

                @if ($this->expiredCount > 0)
                    <span class="px-3 py-2 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm font-semibold">
                        Vencidos
                        <span class="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-red-600 text-white text-xs">
                            {{ $this->expiredCount }}
                        </span>
                    </span>
                @endif

                <button type="button" wire:click="createProduct" class="px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold shadow-sm transition">
                    + Nuevo producto
                </button>
            </div>
        </div>

        {{-- Filtros --}}
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 flex flex-wrap gap-3 items-center">
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

            <div class="flex gap-1">
                @foreach (['all' => 'Todos', 'low' => 'Stock bajo', 'expiring' => 'Por vencer', 'inactive' => 'Inactivos'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('filter', '{{ $value }}')"
                        @class([
                            'px-3 py-2 rounded-lg text-sm font-semibold transition',
                            'bg-blue-600 text-white' => $filter === $value,
                            'bg-slate-100 text-slate-600 hover:bg-slate-200' => $filter !== $value,
                        ])
                    >{{ $label }}</button>
                @endforeach
            </div>
        </div>

        {{-- Tabla de productos --}}
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                            <th class="p-4">Producto</th>
                            <th class="p-4 text-right">Costo</th>
                            <th class="p-4 text-right">Venta</th>
                            <th class="p-4 text-center">Stock</th>
                            <th class="p-4">Lotes</th>
                            <th class="p-4 text-center">Estado</th>
                            <th class="p-4 text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700 text-sm">
                        @forelse ($products as $product)
                            @php
                                $stock = (int) ($product->total_stock ?? 0);
                                $isLow = $stock <= $product->min_stock;
                            @endphp
                            <tr wire:key="product-{{ $product->id }}" class="hover:bg-slate-50 align-top transition">
                                <td class="p-4">
                                    <p class="font-semibold text-slate-800">
                                        {{ $product->name }}
                                        @if ($product->requires_prescription)
                                            <span class="ml-1 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 align-middle">Rx</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ $product->barcode }} @if ($product->presentation) &middot; {{ $product->presentation }} @endif
                                    </p>
                                </td>
                                <td class="p-4 text-right text-slate-500">${{ number_format($product->cost_price, 2) }}</td>
                                <td class="p-4 text-right font-semibold text-slate-800">${{ number_format($product->selling_price, 2) }}</td>
                                <td class="p-4 text-center">
                                    <span @class([
                                        'inline-block px-2.5 py-1 rounded-full text-xs font-bold',
                                        'bg-red-100 text-red-800' => $isLow,
                                        'bg-emerald-100 text-emerald-800' => ! $isLow,
                                    ])>{{ $stock }}</span>
                                    <p class="text-[11px] text-slate-400 mt-1">mín. {{ $product->min_stock }}</p>
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col gap-1">
                                        @forelse ($product->batches as $batch)
                                            @php
                                                $expiration = \Illuminate\Support\Carbon::parse($batch->expiration_date);
                                                $days = now()->startOfDay()->diffInDays($expiration, false);
                                            @endphp
                                            <div wire:key="batch-{{ $batch->id }}" class="flex items-center gap-2 text-xs">
                                                <span @class([
                                                    'px-1.5 py-0.5 rounded font-semibold',
                                                    'bg-red-100 text-red-800' => $days <= 0,
                                                    'bg-orange-100 text-orange-800' => $days > 0 && $days <= 90,
                                                    'bg-slate-100 text-slate-600' => $days > 90,
                                                ])>
                                                    {{ $batch->batch_number }}
                                                </span>
                                                <span class="text-slate-400">
                                                    {{ $expiration->format('d/m/Y') }} &middot; {{ $batch->stock }} u.
                                                    @if ($days <= 0) <span class="text-red-600 font-semibold">vencido</span> @endif
                                                </span>
                                                <button type="button" wire:click="editBatch({{ $batch->id }})" class="text-blue-500 hover:text-blue-700 font-semibold">editar</button>
                                                <button
                                                    type="button"
                                                    wire:click="deleteBatch({{ $batch->id }})"
                                                    wire:confirm="¿Eliminar el lote {{ $batch->batch_number }}?"
                                                    class="text-red-400 hover:text-red-600 font-semibold"
                                                >x</button>
                                            </div>
                                        @empty
                                            <span class="text-xs text-slate-400">Sin lotes registrados</span>
                                        @endforelse

                                        <button type="button" wire:click="createBatch({{ $product->id }})" class="text-xs text-blue-600 hover:text-blue-800 font-semibold text-left mt-1">
                                            + Agregar lote
                                        </button>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <span @class([
                                        'px-2 py-1 rounded-full text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800' => $product->is_active,
                                        'bg-slate-200 text-slate-600' => ! $product->is_active,
                                    ])>{{ $product->is_active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="p-4 text-center whitespace-nowrap">
                                    <button type="button" wire:click="editProduct({{ $product->id }})" class="text-blue-600 hover:text-blue-800 font-semibold text-xs">Editar</button>
                                    <span class="text-slate-300 mx-1">|</span>
                                    <button type="button" wire:click="confirmDelete({{ $product->id }})" class="text-red-500 hover:text-red-700 font-semibold text-xs">Eliminar</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-10 text-center text-slate-400">No se encontraron productos con estos criterios.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($products->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $products->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Modal producto --}}
    @if ($showProductModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('showProductModal', false)"></div>

            <form wire:submit="saveProduct" class="relative bg-white w-full max-w-2xl rounded-2xl shadow-2xl my-8">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800">{{ $productId ? 'Editar producto' : 'Nuevo producto' }}</h3>
                    <button type="button" wire:click="$set('showProductModal', false)" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Nombre *</label>
                        <input type="text" wire:model="name" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Código de barras *</label>
                        <input type="text" wire:model="barcode" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('barcode') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Presentación</label>
                        <input type="text" wire:model="presentation" placeholder="Caja x 20 tabletas" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('presentation') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Precio de costo *</label>
                        <input type="number" step="0.01" min="0" wire:model="cost_price" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('cost_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Precio de venta *</label>
                        <input type="number" step="0.01" min="0" wire:model="selling_price" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('selling_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Stock mínimo *</label>
                        <input type="number" min="0" wire:model="min_stock" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('min_stock') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center gap-6 pt-6">
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="requires_prescription" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Requiere fórmula
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input type="checkbox" wire:model="is_active" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                            Activo
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Descripción</label>
                        <textarea wire:model="description" rows="3" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"></textarea>
                        @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3 justify-end rounded-b-2xl">
                    <button type="button" wire:click="$set('showProductModal', false)" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-white transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition">
                        <span wire:loading.remove wire:target="saveProduct">Guardar</span>
                        <span wire:loading wire:target="saveProduct">Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Modal lote --}}
    @if ($showBatchModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('showBatchModal', false)"></div>

            <form wire:submit="saveBatch" class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800">{{ $batchId ? 'Editar lote' : 'Nuevo lote' }}</h3>
                    <button type="button" wire:click="$set('showBatchModal', false)" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
                </div>

                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Número de lote *</label>
                        <input type="text" wire:model="batch_number" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('batch_number') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Fecha de vencimiento *</label>
                        <input type="date" wire:model="expiration_date" min="{{ now()->addDay()->toDateString() }}" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('expiration_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Unidades en el lote *</label>
                        <input type="number" min="0" wire:model="stock" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('stock') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" wire:model="batch_is_active" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Lote habilitado para la venta
                    </label>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3 justify-end rounded-b-2xl">
                    <button type="button" wire:click="$set('showBatchModal', false)" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-white transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition">Guardar lote</button>
                </div>
            </form>
        </div>
    @endif

    {{-- Confirmación de eliminación --}}
    @if ($confirmingProductId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="$set('confirmingProductId', null)"></div>

            <div class="relative bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 text-center">
                <h3 class="font-bold text-slate-800 mb-2">¿Eliminar este producto?</h3>
                <p class="text-sm text-slate-500 mb-6">
                    Si el producto tiene lotes registrados se desactivará en lugar de borrarse, para conservar el historial de ventas.
                </p>
                <div class="flex gap-3">
                    <button type="button" wire:click="$set('confirmingProductId', null)" class="flex-1 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-slate-50 transition">Cancelar</button>
                    <button type="button" wire:click="deleteProduct" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold transition">Eliminar</button>
                </div>
            </div>
        </div>
    @endif
</div>
