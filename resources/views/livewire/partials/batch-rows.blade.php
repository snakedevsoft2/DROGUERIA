{{--
    Renglones de lotes.

    Un pedido llega con varios lotes a la vez: distinto número, distinta fecha
    de vencimiento y a veces distinto código de barras aunque sea el mismo
    medicamento. Aquí se cargan todos de una, y cada renglón se quita con la X.

    Lo usan los dos formularios —el del producto y el de "+ Agregar lote"—, así
    que no asume nada del que lo incluye.
--}}
<div class="space-y-2">
    {{-- Encabezado: en pantalla angosta cada campo lleva su propia etiqueta. --}}
    <div class="hidden md:grid grid-cols-12 gap-2 px-1 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
        <div class="col-span-3">Número de lote</div>
        <div class="col-span-3">Código de barras</div>
        <div class="col-span-3">Vence *</div>
        <div class="col-span-2">Unidades *</div>
        <div class="col-span-1"></div>
    </div>

    @forelse ($batchRows as $i => $row)
        <div wire:key="{{ $row['uid'] }}" class="grid grid-cols-12 gap-2 items-start bg-slate-50 md:bg-transparent rounded-xl p-2 md:p-0">
            <div class="col-span-12 md:col-span-3">
                <label class="md:hidden block text-[10px] font-semibold text-slate-400 uppercase mb-1">Número de lote</label>
                <input
                    type="text"
                    wire:model="batchRows.{{ $i }}.batch_number"
                    placeholder="Lote {{ $i + 1 }}"
                    class="w-full px-3 py-2 bg-white md:bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"
                >
                @error("batchRows.{$i}.batch_number") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="col-span-12 md:col-span-3">
                <label class="md:hidden block text-[10px] font-semibold text-slate-400 uppercase mb-1">Código de barras</label>
                <input
                    type="text"
                    wire:model="batchRows.{{ $i }}.barcode"
                    placeholder="igual al del producto"
                    class="w-full px-3 py-2 bg-white md:bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"
                >
                @error("batchRows.{$i}.barcode") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="col-span-7 md:col-span-3">
                <label class="md:hidden block text-[10px] font-semibold text-slate-400 uppercase mb-1">Vence *</label>
                <input
                    type="date"
                    wire:model="batchRows.{{ $i }}.expiration_date"
                    min="{{ now()->addDay()->toDateString() }}"
                    class="w-full px-3 py-2 bg-white md:bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"
                >
                @error("batchRows.{$i}.expiration_date") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="col-span-4 md:col-span-2">
                <label class="md:hidden block text-[10px] font-semibold text-slate-400 uppercase mb-1">Unidades *</label>
                <input
                    type="number" min="0" step="1"
                    wire:model="batchRows.{{ $i }}.stock"
                    class="w-full px-3 py-2 bg-white md:bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 outline-none text-sm"
                >
                @error("batchRows.{$i}.stock") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="col-span-1 flex md:justify-center pt-1 md:pt-2">
                <button
                    type="button"
                    wire:click="removeBatchRow({{ $i }})"
                    title="Quitar este lote"
                    class="w-8 h-8 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 text-xl leading-none transition"
                >&times;</button>
            </div>

            {{-- Sólo en lotes ya guardados: desactivar aparta la mercancía de la
                 venta sin borrarla. Uno nuevo entra activo. --}}
            @if ($row['id'])
                <div class="col-span-12 md:col-span-11 md:col-start-1 -mt-1">
                    <label class="inline-flex items-center gap-2 text-xs text-slate-500">
                        <input type="checkbox" wire:model="batchRows.{{ $i }}.is_active" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        Habilitado para la venta
                    </label>
                </div>
            @endif
        </div>
    @empty
        <p class="text-sm text-slate-400 py-2">Sin lotes. Agregue uno para que el producto tenga stock.</p>
    @endforelse

    <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <button
            type="button"
            wire:click="addBatchRow"
            class="text-sm text-blue-600 hover:text-blue-800 font-semibold"
        >+ Agregar otro lote</button>

        <span class="text-[11px] text-slate-400">
            Si deja el número en blanco se numera solo: Lote 1, Lote 2, Lote 3…
        </span>
    </div>

    @error('batchRows') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

    {{-- Lo que se quitó y todavía no se ha borrado: se puede deshacer, y si se
         confirma pide la clave, porque borrar un lote es irreversible. --}}
    @if ($this->removedBatches->isNotEmpty())
        <div class="mt-3 p-3 rounded-xl bg-red-50 border border-red-100 space-y-2">
            <p class="text-xs font-semibold text-red-800 uppercase tracking-wider">Se eliminarán al guardar</p>

            @foreach ($this->removedBatches as $quitado)
                <div wire:key="quitado-{{ $quitado->id }}" class="flex items-center gap-2 text-xs text-red-700">
                    <span class="line-through">
                        {{ $quitado->batch_number }} &middot;
                        {{ \Illuminate\Support\Carbon::parse($quitado->expiration_date)->format('d/m/Y') }} &middot;
                        {{ $quitado->stock }} u.
                    </span>
                    <button type="button" wire:click="restoreRemovedBatch({{ $quitado->id }})" class="font-semibold text-slate-600 hover:text-slate-900">deshacer</button>
                </div>
            @endforeach

            <div>
                <label class="block text-[10px] font-semibold text-red-800 uppercase tracking-wider mb-1">Clave para eliminar</label>
                <input
                    type="password"
                    wire:model="batchDeletePassword"
                    class="w-full md:w-56 px-3 py-2 bg-white border border-red-200 rounded-xl focus:ring-2 focus:ring-red-500 outline-none text-sm tracking-widest"
                >
                @error('batchDeletePassword') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                <p class="text-[11px] text-red-700/70 mt-1">Las ventas ya registradas no se modifican.</p>
            </div>
        </div>
    @endif
</div>
