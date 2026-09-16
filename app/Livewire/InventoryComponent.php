<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Product;
use App\Models\SaleDetail;
use App\Support\AdminPassword;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('components.layouts.app')]
#[Title('Inventario')]
class InventoryComponent extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $filter = 'all'; // all | low | expiring | inactive

    // --- Product form ---
    public bool $showProductModal = false;

    public ?int $productId = null;

    public string $barcode = '';

    public string $name = '';

    public string $presentation = '';

    /** Unidades que trae la presentación: una caja x 10 son 10. */
    public $units_per_package = 1;

    public string $description = '';

    public $cost_price = 0;

    public $selling_price = 0;

    public $min_stock = 5;

    public bool $requires_prescription = false;

    public bool $is_active = true;

    /** True cuando el código tecleado ya existía y el formulario se pasó a esa ficha. */
    public bool $fusionadoConExistente = false;

    /** Producto ya registrado con este código, cuando todavía no se decidió fusionar. */
    public ?int $duplicadoProductId = null;

    public string $duplicadoNombre = '';

    // --- Batch form ---
    public bool $showBatchModal = false;

    public ?int $batchProductId = null;

    /** Lote que se está editando, cuando se entró por "editar" en la lista. */
    public ?int $batchId = null;

    /**
     * Filas del formulario de lotes.
     *
     * Un pedido llega con varios lotes de una vez —distinto número, distinta
     * fecha y a veces distinto código de barras— y se cargan todos juntos en
     * lugar de abrir el formulario una vez por lote. Cada fila es un lote:
     * ['uid', 'id', 'batch_number', 'barcode', 'expiration_date', 'stock', 'is_active'].
     * 'id' viene lleno cuando la fila es un lote que ya está guardado.
     */
    public array $batchRows = [];

    /** Lotes ya guardados que el usuario quitó del formulario. */
    public array $removedBatchIds = [];

    /** Clave del dueño: sólo se pide si hay lotes marcados para quitar. */
    public string $batchDeletePassword = '';

    // --- Deletion ---
    public ?int $confirmingProductId = null;

    public function render()
    {
        return view('livewire.inventory-component', [
            'products' => $this->products,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /** Constraint shared by every "sellable stock" query. */
    protected function availableBatches($query)
    {
        return $query->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereDate('expiration_date', '>', now());
    }

    /**
     * Meses de vigencia a partir de los cuales el lote entra en alerta.
     * Configurable en config/drogueria.php; por defecto, seis.
     */
    public function expiryAlertMonths(): int
    {
        return max(1, (int) config('drogueria.inventory.expiry_alert_months', 6));
    }

    /** Fecha límite de la alerta: hoy más la ventana configurada. */
    protected function expiryLimit()
    {
        return now()->addMonths($this->expiryAlertMonths());
    }

    /**
     * Los mismos meses expresados en días, que es como la lista pinta cada
     * lote según lo que le falte para vencer.
     */
    #[Computed]
    public function expiryAlertDays(): int
    {
        return (int) now()->startOfDay()->diffInDays(now()->startOfDay()->addMonths($this->expiryAlertMonths()));
    }

    /** Lotes vigentes a los que ya les queda menos que la ventana de alerta. */
    protected function expiringBatches($query)
    {
        return $query->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereBetween('expiration_date', [now(), $this->expiryLimit()]);
    }

    /**
     * Existencias que sirven para reponer: las que aún tienen por delante más
     * que la ventana de alerta. Lo que vence antes se sigue vendiendo, pero no
     * cuenta como respaldo a la hora de decidir si hay que pedir más.
     */
    protected function healthyBatches($query)
    {
        return $query->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereDate('expiration_date', '>', $this->expiryLimit());
    }

    /**
     * "Stock at or below the minimum" compares an aggregate against a plain
     * column, which MySQL rejects in a HAVING clause under ONLY_FULL_GROUP_BY.
     * A correlated sub-select in WHERE expresses the same thing portably.
     */
    protected function whereLowStock($query)
    {
        $sub = Batch::query()
            ->selectRaw('COALESCE(SUM(stock), 0)')
            ->whereColumn('batches.product_id', 'products.id');

        $this->healthyBatches($sub);

        return $query->whereRaw(
            '('.$sub->toSql().') <= products.min_stock',
            $sub->getBindings()
        );
    }

    #[Computed(persist: false)]
    public function products()
    {
        return Product::query()
            ->withSum(['batches as total_stock' => fn ($q) => $this->availableBatches($q)], 'stock')
            // Existencias con vigencia suficiente: son las que deciden si el
            // producto se marca en rojo, no las que simplemente están en bodega.
            ->withSum(['batches as healthy_stock' => fn ($q) => $this->healthyBatches($q)], 'stock')
            ->with(['batches' => fn ($q) => $q->orderBy('expiration_date')])
            ->when($this->search !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%")
                        ->orWhere('presentation', 'like', "%{$term}%")
                        // Un lote puede traer su propio código: buscarlo tiene
                        // que llevar al producto igual que el código de la ficha.
                        ->orWhereHas('batches', fn ($b) => $b->where('barcode', 'like', "%{$term}%"))
                        ->orWhereHas('batches', fn ($b) => $b->where('batch_number', 'like', "%{$term}%"));
                });
            })
            ->when($this->filter === 'low', fn ($q) => $this->whereLowStock($q))
            ->when($this->filter === 'expiring', fn ($q) => $q->whereHas('batches', fn ($b) => $this->expiringBatches($b)))
            ->when($this->filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->paginate(10);
    }

    /** Products at or below their configured minimum — drives the alert badge. */
    #[Computed]
    public function lowStockCount(): int
    {
        return $this->whereLowStock(Product::query()->where('is_active', true))->count();
    }

    #[Computed]
    public function expiringCount(): int
    {
        return $this->expiringBatches(Batch::query())->count();
    }

    #[Computed]
    public function expiredCount(): int
    {
        return Batch::query()
            ->where('stock', '>', 0)
            ->whereDate('expiration_date', '<=', now())
            ->count();
    }

    // ------------------------------------------------------------------
    // Product CRUD
    // ------------------------------------------------------------------

    public function createProduct(): void
    {
        $this->resetProductForm();
        // Un renglón de lote listo: casi siempre el producto se crea porque
        // acaba de llegar mercancía, y esa mercancía tiene lote y vencimiento.
        $this->batchRows = [$this->emptyBatchRow()];
        $this->showProductModal = true;
    }

    public function editProduct(int $id): void
    {
        $product = Product::with(['batches' => fn ($q) => $q->orderBy('expiration_date')])->findOrFail($id);

        $this->productId = $product->id;
        $this->barcode = (string) $product->barcode;
        $this->name = (string) $product->name;
        $this->presentation = (string) $product->presentation;
        $this->units_per_package = $product->units_per_package;
        $this->description = (string) $product->description;
        $this->cost_price = $product->cost_price;
        $this->selling_price = $product->selling_price;
        $this->min_stock = $product->min_stock;
        $this->requires_prescription = (bool) $product->requires_prescription;
        $this->is_active = (bool) $product->is_active;
        $this->fusionadoConExistente = false;
        $this->duplicadoProductId = null;
        $this->duplicadoNombre = '';

        // Los lotes se editan aquí mismo: se cambian, se agregan y se quitan
        // sin salir de la ficha del producto.
        $this->resetBatchForm();
        $this->batchProductId = $product->id;
        $this->batchRows = $product->batches->map(fn ($b) => $this->batchRow($b))->all();

        $this->resetValidation();
        $this->showProductModal = true;
    }

    /**
     * El código de barras que se acaba de escribir ya está registrado.
     *
     * Puede ser el mismo medicamento que vuelve a llegar —y entonces conviene
     * agregarlo como lote nuevo de esa ficha— o puede ser un producto distinto
     * que quedó con el mismo código por error de digitación. Ya no se decide
     * solo: se avisa y el usuario elige con el botón "Es el mismo" si quiere
     * fusionar; si no hace nada y guarda, queda como producto aparte,
     * numerado para distinguirlo.
     */
    public function updatedBarcode(): void
    {
        $this->duplicadoProductId = null;
        $this->duplicadoNombre = '';

        // Editando una ficha, no hay nada que ofrecer fusionar.
        if ($this->productId) {
            return;
        }

        $codigo = trim($this->barcode);

        if ($codigo === '') {
            return;
        }

        $product = Product::where('barcode', $codigo)->first();

        if ($product) {
            $this->duplicadoProductId = $product->id;
            $this->duplicadoNombre = $product->name;
        }
    }

    /**
     * Pasa el formulario a la ficha que ya existe sin perder nada de lo escrito.
     *
     * Los datos que el usuario venía tecleando mandan —si el pedido llegó más
     * caro, ese es el costo nuevo— y de la ficha vieja sólo se traen los campos
     * que quedaron en blanco. Los lotes que ya tenía quedan a la vista para no
     * repetir números, y debajo van los que se están registrando.
     */
    public function usarProductoExistente(): void
    {
        $product = Product::with(['batches' => fn ($q) => $q->orderBy('expiration_date')])
            ->where('barcode', trim($this->barcode))
            ->first();

        if (! $product) {
            return;
        }

        $escritas = array_values(array_filter($this->batchRows, fn ($row) => ! $this->filaVacia($row)));

        $this->productId = $product->id;
        $this->fusionadoConExistente = true;
        $this->duplicadoProductId = null;
        $this->duplicadoNombre = '';

        // Sólo se completa lo que está vacío: lo tecleado no se pisa.
        $this->name = $this->name !== '' ? $this->name : (string) $product->name;
        $this->presentation = $this->presentation !== '' ? $this->presentation : (string) $product->presentation;
        $this->description = $this->description !== '' ? $this->description : (string) $product->description;
        $this->units_per_package = (int) $this->units_per_package > 1 ? $this->units_per_package : $product->units_per_package;
        $this->cost_price = (float) $this->cost_price > 0 ? $this->cost_price : $product->cost_price;
        $this->selling_price = (float) $this->selling_price > 0 ? $this->selling_price : $product->selling_price;
        $this->min_stock = (int) $this->min_stock > 0 ? $this->min_stock : $product->min_stock;

        $this->batchProductId = $product->id;
        $this->removedBatchIds = [];
        $this->batchRows = $product->batches->map(fn ($b) => $this->batchRow($b))->all();

        foreach ($escritas as $row) {
            $row['id'] = null;
            $row['uid'] = uniqid('lote-', true);
            $this->batchRows[] = $row;
        }

        if (empty($escritas)) {
            $this->batchRows[] = $this->emptyBatchRow();
        }

        $this->resetValidation();
    }

    /**
     * Si el nombre coincide con el de otro producto ya registrado, le agrega
     * un consecutivo entre paréntesis para poder distinguirlos: "Acetaminofén"
     * y "Acetaminofén (2)" no se confunden en la lista ni en el mostrador.
     * Si no hay coincidencia, el nombre vuelve intacto.
     */
    protected function nombreEnumerado(string $name, ?int $ignoreId = null): string
    {
        $nombre = trim($name);
        $base = preg_replace('/\s*\(\d+\)$/', '', $nombre);

        $usados = Product::query()
            ->where(fn ($q) => $q->where('name', $base)->orWhere('name', 'like', $base.' (%)'))
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->pluck('name');

        if ($usados->isEmpty()) {
            return $nombre;
        }

        $consecutivo = 1;

        foreach ($usados as $usado) {
            if (preg_match('/\((\d+)\)$/', trim($usado), $m)) {
                $consecutivo = max($consecutivo, (int) $m[1]);
            }
        }

        return $base.' ('.($consecutivo + 1).')';
    }

    /** Costo de cada unidad según lo que se está escribiendo en el formulario. */
    #[Computed]
    public function unitCost(): float
    {
        $unidades = max(1, (int) $this->units_per_package);

        return round((float) $this->cost_price / $unidades, 2);
    }

    /**
     * Precio de venta sugerido para la unidad: el costo unitario más el margen
     * de la casa, redondeado a la centena de arriba porque en el mostrador no
     * se manejan monedas menores.
     */
    #[Computed]
    public function suggestedPrice(): float
    {
        $margen = (float) config('drogueria.inventory.default_margin_percent', 30);

        return (float) (ceil($this->unitCost * (1 + $margen / 100) / 100) * 100);
    }

    /** Copia el precio sugerido al formulario; el usuario puede cambiarlo. */
    public function applySuggestedPrice(): void
    {
        $this->selling_price = $this->suggestedPrice;
    }

    protected function productRules(): array
    {
        return [
            // Ya no es único: dos productos distintos pueden compartir el
            // mismo código, y el nombre numerado los distingue.
            'barcode' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'presentation' => ['nullable', 'string', 'max:255'],
            'units_per_package' => ['required', 'integer', 'min:1', 'max:10000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => [
                'required', 'numeric', 'min:0',
                function ($attribute, $value, $fail) {
                    if ((float) $value < $this->unitCost) {
                        $fail('El precio por unidad no puede ser menor al costo por unidad ($'
                            .number_format($this->unitCost, 0, ',', '.').').');
                    }
                },
            ],
            'min_stock' => ['required', 'integer', 'min:0'],
            'requires_prescription' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'batchRows.*.batch_number.required' => 'Escriba el número de lote o quite el renglón.',
            'batchRows.*.expiration_date.required' => 'Falta la fecha de vencimiento.',
            'batchRows.*.expiration_date.after' => 'El vencimiento debe ser posterior a hoy.',
            'batchRows.*.stock.required' => 'Escriba cuántas unidades entraron.',
        ];
    }

    public function saveProduct(): void
    {
        // Fusionar con una ficha existente es una decisión del usuario (botón
        // "Es el mismo"), no algo que se dispare solo: si no la tomó, esto se
        // guarda como producto aparte aunque comparta código con otro.
        $this->limpiarFilasVacias();
        $this->numerarFilasSinNumero($this->productId);

        $data = $this->validate(
            $this->productRules() + $this->batchRowRules(),
            [],
            $this->batchRowAttributes()
        );

        // Los lotes no son columnas del producto.
        unset($data['batchRows']);

        // Si el nombre coincide con el de otro producto (mismo código o
        // coincidencia de nombre), se numera para poder distinguirlos en la
        // lista y en el punto de venta.
        $data['name'] = $this->nombreEnumerado($data['name'], $this->productId);

        if (! $this->validateBatchRows($this->productId)) {
            return;
        }

        if (! $this->authorizeBatchRemovals()) {
            return;
        }

        $lotes = 0;
        $nombre = $data['name'];
        $fusionado = $this->fusionadoConExistente;

        DB::transaction(function () use ($data, &$lotes) {
            $product = Product::updateOrCreate(['id' => $this->productId], $data);

            $lotes = count(array_filter($this->batchRows, fn ($row) => filled($row['batch_number'])));

            $this->persistBatchRows($product->id);
            $this->applyBatchRemovals();
        });

        $this->showProductModal = false;
        $this->resetProductForm();
        unset($this->products, $this->lowStockCount, $this->expiringCount, $this->expiredCount);

        $mensaje = match (true) {
            $fusionado => "Se actualizó \"{$nombre}\" con sus {$lotes} ".($lotes === 1 ? 'lote' : 'lotes').'.',
            $lotes > 0 => "Producto guardado con {$lotes} ".($lotes === 1 ? 'lote' : 'lotes').'.',
            default => 'Producto guardado correctamente.',
        };

        $this->dispatch('toast', type: 'success', message: $mensaje);
    }

    /** Activa o desactiva el producto desde la propia lista. */
    public function toggleProduct(int $id): void
    {
        $product = Product::find($id);

        if (! $product) {
            return;
        }

        $product->update(['is_active' => ! $product->is_active]);

        unset($this->products, $this->lowStockCount, $this->expiringCount, $this->expiredCount);

        $this->dispatch('toast', type: 'success', message: $product->is_active
            ? 'Producto activado.'
            : 'Producto desactivado: deja de aparecer en el punto de venta.');
    }

    // --- Borrado con clave ---

    /** Lote en espera de confirmación, si lo que se borra es un lote. */
    public ?int $confirmingBatchId = null;

    public string $deletePassword = '';

    public function confirmDelete(int $id): void
    {
        $this->confirmingBatchId = null;
        $this->confirmingProductId = $id;
        $this->deletePassword = '';
        $this->resetValidation();
    }

    public function confirmDeleteBatch(int $id): void
    {
        $this->confirmingProductId = null;
        $this->confirmingBatchId = $id;
        $this->deletePassword = '';
        $this->resetValidation();
    }

    public function cancelDelete(): void
    {
        $this->confirmingProductId = null;
        $this->confirmingBatchId = null;
        $this->deletePassword = '';
        $this->resetValidation();
    }

    /**
     * Borrar es lo único que no tiene vuelta atrás, así que pide la clave del
     * dueño. Vender y consultar no la necesitan.
     */
    protected function authorizeDelete(): bool
    {
        if (AdminPassword::check($this->deletePassword)) {
            return true;
        }

        $this->addError('deletePassword', 'Clave incorrecta.');
        $this->deletePassword = '';

        return false;
    }

    public function deleteProduct(): void
    {
        if (! $this->authorizeDelete()) {
            return;
        }

        $product = Product::find($this->confirmingProductId);
        $this->cancelDelete();

        if (! $product) {
            return;
        }

        // Se borra de verdad, con sus lotes. Las ventas ya registradas no se
        // tocan: el detalle guarda el nombre y suelta la referencia, así que
        // los comprobantes y los reportes siguen cuadrando.
        try {
            DB::transaction(function () use ($product) {
                $batchIds = $product->batches()->pluck('id');

                SaleDetail::whereIn('batch_id', $batchIds)->update(['batch_id' => null]);

                // El nombre se copia antes de soltar la referencia: de ahí lo
                // leen las facturas viejas cuando el producto ya no está.
                SaleDetail::where('product_id', $product->id)
                    ->whereNull('product_name')
                    ->update(['product_name' => $product->name]);

                SaleDetail::where('product_id', $product->id)->update(['product_id' => null]);

                $product->delete();
            });
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'No se pudo eliminar el producto: '.$e->getMessage());

            return;
        }

        unset($this->products, $this->lowStockCount, $this->expiringCount, $this->expiredCount);

        $this->dispatch('toast', type: 'success', message: 'Producto eliminado.');
    }

    protected function resetProductForm(): void
    {
        $this->reset([
            'productId', 'barcode', 'name', 'presentation', 'units_per_package', 'description',
            'cost_price', 'selling_price', 'min_stock', 'requires_prescription', 'is_active',
            'fusionadoConExistente', 'duplicadoProductId', 'duplicadoNombre',
        ]);
        $this->resetBatchForm();
        $this->resetValidation();
    }

    // ------------------------------------------------------------------
    // Batch CRUD
    // ------------------------------------------------------------------

    public function createBatch(int $productId): void
    {
        $this->resetBatchForm();
        $this->batchProductId = $productId;
        $this->batchRows = [$this->emptyBatchRow()];
        $this->showBatchModal = true;
    }

    public function editBatch(int $id): void
    {
        $batch = Batch::findOrFail($id);

        $this->resetBatchForm();
        $this->batchId = $batch->id;
        $this->batchProductId = $batch->product_id;
        $this->batchRows = [$this->batchRow($batch)];
        $this->showBatchModal = true;
    }

    public function saveBatch(): void
    {
        $this->limpiarFilasVacias();

        if (empty($this->batchRows) && empty($this->removedBatchIds)) {
            $this->addError('batchRows', 'Agregue al menos un lote.');

            return;
        }

        $this->numerarFilasSinNumero($this->batchProductId);

        $this->validate(
            ['batchProductId' => ['required', 'exists:products,id']] + $this->batchRowRules(),
            [],
            $this->batchRowAttributes()
        );

        if (! $this->validateBatchRows($this->batchProductId)) {
            return;
        }

        // La clave se pide antes de escribir nada: si está mal, el formulario
        // queda intacto y no se guardó ni se borró la mitad.
        if (! $this->authorizeBatchRemovals()) {
            return;
        }

        DB::transaction(function () {
            $this->persistBatchRows($this->batchProductId);
            $this->applyBatchRemovals();
        });

        $guardados = count(array_filter($this->batchRows, fn ($row) => filled($row['batch_number'])));

        $this->showBatchModal = false;
        $this->resetBatchForm();
        unset($this->products, $this->lowStockCount, $this->expiringCount, $this->expiredCount);

        $this->dispatch('toast', type: 'success', message: $guardados === 1
            ? 'Lote guardado correctamente.'
            : "Se guardaron {$guardados} lotes.");
    }

    // --- Filas del formulario de lotes ---

    /** Fila en blanco. El uid mantiene el orden al agregar y quitar filas. */
    protected function emptyBatchRow(): array
    {
        return [
            'uid' => uniqid('lote-', true),
            'id' => null,
            'batch_number' => '',
            'barcode' => '',
            'expiration_date' => '',
            'stock' => 0,
            'is_active' => true,
        ];
    }

    /** Un lote ya guardado, visto como fila del formulario. */
    protected function batchRow(Batch $batch): array
    {
        return [
            'uid' => 'lote-'.$batch->id,
            'id' => $batch->id,
            'batch_number' => (string) $batch->batch_number,
            'barcode' => (string) $batch->barcode,
            'expiration_date' => (string) $batch->expiration_date,
            'stock' => (int) $batch->stock,
            'is_active' => (bool) $batch->is_active,
        ];
    }

    public function addBatchRow(): void
    {
        $this->batchRows[] = $this->emptyBatchRow();
    }

    /** Un renglón en el que no se escribió nada: al guardar se ignora. */
    protected function filaVacia(array $row): bool
    {
        return trim((string) ($row['batch_number'] ?? '')) === ''
            && trim((string) ($row['expiration_date'] ?? '')) === ''
            && (int) ($row['stock'] ?? 0) === 0;
    }

    /** Quita los renglones en blanco antes de validar. */
    protected function limpiarFilasVacias(): void
    {
        $this->batchRows = array_values(array_filter(
            $this->batchRows,
            fn ($row) => ! $this->filaVacia($row)
        ));
    }

    /**
     * Le pone número a los lotes que se dejaron sin número.
     *
     * Cuando el mismo código de barras vuelve a entrar, lo que separa una
     * entrada de otra es el número de lote: el primero es el 1, el siguiente el
     * 2, y así. Si el empaque trae el número del laboratorio se escribe ese; si
     * no, el sistema sigue la cuenta del producto y nadie se queda trancado.
     */
    protected function numerarFilasSinNumero(?int $productId): void
    {
        $usados = array_filter(array_map(
            fn ($row) => trim((string) $row['batch_number']),
            $this->batchRows
        ));

        if ($productId) {
            $usados = array_merge(
                $usados,
                Batch::where('product_id', $productId)->pluck('batch_number')->all()
            );
        }

        foreach ($this->batchRows as $i => $row) {
            if (trim((string) $row['batch_number']) !== '') {
                continue;
            }

            $consecutivo = 0;

            foreach ($usados as $numero) {
                if (preg_match('/^lote\s*(\d+)$/i', trim((string) $numero), $m)) {
                    $consecutivo = max($consecutivo, (int) $m[1]);
                }
            }

            $numero = 'Lote '.($consecutivo + 1);

            $this->batchRows[$i]['batch_number'] = $numero;
            $usados[] = $numero;
        }
    }

    /**
     * Quita una fila. Si era un lote ya guardado queda anotado para borrarlo
     * al guardar, que es cuando se pide la clave.
     */
    public function removeBatchRow(int $index): void
    {
        if (! isset($this->batchRows[$index])) {
            return;
        }

        if ($this->batchRows[$index]['id']) {
            $this->removedBatchIds[] = (int) $this->batchRows[$index]['id'];
        }

        unset($this->batchRows[$index]);
        $this->batchRows = array_values($this->batchRows);
        unset($this->removedBatches);
        $this->resetValidation();
    }

    /** Deshace el "quitar" de un lote que todavía no se ha guardado. */
    public function restoreRemovedBatch(int $id): void
    {
        $this->removedBatchIds = array_values(array_diff($this->removedBatchIds, [$id]));

        if ($batch = Batch::find($id)) {
            $this->batchRows[] = $this->batchRow($batch);
        }

        if (empty($this->removedBatchIds)) {
            $this->batchDeletePassword = '';
        }

        unset($this->removedBatches);
        $this->resetValidation();
    }

    /** Lotes marcados para quitar, para pintarlos en el formulario. */
    #[Computed]
    public function removedBatches()
    {
        return empty($this->removedBatchIds)
            ? collect()
            : Batch::whereIn('id', $this->removedBatchIds)->get();
    }

    protected function batchRowRules(): array
    {
        return [
            'batchRows' => ['array'],
            // El número lo pone el sistema si se deja en blanco, así que aquí
            // ya viene lleno; la regla sólo cuida el largo.
            'batchRows.*.batch_number' => ['required', 'string', 'max:100'],
            'batchRows.*.barcode' => ['nullable', 'string', 'max:64'],
            'batchRows.*.expiration_date' => ['required', 'date', 'after:today'],
            'batchRows.*.stock' => ['required', 'integer', 'min:0'],
            'batchRows.*.is_active' => ['boolean'],
        ];
    }

    /** Nombres legibles: "el campo batchRows.0.batch_number" no le dice nada a nadie. */
    protected function batchRowAttributes(): array
    {
        $atributos = [];

        foreach (array_keys($this->batchRows) as $i) {
            $fila = $i + 1;
            $atributos["batchRows.{$i}.batch_number"] = "número de lote (fila {$fila})";
            $atributos["batchRows.{$i}.barcode"] = "código de barras (fila {$fila})";
            $atributos["batchRows.{$i}.expiration_date"] = "fecha de vencimiento (fila {$fila})";
            $atributos["batchRows.{$i}.stock"] = "unidades (fila {$fila})";
        }

        return $atributos;
    }

    /**
     * Lo que las reglas sueltas no alcanzan a ver: que dos filas no repitan el
     * mismo número de lote y que ningún código de barras apunte a otro producto.
     *
     * Un mismo código en varios lotes del mismo producto sí se permite: es el
     * caso normal, el código del empaque no cambia entre lotes.
     *
     * Devuelve false si encontró algo: los errores quedan puestos fila por fila
     * y el que llama se devuelve sin guardar nada.
     */
    protected function validateBatchRows(?int $productId): bool
    {
        $vistos = [];

        foreach ($this->batchRows as $i => $row) {
            $numero = mb_strtolower(trim((string) $row['batch_number']));

            if ($numero === '') {
                continue;
            }

            // Contra las otras filas del formulario.
            if (isset($vistos[$numero])) {
                $this->addError("batchRows.{$i}.batch_number",
                    'Este número de lote ya está en la fila '.($vistos[$numero] + 1).'.');
            } else {
                $vistos[$numero] = $i;
            }

            // Contra los lotes que el producto ya tiene guardados y que no
            // están abiertos en el formulario.
            if ($productId) {
                $repetido = Batch::where('product_id', $productId)
                    ->whereRaw('LOWER(batch_number) = ?', [$numero])
                    ->when($row['id'], fn ($q) => $q->where('id', '!=', $row['id']))
                    ->whereNotIn('id', $this->removedBatchIds ?: [0])
                    ->exists();

                if ($repetido) {
                    $this->addError("batchRows.{$i}.batch_number",
                        'Este producto ya tiene un lote con ese número.');
                }
            }

            $codigo = trim((string) $row['barcode']);

            if ($codigo === '') {
                continue;
            }

            // El escáner necesita que un código lleve siempre al mismo producto.
            $deOtroProducto = Product::where('barcode', $codigo)
                ->when($productId, fn ($q) => $q->where('id', '!=', $productId))
                ->value('name')
                ?? Batch::where('barcode', $codigo)
                    ->when($productId, fn ($q) => $q->where('product_id', '!=', $productId))
                    ->with('product')
                    ->first()?->product?->name;

            if ($deOtroProducto) {
                $this->addError("batchRows.{$i}.barcode",
                    "El código {$codigo} ya es de \"{$deOtroProducto}\".");
            }
        }

        return ! $this->getErrorBag()->has('batchRows.*');
    }

    /** Guarda las filas. Las vacías se ignoran: nadie tiene que borrarlas. */
    protected function persistBatchRows(int $productId): void
    {
        foreach ($this->batchRows as $row) {
            if (trim((string) $row['batch_number']) === '') {
                continue;
            }

            Batch::updateOrCreate(['id' => $row['id']], [
                'product_id' => $productId,
                'batch_number' => trim((string) $row['batch_number']),
                'barcode' => trim((string) $row['barcode']) ?: null,
                'expiration_date' => $row['expiration_date'],
                'stock' => (int) $row['stock'],
                'is_active' => (bool) $row['is_active'],
            ]);
        }
    }

    /** Quitar un lote guardado borra mercancía: pide la clave, como en la lista. */
    protected function authorizeBatchRemovals(): bool
    {
        if (empty($this->removedBatchIds)) {
            return true;
        }

        if (AdminPassword::check($this->batchDeletePassword)) {
            return true;
        }

        $this->addError('batchDeletePassword', 'Clave incorrecta.');
        $this->batchDeletePassword = '';

        return false;
    }

    /** Borra los lotes quitados, soltando la referencia de las ventas viejas. */
    protected function applyBatchRemovals(): void
    {
        if (empty($this->removedBatchIds)) {
            return;
        }

        SaleDetail::whereIn('batch_id', $this->removedBatchIds)->update(['batch_id' => null]);
        Batch::whereIn('id', $this->removedBatchIds)->delete();
    }

    /**
     * Activa o desactiva el lote. Un lote inactivo conserva su stock pero el
     * punto de venta no lo toca: sirve para apartar mercancía vencida o en
     * revisión sin perder el registro.
     */
    public function toggleBatch(int $id): void
    {
        $batch = Batch::find($id);

        if (! $batch) {
            return;
        }

        $batch->update(['is_active' => ! $batch->is_active]);

        unset($this->products, $this->lowStockCount, $this->expiringCount, $this->expiredCount);

        $this->dispatch('toast', type: 'success', message: $batch->is_active
            ? 'Lote activado.'
            : 'Lote desactivado: su stock ya no se vende.');
    }

    public function deleteBatch(): void
    {
        if (! $this->authorizeDelete()) {
            return;
        }

        $batch = Batch::find($this->confirmingBatchId);
        $this->cancelDelete();

        if (! $batch) {
            return;
        }

        // Las ventas anotan de qué lote salió cada unidad. Al borrarlo esa
        // referencia se suelta y la venta queda igual: mismo producto, mismas
        // cantidades, mismo total.
        try {
            DB::transaction(function () use ($batch) {
                SaleDetail::where('batch_id', $batch->id)->update(['batch_id' => null]);

                $batch->delete();
            });
        } catch (Throwable $e) {
            $this->dispatch('toast', type: 'error', message: 'No se pudo eliminar el lote: '.$e->getMessage());

            return;
        }

        unset($this->products, $this->lowStockCount, $this->expiringCount, $this->expiredCount);

        $this->dispatch('toast', type: 'success', message: 'Lote eliminado.');
    }

    protected function resetBatchForm(): void
    {
        $this->reset(['batchId', 'batchProductId', 'batchRows', 'removedBatchIds', 'batchDeletePassword']);
        unset($this->removedBatches);
        $this->resetValidation();
    }
}
