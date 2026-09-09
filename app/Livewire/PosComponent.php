<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Punto de Venta')]
class PosComponent extends Component
{
    public string $search = '';

    /** @var array<int, array<string, mixed>> */
    public array $cart = [];

    public $discount = 0;

    public $paidAmount = 0;

    public string $paymentMethod = 'cash';

    public float $taxRate = 19.0; // IVA Colombia

    public bool $showCheckout = false;

    /** Última venta registrada, para previsualizar o reimprimir. */
    public ?int $lastSaleId = null;

    public bool $showReceiptPreview = false;

    public function render()
    {
        return view('livewire.pos-component', [
            'searchResults' => $this->searchResults,
        ]);
    }

    /**
     * Live product search by name, barcode or presentation.
     * Only products with stock in a non-expired, active batch are surfaced.
     */
    #[Computed]
    public function searchResults()
    {
        $term = trim($this->search);

        if (strlen($term) < 2) {
            return collect();
        }

        return Product::query()
            ->where('is_active', true)
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('presentation', 'like', "%{$term}%");
            })
            ->withSum(['batches as total_stock' => fn ($q) => $this->availableBatches($q)], 'stock')
            ->orderBy('name')
            ->take(7)
            ->get();
    }

    /**
     * Constraint shared by every "sellable stock" query: active batch,
     * stock left, and not past its expiration date.
     */
    protected function availableBatches($query)
    {
        return $query->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereDate('expiration_date', '>', now());
    }

    /**
     * Barcode scanners emit the code then press Enter — exact match wins.
     */
    public function searchByBarcode(): void
    {
        $term = trim($this->search);

        if ($term === '') {
            return;
        }

        $product = Product::where('barcode', $term)->where('is_active', true)->first();

        if ($product) {
            $this->addToCart($product->id);

            return;
        }

        $this->dispatch('toast', type: 'error', message: "No existe un producto con el código {$term}.");
    }

    public function addToCart(int $productId): void
    {
        $product = Product::query()
            ->withSum(['batches as total_stock' => fn ($q) => $this->availableBatches($q)], 'stock')
            ->find($productId);

        if (! $product) {
            $this->dispatch('toast', type: 'error', message: 'El producto no existe.');

            return;
        }

        $availableStock = (int) ($product->total_stock ?? 0);

        if ($availableStock <= 0) {
            $this->dispatch('toast', type: 'error', message: "{$product->name} no tiene stock vigente disponible.");

            return;
        }

        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['quantity'] + 1 > $availableStock) {
                $this->dispatch('toast', type: 'error', message: 'Supera el stock disponible.');

                return;
            }

            $this->cart[$productId]['quantity']++;
        } else {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'presentation' => $product->presentation,
                'requires_prescription' => (bool) $product->requires_prescription,
                'unit_price' => (float) $product->selling_price,
                'quantity' => 1,
                'max_stock' => $availableStock,
            ];
        }

        $this->cart[$productId]['max_stock'] = $availableStock;
        $this->refreshLine($productId);

        $this->search = '';
    }

    public function updateQuantity(int $productId, $quantity): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $quantity = (int) $quantity;

        if ($quantity <= 0) {
            $this->removeFromCart($productId);

            return;
        }

        if ($quantity > $this->cart[$productId]['max_stock']) {
            $this->dispatch('toast', type: 'error', message: 'Cantidad superior al stock disponible.');
            $quantity = $this->cart[$productId]['max_stock'];
        }

        $this->cart[$productId]['quantity'] = $quantity;
        $this->refreshLine($productId);
    }

    protected function refreshLine(int $productId): void
    {
        $line = $this->cart[$productId];

        $this->cart[$productId]['subtotal'] = round($line['quantity'] * $line['unit_price'], 2);
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discount = 0;
        $this->paidAmount = 0;
        $this->paymentMethod = 'cash';
        $this->showCheckout = false;
    }

    #[Computed]
    public function subtotal(): float
    {
        return round(array_sum(array_column($this->cart, 'subtotal')), 2);
    }

    /** Discount is capped at the subtotal so the total can never go negative. */
    #[Computed]
    public function discountAmount(): float
    {
        return round(min(max((float) $this->discount, 0), $this->subtotal), 2);
    }

    #[Computed]
    public function tax(): float
    {
        return round(($this->subtotal - $this->discountAmount) * ($this->taxRate / 100), 2);
    }

    #[Computed]
    public function total(): float
    {
        return round($this->subtotal - $this->discountAmount + $this->tax, 2);
    }

    #[Computed]
    public function change(): float
    {
        return round(max(0, (float) $this->paidAmount - $this->total), 2);
    }

    public function openCheckout(): void
    {
        if (empty($this->cart)) {
            $this->dispatch('toast', type: 'error', message: 'El carrito está vacío.');

            return;
        }

        $this->paidAmount = $this->total;
        $this->showCheckout = true;
    }

    public function closeCheckout(): void
    {
        $this->showCheckout = false;
    }

    /** Vuelve a abrir el comprobante de la última venta. */
    public function previewLastReceipt(): void
    {
        if ($this->lastSaleId) {
            $this->showReceiptPreview = true;
        }
    }

    public function closeReceiptPreview(): void
    {
        $this->showReceiptPreview = false;
    }

    /**
     * Card and transfer payments are always settled at the exact total,
     * so there is no change to hand back.
     */
    public function updatedPaymentMethod(): void
    {
        if ($this->paymentMethod !== 'cash') {
            $this->paidAmount = $this->total;
        }
    }

    public function completeSale(): void
    {
        if (empty($this->cart)) {
            $this->dispatch('toast', type: 'error', message: 'El carrito está vacío.');

            return;
        }

        if (! in_array($this->paymentMethod, ['cash', 'card', 'transfer'], true)) {
            $this->dispatch('toast', type: 'error', message: 'Método de pago inválido.');

            return;
        }

        if ($this->paymentMethod !== 'cash') {
            $this->paidAmount = $this->total;
        }

        if ((float) $this->paidAmount < $this->total) {
            $this->dispatch('toast', type: 'error', message: 'El monto recibido es menor al total a pagar.');

            return;
        }

        try {
            $sale = DB::transaction(function () {
                $sale = Sale::create([
                    'invoice_number' => $this->nextInvoiceNumber(),
                    'subtotal' => $this->subtotal,
                    'tax' => $this->tax,
                    'discount' => $this->discountAmount,
                    'total' => $this->total,
                    'paid_amount' => (float) $this->paidAmount,
                    'change_amount' => $this->change,
                    'payment_method' => $this->paymentMethod,
                    'user_id' => Auth::id(),
                ]);

                foreach ($this->cart as $item) {
                    $this->discountStock($sale, $item);
                }

                return $sale;
            });
        } catch (Exception $e) {
            $this->dispatch('toast', type: 'error', message: 'Error al procesar la venta: '.$e->getMessage());

            return;
        }

        $invoice = $sale->invoice_number;
        $this->lastSaleId = $sale->id;

        $this->clearCart();
        unset($this->searchResults);

        // El carrito ya quedó limpio para la siguiente venta; el comprobante
        // se muestra en pantalla para revisarlo antes de gastar papel.
        $this->showReceiptPreview = true;

        $this->dispatch('toast', type: 'success', message: "Venta {$invoice} registrada con éxito.");
    }

    /**
     * FEFO — batches closest to expiring are consumed first, and each batch
     * touched becomes its own sale detail line so stock stays traceable.
     */
    protected function discountStock(Sale $sale, array $item): void
    {
        $pending = (int) $item['quantity'];

        $batches = Batch::where('product_id', $item['id'])
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->whereDate('expiration_date', '>', now())
            ->orderBy('expiration_date')
            ->lockForUpdate()
            ->get();

        if ($batches->sum('stock') < $pending) {
            throw new Exception("Stock insuficiente para {$item['name']}.");
        }

        foreach ($batches as $batch) {
            if ($pending <= 0) {
                break;
            }

            $deduct = min((int) $batch->stock, $pending);

            $batch->decrement('stock', $deduct);
            $pending -= $deduct;

            SaleDetail::create([
                'sale_id' => $sale->id,
                'product_id' => $item['id'],
                'batch_id' => $batch->id,
                'quantity' => $deduct,
                'unit_price' => $item['unit_price'],
                'subtotal' => round($deduct * $item['unit_price'], 2),
            ]);
        }
    }

    /** Called inside the sale transaction, where the row lock serialises callers. */
    protected function nextInvoiceNumber(): string
    {
        $last = Sale::lockForUpdate()->max('id');

        return 'FAC-'.str_pad((string) ($last + 1), 8, '0', STR_PAD_LEFT);
    }
}
