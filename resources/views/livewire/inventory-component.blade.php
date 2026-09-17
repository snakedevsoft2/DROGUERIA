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
                    Por vencer ({{ $this->expiryAlertMonths() }} meses)
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
                            <th class="p-4 text-right">Costo /u</th>
                            <th class="p-4 text-right">Venta /u</th>
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
                                // Lo que vence dentro de la ventana de alerta no cuenta
                                // como respaldo: el producto ya hay que reponerlo.
                                $vigente = (int) ($product->healthy_stock ?? 0);
                                $porVencer = max(0, $stock - $vigente);
                                $isLow = $vigente <= $product->min_stock;
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
                                <td class="p-4 text-right text-slate-500">
                                    ${{ number_format($product->unit_cost, 0, ',', '.') }}
                                    @if ($product->units_per_package > 1)
                                        <p class="text-[11px] text-slate-400">
                                            caja ${{ number_format($product->cost_price, 0, ',', '.') }} &middot; {{ $product->units_per_package }} u.
                                        </p>
                                    @endif
                                </td>
                                <td class="p-4 text-right font-semibold text-slate-800">${{ number_format($product->selling_price, 0, ',', '.') }}</td>
                                <td class="p-4 text-center">
                                    <span @class([
                                        'inline-block px-2.5 py-1 rounded-full text-xs font-bold',
                                        'bg-red-100 text-red-800' => $isLow,
                                        'bg-emerald-100 text-emerald-800' => ! $isLow,
                                    ])>{{ $stock }}</span>
                                    <p class="text-[11px] text-slate-400 mt-1">mín. {{ $product->min_stock }}</p>
                                    @if ($porVencer > 0)
                                        <p class="text-[11px] text-orange-600 font-semibold">{{ $porVencer }} por vencer</p>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <div class="flex flex-col gap-1">
                                        @forelse ($product->batches as $batch)
                                            @php
                                                $expiration = \Illuminate\Support\Carbon::parse($batch->expiration_date);
                                                $days = now()->startOfDay()->diffInDays($expiration, false);
                                            @endphp
                                            <div wire:key="batch-{{ $batch->id }}" @class([
                                                'flex items-center gap-2 text-xs',
                                                'opacity-50' => ! $batch->is_active,
                                            ])>
                                                <span @class([
                                                    'px-1.5 py-0.5 rounded font-semibold',
                                                    'bg-red-100 text-red-800' => $days <= 0,
                                                    'bg-orange-100 text-orange-800' => $days > 0 && $days <= $this->expiryAlertDays,
                                                    'bg-slate-100 text-slate-600' => $days > $this->expiryAlertDays,
                                                ])>
                                                    {{ $batch->batch_number }}
                                                </span>
                                                <span class="text-slate-400">
                                                    {{ $expiration->format('d/m/Y') }} &middot; {{ $batch->stock }} u.
                                                    @if ($batch->barcode && $batch->barcode !== $product->barcode)
                                                        <span class="text-slate-500" title="Este lote tiene su propio código de barras">&middot; {{ $batch->barcode }}</span>
                                                    @endif
                                                    @if ($days <= 0) <span class="text-red-600 font-semibold">vencido</span> @endif
                                                    @unless ($batch->is_active) <span class="text-slate-500 font-semibold">inactivo</span> @endunless
                                                </span>
                                                <button type="button" wire:click="editBatch({{ $batch->id }})" class="text-blue-500 hover:text-blue-700 font-semibold">editar</button>
                                                {{-- Desactivar aparta el lote de la venta sin borrarlo. --}}
                                                <button
                                                    type="button"
                                                    wire:click="toggleBatch({{ $batch->id }})"
                                                    @class([
                                                        'font-semibold',
                                                        'text-amber-600 hover:text-amber-700' => $batch->is_active,
                                                        'text-emerald-600 hover:text-emerald-700' => ! $batch->is_active,
                                                    ])
                                                >{{ $batch->is_active ? 'desactivar' : 'activar' }}</button>
                                                <button
                                                    type="button"
                                                    wire:click="confirmDeleteBatch({{ $batch->id }})"
                                                    class="text-red-400 hover:text-red-600 font-semibold"
                                                >eliminar</button>
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
                                    {{-- El estado se cambia con un clic: no hay que abrir el formulario. --}}
                                    <button
                                        type="button"
                                        wire:click="toggleProduct({{ $product->id }})"
                                        title="{{ $product->is_active ? 'Desactivar producto' : 'Activar producto' }}"
                                        @class([
                                            'px-2 py-1 rounded-full text-xs font-semibold transition',
                                            'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' => $product->is_active,
                                            'bg-slate-200 text-slate-600 hover:bg-slate-300' => ! $product->is_active,
                                        ])
                                    >{{ $product->is_active ? 'Activo' : 'Inactivo' }}</button>
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
            {{-- El fondo no cierra el formulario: un clic afuera borraba todo lo
                 que se llevaba escrito. Se sale con Cancelar o con la X. --}}
            <div class="absolute inset-0 bg-slate-900/50"></div>

            <form
                wire:submit="saveProduct"
                class="relative bg-white w-full max-w-4xl rounded-2xl shadow-2xl my-8"
                x-data="{
                    /* Si ya se escribió algo, salir pide confirmación: el
                       formulario solo se cierra cuando el usuario lo decide. */
                    tocado: false,
                    cerrar() {
                        if (this.tocado && ! confirm('Se perderá lo que escribió en este producto. ¿Salir de todas formas?')) {
                            return
                        }

                        $wire.set('showProductModal', false)
                    },
                    /* El lector de código de barras termina cada lectura con un
                       Enter. Sin esto ese Enter guardaba el producto a medias,
                       así que aquí solo pasa al campo siguiente. */
                    siguienteCampo(actual) {
                        const campos = [...$el.querySelectorAll('input, textarea, select')]
                            .filter(campo => !campo.disabled && campo.type !== 'hidden')
                        const siguiente = campos[campos.indexOf(actual) + 1]

                        if (siguiente) {
                            siguiente.focus()
                            siguiente.select?.()
                        }
                    },
                }"
                x-on:keydown.enter="if (['INPUT', 'SELECT'].includes($event.target.tagName)) { $event.preventDefault(); siguienteCampo($event.target) }"
                x-on:input="tocado = true"
            >
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800">{{ $productId ? 'Editar producto' : 'Nuevo producto' }}</h3>
                    <button type="button" x-on:click="cerrar()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
                </div>

                {{-- El código ya estaba registrado y el usuario decidió que es
                     el mismo producto: se avisa para que no crea que está
                     creando una ficha repetida. --}}
                @if ($fusionadoConExistente)
                    <div class="mx-6 mt-4 px-4 py-3 rounded-xl bg-blue-50 border border-blue-100 text-sm text-blue-900">
                        Este código de barras ya estaba registrado, así que se está trabajando sobre
                        <span class="font-bold">{{ $name }}</span>.
                        Lo que agregue abajo entra como <span class="font-semibold">lote nuevo</span>, con su propia fecha de vencimiento.
                    </div>
                @endif

                {{-- El código coincide con otro producto pero todavía no se
                     decidió qué hacer: se avisa y se deja elegir, en vez de
                     fusionar solo. Si no se toca nada, al guardar queda como
                     producto aparte (numerado si el nombre también se repite). --}}
                @if ($duplicadoProductId && ! $fusionadoConExistente)
                    <div class="mx-6 mt-4 px-4 py-3 rounded-xl bg-amber-50 border border-amber-100 text-sm text-amber-900 flex items-center justify-between gap-3">
                        <span>
                            Ya existe <span class="font-bold">{{ $duplicadoNombre }}</span> con este código.
                            Si guarda así, queda como <span class="font-semibold">producto aparte</span> (se numera si el nombre también se repite).
                        </span>
                        <button type="button" wire:click="usarProductoExistente" class="shrink-0 px-3 py-1.5 rounded-lg bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700">
                            Es el mismo: agregar lote
                        </button>
                    </div>
                @endif

                <div
                    wire:key="precios-{{ $productId ?? 'nuevo' }}"
                    class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4"
                    x-data="{
                        unidades: @js(max(1, (int) $units_per_package)),
                        costo: @js((float) $cost_price),
                        margen: @js((float) config('drogueria.inventory.default_margin_percent', 30)),
                        get costoUnidad() { return Math.round(this.costo / Math.max(1, this.unidades)) },
                        get sugerido() { return Math.ceil(this.costoUnidad * (1 + this.margen / 100) / 100) * 100 },
                        pesos(valor) { return '$' + Math.round(valor || 0).toLocaleString('es-CO') },
                        usarSugerido() {
                            // Se avisa a Livewire con un evento para que recoja el
                            // valor, sin mandar nada al servidor todavía.
                            this.$refs.precio.value = this.sugerido
                            this.$refs.precio.dispatchEvent(new Event('input'))
                        },
                    }"
                >
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Nombre *</label>
                        <input type="text" wire:model="name" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Código de barras *</label>
                        {{-- .live.debounce y no .blur: en la versión instalada de
                             Livewire, wire:model.blur no dispara la actualización
                             al salir del campo (probado en navegador real). El
                             debounce logra lo mismo que buscaba el .blur —una
                             sola consulta, no una por dígito— sin depender de ese
                             evento. Si el código ya existe, se avisa arriba y el
                             usuario decide si fusiona o guarda aparte. --}}
                        <input type="text" wire:model.live.debounce.500ms="barcode" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('barcode') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Presentación</label>
                        <input type="text" wire:model="presentation" placeholder="Caja x 20 tabletas" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm">
                        @error('presentation') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Unidades por presentación *</label>
                        <input
                            type="number" min="1" step="1"
                            wire:model="units_per_package"
                            x-on:input="unidades = Number($event.target.value) || 1"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"
                        >
                        <p class="text-[11px] text-slate-400 mt-1">Cuántas tabletas o unidades trae la caja. Si se vende entera, deje 1.</p>
                        @error('units_per_package') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Costo de la presentación *</label>
                        <input
                            type="number" step="1" min="0"
                            wire:model="cost_price"
                            x-on:input="costo = Number($event.target.value) || 0"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"
                        >
                        <p class="text-[11px] text-slate-500 mt-1">
                            Costo por unidad:
                            <span class="font-bold text-slate-700" x-text="pesos(costoUnidad)"></span>
                        </p>
                        @error('cost_price') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Precio de venta por unidad *</label>
                        <input
                            type="number" step="1" min="0"
                            wire:model="selling_price"
                            x-ref="precio"
                            class="w-full px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"
                        >
                        <p class="text-[11px] text-slate-500 mt-1">
                            Sugerido: <span class="font-bold text-slate-700" x-text="pesos(sugerido)"></span>
                            <button type="button" x-on:click="usarSugerido()" class="ml-1 text-blue-600 hover:text-blue-800 font-semibold">usar</button>
                        </p>
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

                    {{-- Lotes: el stock del producto es la suma de estos. Se
                         cargan todos los que llegaron en el pedido, cada uno con
                         su vencimiento y, si el empaque trae otro, su código. --}}
                    <div class="md:col-span-2 pt-4 border-t border-slate-100">
                        <div class="flex items-baseline justify-between mb-2">
                            <h4 class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Lotes</h4>
                            <span class="text-[11px] text-slate-400">El stock sale de la suma de los lotes</span>
                        </div>

                        @include('livewire.partials.batch-rows')
                    </div>
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3 justify-end rounded-b-2xl">
                    <button type="button" x-on:click="cerrar()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-white transition">Cancelar</button>
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
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 overflow-y-auto">
            {{-- Igual que el de producto: el fondo no cierra nada. --}}
            <div class="absolute inset-0 bg-slate-900/50"></div>

            <form
                wire:submit="saveBatch"
                class="relative bg-white w-full max-w-3xl rounded-2xl shadow-2xl my-8"
                x-data="{
                    tocado: false,
                    cerrar() {
                        if (this.tocado && ! confirm('Se perderá lo que escribió en este lote. ¿Salir de todas formas?')) {
                            return
                        }

                        $wire.set('showBatchModal', false)
                    },
                    siguienteCampo(actual) {
                        const campos = [...$el.querySelectorAll('input, textarea, select')]
                            .filter(campo => !campo.disabled && campo.type !== 'hidden')
                        const siguiente = campos[campos.indexOf(actual) + 1]

                        if (siguiente) {
                            siguiente.focus()
                            siguiente.select?.()
                        }
                    },
                }"
                x-on:keydown.enter="if (['INPUT', 'SELECT'].includes($event.target.tagName)) { $event.preventDefault(); siguienteCampo($event.target) }"
                x-on:input="tocado = true"
            >
                @php $productoDelLote = $batchProductId ? \App\Models\Product::find($batchProductId) : null; @endphp

                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <div>
                        <h3 class="font-bold text-slate-800">{{ $batchId ? 'Editar lote' : 'Agregar lotes' }}</h3>
                        @if ($productoDelLote)
                            <p class="text-xs text-slate-400">
                                {{ $productoDelLote->name }} &middot; código del producto {{ $productoDelLote->barcode }}
                            </p>
                        @endif
                    </div>
                    <button type="button" x-on:click="cerrar()" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
                </div>

                <div class="p-6">
                    <p class="text-xs text-slate-500 mb-3">
                        Cargue de una vez todos los lotes que llegaron. Cada uno lleva su vencimiento;
                        el código de barras sólo se llena si el empaque trae uno distinto al del producto.
                    </p>

                    @include('livewire.partials.batch-rows')

                    @error('batchProductId') <p class="text-xs text-red-600 mt-2">{{ $message }}</p> @enderror
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3 justify-end rounded-b-2xl">
                    <button type="button" x-on:click="cerrar()" class="px-5 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-white transition">Cancelar</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition">
                        <span wire:loading.remove wire:target="saveBatch">Guardar lotes</span>
                        <span wire:loading wire:target="saveBatch">Guardando...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Confirmación de eliminación: borrar es lo único irreversible, así que
         pide la clave del dueño. --}}
    @if ($confirmingProductId || $confirmingBatchId)
        @php
            $loteABorrar = $confirmingBatchId ? \App\Models\Batch::find($confirmingBatchId) : null;
            $productoABorrar = $confirmingProductId ? \App\Models\Product::find($confirmingProductId) : null;
        @endphp

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="cancelDelete"></div>

            <form
                wire:submit="{{ $confirmingBatchId ? 'deleteBatch' : 'deleteProduct' }}"
                class="relative bg-white w-full max-w-sm rounded-2xl shadow-2xl p-6 text-center"
            >
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-red-50 flex items-center justify-center text-2xl">&#9888;</div>

                @if ($confirmingBatchId)
                    <h3 class="font-bold text-slate-800 mb-2">¿Eliminar el lote {{ $loteABorrar?->batch_number }}?</h3>
                    <p class="text-sm text-slate-500 mb-5">
                        Se borran las {{ $loteABorrar?->stock }} unidades de este lote. Las ventas ya registradas no se modifican.
                    </p>
                @else
                    <h3 class="font-bold text-slate-800 mb-2">¿Eliminar {{ $productoABorrar?->name }}?</h3>
                    <p class="text-sm text-slate-500 mb-5">
                        Se borrará el producto junto con todos sus lotes. Las ventas ya registradas no se modifican: siguen mostrando el nombre con el que se vendió.
                    </p>
                @endif

                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1 text-left">Clave para eliminar</label>
                <input
                    type="password"
                    wire:model="deletePassword"
                    autofocus
                    class="w-full px-3 py-2.5 mb-1 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-red-500 outline-none text-sm text-center tracking-widest"
                >
                @error('deletePassword') <p class="text-xs text-red-600 mb-2 text-left">{{ $message }}</p> @enderror

                <div class="flex gap-3 mt-4">
                    <button type="button" wire:click="cancelDelete" class="flex-1 py-2.5 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="flex-1 py-2.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold transition">Eliminar</button>
                </div>

                <p class="text-[11px] text-slate-400 mt-3">La clave se cambia desde Reportes.</p>
            </form>
        </div>
    @endif
</div>
