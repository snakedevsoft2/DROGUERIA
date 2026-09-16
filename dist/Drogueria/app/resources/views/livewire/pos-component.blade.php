<div class="p-6">
    <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        {{-- Columna izquierda: búsqueda y carrito --}}
        <div class="lg:col-span-8 flex flex-col gap-6">

            {{-- Buscador / lector de código de barras --}}
            <div
                class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200 relative"
                x-data="{ open: false }"
                x-on:click.outside="open = false"
            >
                <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">
                    Buscador de medicamentos / lector de código
                </label>

                <div class="relative">
                    <input
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        wire:keydown.enter="searchByBarcode"
                        x-on:focus="open = true"
                        x-on:input="open = true"
                        placeholder="Escriba nombre, presentación o escanee el código de barras..."
                        class="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-slate-800 font-medium"
                        autofocus
                        autocomplete="off"
                    >
                    <svg class="w-6 h-6 absolute left-3 top-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>

                    <div wire:loading wire:target="search" class="absolute right-4 top-4 text-xs text-slate-400">
                        Buscando...
                    </div>
                </div>

                @if ($searchResults->isNotEmpty())
                    <div x-show="open" x-cloak class="absolute z-50 left-4 right-4 mt-2 bg-white rounded-xl shadow-2xl border border-slate-200 overflow-hidden">
                        <ul class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                            @foreach ($searchResults as $product)
                                @php $stock = (int) ($product->total_stock ?? 0); @endphp
                                <li>
                                    <button
                                        type="button"
                                        wire:click="addToCart({{ $product->id }})"
                                        x-on:click="open = false"
                                        @disabled($stock <= 0)
                                        class="w-full px-4 py-3 text-left hover:bg-blue-50 disabled:opacity-50 disabled:hover:bg-white flex justify-between items-center gap-4 transition-colors"
                                    >
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-800 truncate">
                                                {{ $product->name }}
                                                @if ($product->requires_prescription)
                                                    <span class="ml-1 text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 align-middle">Rx</span>
                                                @endif
                                            </p>
                                            <p class="text-xs text-slate-500 truncate">
                                                Cód: {{ $product->barcode }} @if ($product->presentation) &middot; {{ $product->presentation }} @endif
                                            </p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="font-bold text-blue-600">${{ number_format($product->selling_price, 0, ',', '.') }} <span class="text-[11px] font-semibold text-slate-400">/u</span></p>
                                            <span class="text-xs px-2 py-0.5 rounded-full {{ $stock > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">
                                                Stock: {{ $stock }}
                                            </span>
                                        </div>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Carrito --}}
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 bg-slate-50 border-b border-slate-200 flex justify-between items-center">
                    <h2 class="font-bold text-slate-700">
                        Productos en la venta
                        <span class="ml-1 text-xs font-semibold text-slate-400">({{ count($cart) }})</span>
                    </h2>
                    <button
                        type="button"
                        wire:click="clearCart"
                        @disabled(empty($cart))
                        class="text-xs font-semibold text-red-500 hover:text-red-700 disabled:text-slate-300 transition"
                    >
                        Vaciar carrito
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-500 text-xs uppercase tracking-wider">
                                <th class="p-4">Producto</th>
                                <th class="p-4 text-center">Precio /u</th>
                                <th class="p-4 text-center">Cantidad</th>
                                <th class="p-4 text-right">Subtotal</th>
                                <th class="p-4 text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 text-slate-700 text-sm">
                            @forelse ($cart as $item)
                                <tr wire:key="cart-{{ $item['id'] }}" class="hover:bg-slate-50 transition">
                                    <td class="p-4">
                                        <p class="font-semibold text-slate-800">{{ $item['name'] }}</p>
                                        <p class="text-xs text-slate-400">
                                            {{ $item['barcode'] }} @if ($item['presentation']) &middot; {{ $item['presentation'] }} @endif
                                        </p>
                                    </td>
                                    <td class="p-4 text-center">${{ number_format($item['unit_price'], 0, ',', '.') }}</td>
                                    <td class="p-4">
                                        <div class="flex items-center justify-center gap-2">
                                            <button
                                                type="button"
                                                wire:click="updateQuantity({{ $item['id'] }}, {{ $item['quantity'] - 1 }})"
                                                class="w-7 h-7 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg font-bold flex items-center justify-center"
                                            >&minus;</button>

                                            <input
                                                type="number"
                                                value="{{ $item['quantity'] }}"
                                                wire:change="updateQuantity({{ $item['id'] }}, $event.target.value)"
                                                min="1"
                                                max="{{ $item['max_stock'] }}"
                                                class="w-16 text-center bg-slate-50 border border-slate-300 rounded-lg py-1 font-semibold focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            >

                                            <button
                                                type="button"
                                                wire:click="updateQuantity({{ $item['id'] }}, {{ $item['quantity'] + 1 }})"
                                                @disabled($item['quantity'] >= $item['max_stock'])
                                                class="w-7 h-7 bg-slate-200 hover:bg-slate-300 disabled:bg-slate-100 disabled:text-slate-300 text-slate-700 rounded-lg font-bold flex items-center justify-center"
                                            >+</button>
                                        </div>
                                        <p class="text-[11px] text-slate-400 text-center mt-1">Disp: {{ $item['max_stock'] }}</p>
                                    </td>
                                    <td class="p-4 text-right font-bold text-slate-800">${{ number_format($item['subtotal'], 0, ',', '.') }}</td>
                                    <td class="p-4 text-center">
                                        <button type="button" wire:click="removeFromCart({{ $item['id'] }})" class="text-slate-400 hover:text-red-500 transition">
                                            <svg class="w-5 h-5 mx-auto" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-10 text-center text-slate-400">
                                        No hay medicamentos agregados al carrito.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Columna derecha: totales --}}
        <div class="lg:col-span-4">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 sticky top-24">
                <h2 class="text-lg font-bold text-slate-800 mb-4 border-b border-slate-100 pb-2">Resumen de cuenta</h2>

                <div class="space-y-3 text-slate-600 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span class="font-semibold text-slate-800">${{ number_format($this->subtotal, 0, ',', '.') }}</span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span>Descuento ($)</span>
                        <input
                            type="number"
                            wire:model.live.debounce.400ms="discount"
                            min="0"
                            step="0.01"
                            class="w-28 text-right bg-slate-50 border border-slate-300 rounded-lg py-1 px-2 text-slate-800 font-semibold focus:ring-1 focus:ring-blue-500 outline-none"
                        >
                    </div>

                    <hr class="border-slate-100 my-2">

                    <div class="flex justify-between text-lg font-extrabold text-slate-900">
                        <span>TOTAL</span>
                        <span class="text-blue-600">${{ number_format($this->total, 0, ',', '.') }}</span>
                    </div>
                </div>

                <button
                    type="button"
                    wire:click="openCheckout"
                    @disabled(empty($cart))
                    class="w-full mt-6 py-4 bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white font-bold rounded-xl shadow-lg shadow-blue-200 disabled:shadow-none transition-all flex justify-center items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                    Procesar y facturar
                </button>

                @if ($lastSaleId)
                    <div class="mt-3 flex items-center justify-center gap-3 text-xs">
                        <button type="button" wire:click="previewLastReceipt" class="font-semibold text-slate-500 hover:text-blue-600 transition">
                            Ver último comprobante
                        </button>
                        <span class="text-slate-300">|</span>
                        <a
                            href="{{ route('receipt', $lastSaleId) }}"
                            target="_blank"
                            class="font-semibold text-slate-500 hover:text-blue-600 transition"
                        >Abrir en pestaña</a>
                        <span class="text-slate-300">|</span>
                        <a
                            href="{{ route('receipt', ['sale' => $lastSaleId, 'formato' => 'carta']) }}"
                            target="_blank"
                            class="font-semibold text-slate-500 hover:text-blue-600 transition"
                        >Factura en hoja</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Modal de cobro --}}
    @if ($showCheckout)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-900/50" wire:click="closeCheckout"></div>

            <div class="relative bg-white w-full max-w-md rounded-2xl shadow-2xl overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                    <h3 class="font-bold text-slate-800">Cobro de la venta</h3>
                    <button type="button" wire:click="closeCheckout" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
                </div>

                <div class="p-6 space-y-5">
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-center">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-blue-600">Total a pagar</span>
                        <span class="text-3xl font-black text-blue-700">${{ number_format($this->total, 0, ',', '.') }}</span>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Método de pago</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transfer.'] as $value => $label)
                                <button
                                    type="button"
                                    wire:click="$set('paymentMethod', '{{ $value }}')"
                                    @class([
                                        'py-2.5 rounded-xl text-sm font-semibold border transition',
                                        'bg-blue-600 border-blue-600 text-white' => $paymentMethod === $value,
                                        'bg-white border-slate-300 text-slate-600 hover:border-blue-400' => $paymentMethod !== $value,
                                    ])
                                >{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>

                    @if ($paymentMethod === 'cash')
                        <div>
                            <label class="block text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Monto recibido</label>
                            <input
                                type="number"
                                wire:model.live.debounce.300ms="paidAmount"
                                min="0"
                                step="0.01"
                                class="w-full px-4 py-3 text-lg font-bold bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-slate-800"
                            >
                        </div>

                        @php $covered = (float) $paidAmount >= $this->total; @endphp
                        <div class="p-4 rounded-xl {{ $covered ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200' }}">
                            <span class="block text-xs font-semibold uppercase tracking-wider {{ $covered ? 'text-emerald-600' : 'text-amber-600' }}">
                                {{ $covered ? 'Cambio a devolver' : 'Falta por cubrir' }}
                            </span>
                            <span class="text-2xl font-black {{ $covered ? 'text-emerald-700' : 'text-amber-700' }}">
                                ${{ number_format($covered ? $this->change : $this->total - (float) $paidAmount, 0, ',', '.') }}
                            </span>
                        </div>
                    @else
                        <p class="text-sm text-slate-500 bg-slate-50 border border-slate-200 rounded-xl p-4">
                            El pago se registrará por el valor exacto de la venta, sin cambio.
                        </p>
                    @endif
                </div>

                <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3">
                    <button type="button" wire:click="closeCheckout" class="flex-1 py-3 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-white transition">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        wire:click="completeSale"
                        wire:loading.attr="disabled"
                        wire:target="completeSale"
                        @disabled($paymentMethod === 'cash' && (float) $paidAmount < $this->total)
                        class="flex-1 py-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:bg-slate-300 text-white font-bold transition"
                    >
                        <span wire:loading.remove wire:target="completeSale">Confirmar venta</span>
                        <span wire:loading wire:target="completeSale">Procesando...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{--
        Vista previa del comprobante. El iframe es visible a propósito: un
        iframe oculto (display:none o 0x0) no se renderiza y el navegador
        manda una hoja en blanco a la impresora.
    --}}
    @if ($showReceiptPreview && $lastSaleId)
        <div
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            x-data="{
                printReceipt() {
                    // Imprime el documento del iframe, no la página del POS.
                    const frame = this.$refs.frame;
                    if (! frame?.contentWindow) return;

                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                },
                fit(frame) {
                    try {
                        frame.style.height = (frame.contentWindow.document.body.scrollHeight + 16) + 'px';
                    } catch (e) { /* deja la altura mínima */ }
                },
            }"
            x-on:keydown.escape.window="$wire.closeReceiptPreview()"
        >
            <div class="absolute inset-0 bg-slate-900/50" wire:click="closeReceiptPreview"></div>

            <div class="relative bg-white w-full max-w-sm rounded-2xl shadow-2xl flex flex-col max-h-[90vh]">
                <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center shrink-0">
                    <div>
                        <h3 class="font-bold text-slate-800">Comprobante de venta</h3>
                        <p class="text-xs text-slate-400">Revise antes de imprimir</p>
                    </div>
                    <button type="button" wire:click="closeReceiptPreview" class="text-slate-400 hover:text-slate-700 text-2xl leading-none">&times;</button>
                </div>

                {{-- Fondo gris y sombra: se lee como una tirilla sobre el mostrador. --}}
                <div class="flex-1 overflow-y-auto bg-slate-200 p-4 flex justify-center">
                    <iframe
                        x-ref="frame"
                        src="{{ route('receipt', ['sale' => $lastSaleId, 'embed' => 1]) }}"
                        title="Vista previa del comprobante"
                        class="bg-white shadow-md border-0"
                        style="width: 80mm; min-height: 60vh;"
                        x-on:load="fit($el)"
                    ></iframe>
                </div>

                <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 shrink-0 rounded-b-2xl space-y-2">
                    <div class="flex gap-3">
                        <button type="button" wire:click="closeReceiptPreview" class="flex-1 py-3 rounded-xl border border-slate-300 text-slate-600 font-semibold hover:bg-white transition">
                            Cerrar
                        </button>

                        {{-- Con tiquetera configurada el tiquete ya salió solo;
                             este botón es para reimprimirlo. --}}
                        @if ($this->printerReady)
                            <button
                                type="button"
                                wire:click="printLastReceipt"
                                wire:loading.attr="disabled"
                                wire:target="printLastReceipt"
                                class="flex-1 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:bg-slate-300 text-white font-bold transition flex items-center justify-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                <span wire:loading.remove wire:target="printLastReceipt">Reimprimir tiquete</span>
                                <span wire:loading wire:target="printLastReceipt">Enviando...</span>
                            </button>
                        @else
                            <button
                                type="button"
                                x-on:click="printReceipt()"
                                class="flex-1 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold transition flex items-center justify-center gap-2"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Imprimir
                            </button>
                        @endif
                    </div>

                    {{-- Salida de emergencia si la tiquetera se queda sin papel
                         o se desconecta a mitad de la jornada: abre el diálogo
                         de impresión de Windows para usar cualquier otra. --}}
                    @if ($this->printerReady)
                        <button
                            type="button"
                            x-on:click="printReceipt()"
                            class="w-full py-2 text-xs text-slate-500 hover:text-slate-700 font-semibold transition"
                        >
                            Imprimir con otra impresora
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
