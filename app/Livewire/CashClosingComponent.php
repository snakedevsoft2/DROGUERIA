<?php

namespace App\Livewire;

use App\Models\CashClosing;
use App\Services\SalesSummary;
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
#[Title('Cierre de caja')]
class CashClosingComponent extends Component
{
    use WithPagination;

    // --- Arqueo de la caja abierta ---

    /** Base con la que abrió la caja (el sencillo para dar cambio). */
    public $opening_cash = 0;

    /** Efectivo que se contó al cerrar. En blanco: no se contó. */
    public $counted_cash = '';

    public string $cashier = '';

    public string $notes = '';

    /** Cierre recién hecho, para ofrecer su PDF. */
    public ?int $lastClosingId = null;

    // --- Informes ---

    #[Url(as: 'desde', except: '')]
    public string $from = '';

    #[Url(as: 'hasta', except: '')]
    public string $to = '';

    // --- Anular el último cierre ---

    public ?int $confirmingClosingId = null;

    public string $deletePassword = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: now()->toDateString();

        // La base casi siempre es la misma de un día para otro.
        $ultimo = CashClosing::latestClosing();
        $this->opening_cash = $ultimo?->opening_cash ?? 0;
        $this->cashier = (string) ($ultimo?->cashier ?? '');
    }

    public function render()
    {
        return view('livewire.cash-closing-component', [
            'closings' => CashClosing::orderByDesc('closed_at')->orderByDesc('id')->paginate(10),
        ]);
    }

    // ------------------------------------------------------------------
    // Cómo vamos: hoy, esta semana y este mes
    // ------------------------------------------------------------------

    #[Computed]
    public function progress(): array
    {
        return [
            'Hoy' => [now()->translatedFormat('l j \d\e F'), SalesSummary::today()->totals()],
            'Esta semana' => [
                'Del '.now()->startOfWeek()->translatedFormat('j M').' al '.now()->endOfWeek()->translatedFormat('j M'),
                SalesSummary::thisWeek()->totals(),
            ],
            'Este mes' => [ucfirst(now()->translatedFormat('F Y')), SalesSummary::thisMonth()->totals()],
        ];
    }

    // ------------------------------------------------------------------
    // Caja abierta
    // ------------------------------------------------------------------

    /**
     * La caja cuenta desde el segundo siguiente al último cierre. Si nunca se
     * ha cerrado, desde el comienzo del día: lo de antes no estuvo en esta caja.
     */
    #[Computed]
    public function openedAt(): Carbon
    {
        $ultimo = CashClosing::latestClosing();

        return $ultimo ? $ultimo->closed_at->copy()->addSecond() : now()->startOfDay();
    }

    #[Computed]
    public function register(): array
    {
        $periodo = SalesSummary::between($this->openedAt, now());

        return [
            'totals' => $periodo->totals(),
            'payments' => $periodo->byPaymentMethod(),
        ];
    }

    /** Lo que debería haber en el cajón: la base más lo cobrado en efectivo. */
    #[Computed]
    public function expectedCash(): float
    {
        return round((float) $this->opening_cash + $this->register['payments']['cash']['revenue'], 2);
    }

    /** Positivo: sobra plata. Negativo: falta. Null si no se ha contado. */
    #[Computed]
    public function difference(): ?float
    {
        if (! is_numeric($this->counted_cash)) {
            return null;
        }

        return round((float) $this->counted_cash - $this->expectedCash, 2);
    }

    public function closeRegister(): void
    {
        $this->validate([
            'opening_cash' => ['required', 'numeric', 'min:0'],
            'counted_cash' => ['nullable', 'numeric', 'min:0'],
            'cashier' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'opening_cash.required' => 'Escriba la base con la que abrió la caja (puede ser 0).',
            'opening_cash.numeric' => 'La base debe ser un número.',
            'counted_cash.numeric' => 'El efectivo contado debe ser un número.',
        ]);

        // Se cierra en el segundo anterior: una venta que entre en este mismo
        // segundo queda en la caja siguiente y no se pierde entre las dos.
        $closedAt = now()->subSecond()->startOfSecond();
        $openedAt = $this->openedAt;

        // Doble clic en "Cerrar caja": la primera pulsación ya la cerró.
        if ($closedAt->lt($openedAt)) {
            return;
        }

        $periodo = SalesSummary::between($openedAt, $closedAt);
        $totales = $periodo->totals();
        $pagos = $periodo->byPaymentMethod();

        $esperado = round((float) $this->opening_cash + $pagos['cash']['revenue'], 2);
        $contado = is_numeric($this->counted_cash) ? round((float) $this->counted_cash, 2) : null;

        $cierre = DB::transaction(fn () => CashClosing::create([
            'opened_at' => $openedAt,
            'closed_at' => $closedAt,
            'sales_count' => $totales['sales_count'],
            'units_sold' => $totales['units'],
            'revenue' => $totales['revenue'],
            'discount' => $totales['discount'],
            'cost' => $totales['cost'],
            'profit' => $totales['profit'],
            'cash_sales' => $pagos['cash']['revenue'],
            'card_sales' => $pagos['card']['revenue'],
            'transfer_sales' => $pagos['transfer']['revenue'],
            'opening_cash' => round((float) $this->opening_cash, 2),
            'expected_cash' => $esperado,
            'counted_cash' => $contado,
            'difference' => $contado === null ? null : round($contado - $esperado, 2),
            'cashier' => trim($this->cashier) ?: null,
            'notes' => trim($this->notes) ?: null,
        ]));

        $this->lastClosingId = $cierre->id;
        $this->reset(['counted_cash', 'notes']);
        $this->resetPage();
        $this->forget();

        $this->dispatch('toast', type: 'success', message: "Caja cerrada ({$cierre->number}). La caja nueva arranca en cero.");
    }

    // ------------------------------------------------------------------
    // Informes
    // ------------------------------------------------------------------

    public function setReportRange(string $range): void
    {
        [$desde, $hasta] = match ($range) {
            'week' => [now()->startOfWeek(), now()],
            'month' => [now()->startOfMonth(), now()],
            'last-month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            default => [now(), now()],
        };

        $this->from = $desde->toDateString();
        $this->to = $hasta->toDateString();
        unset($this->report);
    }

    #[Computed]
    public function report(): array
    {
        $periodo = SalesSummary::days($this->from ?: now()->toDateString(), $this->to ?: now()->toDateString());

        return [
            'from' => $periodo->from,
            'to' => $periodo->to,
            'totals' => $periodo->totals(),
            'closings' => CashClosing::whereBetween('closed_at', [$periodo->from, $periodo->to])->count(),
        ];
    }

    // ------------------------------------------------------------------
    // Anular un cierre: con la clave, y sólo el último
    // ------------------------------------------------------------------

    /**
     * Sólo se anula el último: sus ventas vuelven a la caja abierta. Uno de
     * más atrás dejaría un hueco, ventas que no estarían en ningún cierre.
     */
    public function confirmDeleteClosing(int $id): void
    {
        if (CashClosing::latestClosing()?->id !== $id) {
            $this->dispatch('toast', type: 'error', message: 'Sólo se puede anular el último cierre.');

            return;
        }

        $this->confirmingClosingId = $id;
        $this->deletePassword = '';
        $this->resetValidation();
    }

    public function cancelDeleteClosing(): void
    {
        $this->confirmingClosingId = null;
        $this->deletePassword = '';
        $this->resetValidation();
    }

    public function deleteClosing(): void
    {
        if (! AdminPassword::check($this->deletePassword)) {
            $this->addError('deletePassword', 'Clave incorrecta.');
            $this->deletePassword = '';

            return;
        }

        $cierre = CashClosing::find($this->confirmingClosingId);
        $this->cancelDeleteClosing();

        // Entre que se abrió la confirmación y se dio la clave pudo hacerse
        // otro cierre: se vuelve a comprobar que siga siendo el último.
        if (! $cierre || CashClosing::latestClosing()?->id !== $cierre->id) {
            $this->dispatch('toast', type: 'error', message: 'Sólo se puede anular el último cierre.');

            return;
        }

        $numero = $cierre->number;
        $cierre->delete();

        if ($this->lastClosingId === $cierre->id) {
            $this->lastClosingId = null;
        }

        $this->forget();

        $this->dispatch('toast', type: 'success', message: "Cierre {$numero} anulado: sus ventas volvieron a la caja abierta.");
    }

    /** Las cifras cambiaron: que se vuelvan a calcular. */
    protected function forget(): void
    {
        unset($this->progress, $this->openedAt, $this->register, $this->expectedCash, $this->difference, $this->report);
    }
}
