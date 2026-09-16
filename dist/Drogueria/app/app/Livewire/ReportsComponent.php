<?php

namespace App\Livewire;

use App\Models\Batch;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Support\AdminPassword;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Reportes')]
class ReportsComponent extends Component
{
    use WithPagination;

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    #[Url(as: 'pago', except: 'all')]
    public string $paymentMethod = 'all';

    public ?int $expandedSaleId = null;

    public function mount(): void
    {
        $this->from = $this->from ?: now()->toDateString();
        $this->to = $this->to ?: now()->toDateString();
    }

    // --- Clave del dueño ---
    // Los reportes se consultan libremente: lo que pide clave es borrar
    // inventario. Aquí sólo se administra esa clave.

    public bool $showPasswordForm = false;

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    #[Computed(persist: false)]
    public function usingDefaultPassword(): bool
    {
        return AdminPassword::isDefault();
    }

    // --- Eliminar una venta del historial ---

    public ?int $confirmingSaleId = null;

    public string $deletePassword = '';

    public function confirmDeleteSale(int $id): void
    {
        $this->confirmingSaleId = $id;
        $this->deletePassword = '';
        $this->resetValidation();
    }

    public function cancelDeleteSale(): void
    {
        $this->confirmingSaleId = null;
        $this->deletePassword = '';
        $this->resetValidation();
    }

    /**
     * Borra una venta del historial. Pide la clave del dueño y devuelve las
     * unidades a su lote: si la venta se anula, la mercancía no se vendió y
     * tiene que volver al inventario.
     */
    public function deleteSale(): void
    {
        if (! AdminPassword::check($this->deletePassword)) {
            $this->addError('deletePassword', 'Clave incorrecta.');
            $this->deletePassword = '';

            return;
        }

        $sale = Sale::with('details')->find($this->confirmingSaleId);
        $factura = $sale?->invoice_number;
        $this->cancelDeleteSale();

        if (! $sale) {
            return;
        }

        DB::transaction(function () use ($sale) {
            foreach ($sale->details as $detail) {
                // El lote puede haberse borrado del inventario; en ese caso no
                // hay dónde devolver las unidades.
                if ($detail->batch_id) {
                    Batch::where('id', $detail->batch_id)->increment('stock', (int) $detail->quantity);
                }
            }

            $sale->delete();
        });

        $this->resetPage();
        unset($this->summary, $this->byPaymentMethod, $this->topProducts, $this->daily);

        $this->dispatch('toast', type: 'success', message: "Venta {$factura} eliminada; el stock volvió al inventario.");
    }

    public function changePassword(): void
    {
        $this->validate([
            'current_password' => ['required'],
            'new_password' => ['required', 'string', 'min:4', 'max:64', 'confirmed'],
        ], [
            'current_password.required' => 'Escriba la clave actual.',
            'new_password.required' => 'Escriba la clave nueva.',
            'new_password.min' => 'La clave nueva debe tener al menos 4 caracteres.',
            'new_password.confirmed' => 'La confirmación no coincide con la clave nueva.',
        ]);

        if (! AdminPassword::check($this->current_password)) {
            $this->addError('current_password', 'La clave actual no es correcta.');

            return;
        }

        AdminPassword::set($this->new_password);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->showPasswordForm = false;
        unset($this->usingDefaultPassword);

        $this->dispatch('toast', type: 'success', message: 'Clave actualizada.');
    }

    public function render()
    {
        return view('livewire.reports-component', [
            'sales' => $this->sales,
        ]);
    }

    public function updated($property): void
    {
        if (in_array($property, ['from', 'to', 'paymentMethod'], true)) {
            $this->expandedSaleId = null;
            $this->resetPage();
        }
    }

    public function setRange(string $range): void
    {
        [$this->from, $this->to] = match ($range) {
            'today' => [now()->toDateString(), now()->toDateString()],
            'yesterday' => [now()->subDay()->toDateString(), now()->subDay()->toDateString()],
            'week' => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            default => [now()->toDateString(), now()->toDateString()],
        };

        $this->expandedSaleId = null;
        $this->resetPage();
    }

    /** Inclusive day boundaries for the selected range. */
    protected function range(): array
    {
        $from = Carbon::parse($this->from ?: now())->startOfDay();
        $to = Carbon::parse($this->to ?: now())->endOfDay();

        return $to->lt($from) ? [$to->copy()->startOfDay(), $from->copy()->endOfDay()] : [$from, $to];
    }

    protected function baseQuery()
    {
        [$from, $to] = $this->range();

        return Sale::query()
            ->whereBetween('created_at', [$from, $to])
            ->when($this->paymentMethod !== 'all', fn ($q) => $q->where('payment_method', $this->paymentMethod));
    }

    #[Computed(persist: false)]
    public function sales()
    {
        return $this->baseQuery()
            ->with(['details.product', 'details.batch'])
            ->latest('created_at')
            ->paginate(15);
    }

    /** Headline totals for the selected range. */
    #[Computed]
    public function summary(): array
    {
        $row = $this->baseQuery()
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->first();

        $transactions = (int) $row->transactions;

        return [
            'transactions' => $transactions,
            'subtotal' => (float) $row->subtotal,
            'discount' => (float) $row->discount,
            'revenue' => (float) $row->revenue,
            'average' => $transactions > 0 ? (float) $row->revenue / $transactions : 0.0,
        ];
    }

    /** Revenue split by payment method, for the breakdown panel. */
    #[Computed]
    public function byPaymentMethod()
    {
        return $this->baseQuery()
            ->select('payment_method')
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupBy('payment_method')
            ->orderByDesc('revenue')
            ->get();
    }

    /** Units sold per day across the range, for the daily summary table. */
    #[Computed]
    public function daily()
    {
        // Truncating a timestamp to its date has no portable spelling, and
        // this query is the only place the app depends on one.
        $day = match (DB::connection()->getDriverName()) {
            'pgsql' => 'created_at::date',
            'sqlsrv' => 'CAST(created_at AS DATE)',
            default => 'DATE(created_at)',
        };

        return $this->baseQuery()
            ->selectRaw("{$day} as day")
            ->selectRaw('COUNT(*) as transactions')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupByRaw($day)
            ->orderByDesc('day')
            ->get();
    }

    #[Computed]
    public function topProducts()
    {
        [$from, $to] = $this->range();

        return SaleDetail::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->join('products', 'products.id', '=', 'sale_details.product_id')
            ->whereBetween('sales.created_at', [$from, $to])
            ->when($this->paymentMethod !== 'all', fn ($q) => $q->where('sales.payment_method', $this->paymentMethod))
            ->select('products.name', 'products.presentation')
            ->selectRaw('SUM(sale_details.quantity) as units')
            ->selectRaw('SUM(sale_details.subtotal) as revenue')
            ->groupBy('products.id', 'products.name', 'products.presentation')
            ->orderByDesc(DB::raw('SUM(sale_details.quantity)'))
            ->take(10)
            ->get();
    }

    public function toggleSale(int $saleId): void
    {
        $this->expandedSaleId = $this->expandedSaleId === $saleId ? null : $saleId;
    }
}
