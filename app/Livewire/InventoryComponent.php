<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Product;
use App\Models\SaleDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public string $description = '';

    public $cost_price = 0;

    public $selling_price = 0;

    public $min_stock = 5;

    public bool $requires_prescription = false;

    public bool $is_active = true;

    // --- Batch form ---
    public bool $showBatchModal = false;

    public ?int $batchProductId = null;

    public ?int $batchId = null;

    public string $batch_number = '';

    public string $expiration_date = '';

    public $stock = 0;

    public bool $batch_is_active = true;

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

    /** Batches expiring within 90 days — the pharmacy's rotation window. */
    protected function expiringBatches($query)
    {
        return $query->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereBetween('expiration_date', [now(), now()->addDays(90)]);
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

        $this->availableBatches($sub);

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
            ->with(['batches' => fn ($q) => $q->orderBy('expiration_date')])
            ->when($this->search !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%")
                        ->orWhere('presentation', 'like', "%{$term}%");
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
        $this->showProductModal = true;
    }

    public function editProduct(int $id): void
    {
        $product = Product::findOrFail($id);

        $this->productId = $product->id;
        $this->barcode = (string) $product->barcode;
        $this->name = (string) $product->name;
        $this->presentation = (string) $product->presentation;
        $this->description = (string) $product->description;
        $this->cost_price = $product->cost_price;
        $this->selling_price = $product->selling_price;
        $this->min_stock = $product->min_stock;
        $this->requires_prescription = (bool) $product->requires_prescription;
        $this->is_active = (bool) $product->is_active;

        $this->resetValidation();
        $this->showProductModal = true;
    }

    protected function productRules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($this->productId)],
            'name' => ['required', 'string', 'max:255'],
            'presentation' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0', 'gte:cost_price'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'requires_prescription' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'barcode.unique' => 'Ya existe un producto con este código de barras.',
            'selling_price.gte' => 'El precio de venta no puede ser menor al costo.',
            'expiration_date.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
        ];
    }

    public function saveProduct(): void
    {
        $data = $this->validate($this->productRules());

        Product::updateOrCreate(['id' => $this->productId], $data);

        $this->showProductModal = false;
        $this->resetProductForm();
        unset($this->products, $this->lowStockCount);

        $this->dispatch('toast', type: 'success', message: 'Producto guardado correctamente.');
    }

    public function confirmDelete(int $id): void
    {
        $this->confirmingProductId = $id;
    }

    public function deleteProduct(): void
    {
        $product = Product::find($this->confirmingProductId);
        $this->confirmingProductId = null;

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
            'productId', 'barcode', 'name', 'presentation', 'description',
            'cost_price', 'selling_price', 'min_stock', 'requires_prescription', 'is_active',
        ]);
        $this->resetValidation();
    }

    // ------------------------------------------------------------------
    // Batch CRUD
    // ------------------------------------------------------------------

    public function createBatch(int $productId): void
    {
        $this->resetBatchForm();
        $this->batchProductId = $productId;
        $this->showBatchModal = true;
    }

    public function editBatch(int $id): void
    {
        $batch = Batch::findOrFail($id);

        $this->batchId = $batch->id;
        $this->batchProductId = $batch->product_id;
        $this->batch_number = (string) $batch->batch_number;
        $this->expiration_date = (string) $batch->expiration_date;
        $this->stock = $batch->stock;
        $this->batch_is_active = (bool) $batch->is_active;

        $this->resetValidation();
        $this->showBatchModal = true;
    }

    public function saveBatch(): void
    {
        $this->validate([
            'batchProductId' => ['required', 'exists:products,id'],
            'batch_number' => ['required', 'string', 'max:100'],
            'expiration_date' => ['required', 'date', 'after:today'],
            'stock' => ['required', 'integer', 'min:0'],
            'batch_is_active' => ['boolean'],
        ]);

        Batch::updateOrCreate(['id' => $this->batchId], [
            'product_id' => $this->batchProductId,
            'batch_number' => $this->batch_number,
            'expiration_date' => $this->expiration_date,
            'stock' => $this->stock,
            'is_active' => $this->batch_is_active,
        ]);

        $this->showBatchModal = false;
        $this->resetBatchForm();
        unset($this->products, $this->lowStockCount, $this->expiringCount, $this->expiredCount);

        $this->dispatch('toast', type: 'success', message: 'Lote guardado correctamente.');
    }

    public function deleteBatch(int $id): void
    {
        $batch = Batch::find($id);

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
        $this->reset(['batchId', 'batchProductId', 'batch_number', 'expiration_date', 'stock']);
        $this->batch_is_active = true;
        $this->resetValidation();
    }
}
