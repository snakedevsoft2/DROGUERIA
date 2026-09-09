<?php

namespace App\Livewire;

use App\Models\Sale;
use App\Models\SaleDetail;
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
            ->selectRaw('COALESCE(SUM(tax), 0) as tax')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->first();

        $transactions = (int) $row->transactions;

        return [
            'transactions' => $transactions,
            'subtotal' => (float) $row->subtotal,
            'discount' => (float) $row->discount,
            'tax' => (float) $row->tax,
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
