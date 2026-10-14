<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo vendido, lo que costó y lo que se ganó en un rango de tiempo.
 *
 * El cierre de caja, los indicadores del día, la semana y el mes, y los
 * informes en PDF sacan sus cifras de aquí, para que las tres cosas den
 * siempre lo mismo.
 *
 * Ganancia = lo cobrado (ya con descuentos) menos lo que costó cada unidad
 * vendida, según el costo que tenía el producto al momento de la venta.
 */
class SalesSummary
{
    public const PAYMENT_METHODS = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
    ];

    /** Costo de lo vendido: unidades por el costo que se copió en la venta. */
    protected const COST = 'COALESCE(SUM(sale_details.quantity * COALESCE(sale_details.unit_cost, 0)), 0)';

    public function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    public static function between(Carbon $from, Carbon $to): self
    {
        return $to->lt($from) ? new self($to, $from) : new self($from, $to);
    }

    /** Días completos, del primero al último inclusive. */
    public static function days(string $from, string $to): self
    {
        return self::between(Carbon::parse($from)->startOfDay(), Carbon::parse($to)->endOfDay());
    }

    public static function today(): self
    {
        return new self(now()->startOfDay(), now()->endOfDay());
    }

    public static function thisWeek(): self
    {
        return new self(now()->startOfWeek(), now()->endOfWeek());
    }

    public static function thisMonth(): self
    {
        return new self(now()->startOfMonth(), now()->endOfMonth());
    }

    /**
     * Truncar una fecha y hora a su día no se escribe igual en todas las
     * bases; esta es la única expresión de la app que depende de eso.
     */
    public static function dayExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'pgsql' => "{$column}::date",
            'sqlsrv' => "CAST({$column} AS DATE)",
            default => "DATE({$column})",
        };
    }

    protected function sales()
    {
        return Sale::query()->whereBetween('created_at', [$this->from, $this->to]);
    }

    protected function details()
    {
        return SaleDetail::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->whereBetween('sales.created_at', [$this->from, $this->to]);
    }

    /**
     * @return array{sales_count: int, units: int, revenue: float, discount: float, cost: float, profit: float, margin: float, average: float}
     */
    public function totals(): array
    {
        $ventas = $this->sales()
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->selectRaw('COALESCE(SUM(discount), 0) as discount')
            ->first();

        $detalle = $this->details()
            ->selectRaw('COALESCE(SUM(sale_details.quantity), 0) as units')
            ->selectRaw(self::COST.' as cost')
            ->first();

        $count = (int) $ventas->sales_count;
        $revenue = round((float) $ventas->revenue, 2);
        $cost = round((float) $detalle->cost, 2);
        $profit = round($revenue - $cost, 2);

        return [
            'sales_count' => $count,
            'units' => (int) $detalle->units,
            'revenue' => $revenue,
            'discount' => round((float) $ventas->discount, 2),
            'cost' => $cost,
            'profit' => $profit,
            // Qué parte de lo vendido quedó como ganancia.
            'margin' => $revenue > 0 ? round($profit / $revenue * 100, 1) : 0.0,
            'average' => $count > 0 ? round($revenue / $count, 2) : 0.0,
        ];
    }

    /**
     * Lo cobrado por cada método de pago, con los tres siempre presentes
     * aunque alguno no haya tenido ventas.
     *
     * @return array<string, array{label: string, sales_count: int, revenue: float}>
     */
    public function byPaymentMethod(): array
    {
        $filas = $this->sales()
            ->select('payment_method')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupBy('payment_method')
            ->get()
            ->keyBy('payment_method');

        $metodos = [];

        foreach (self::PAYMENT_METHODS as $metodo => $nombre) {
            $metodos[$metodo] = [
                'label' => $nombre,
                'sales_count' => (int) ($filas[$metodo]->sales_count ?? 0),
                'revenue' => round((float) ($filas[$metodo]->revenue ?? 0), 2),
            ];
        }

        return $metodos;
    }

    /** Un renglón por cada día con ventas, del más reciente al más antiguo. */
    public function daily(): Collection
    {
        $diaVenta = self::dayExpression('created_at');
        $diaDetalle = self::dayExpression('sales.created_at');

        $costos = $this->details()
            ->selectRaw("{$diaDetalle} as day")
            ->selectRaw(self::COST.' as cost')
            ->groupByRaw($diaDetalle)
            ->pluck('cost', 'day');

        return $this->sales()
            ->selectRaw("{$diaVenta} as day")
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as revenue')
            ->groupByRaw($diaVenta)
            ->get()
            ->map(function ($fila) use ($costos) {
                $revenue = round((float) $fila->revenue, 2);
                $cost = round((float) ($costos[$fila->day] ?? 0), 2);

                return (object) [
                    'day' => Carbon::parse($fila->day),
                    'sales_count' => (int) $fila->sales_count,
                    'revenue' => $revenue,
                    'cost' => $cost,
                    'profit' => round($revenue - $cost, 2),
                ];
            })
            ->sortByDesc('day')
            ->values();
    }

    /**
     * Los productos que más ganancia dejaron. Aquí la ganancia es por renglón
     * (precio menos costo), antes del descuento que se haya hecho a la venta.
     */
    public function topProducts(int $limit = 10): Collection
    {
        // Un producto borrado del catálogo sigue apareciendo con el nombre que
        // se copió en la venta.
        $nombre = "COALESCE(products.name, sale_details.product_name, 'Producto eliminado')";

        return $this->details()
            ->leftJoin('products', 'products.id', '=', 'sale_details.product_id')
            ->selectRaw("{$nombre} as name")
            ->addSelect('products.presentation')
            ->selectRaw('SUM(sale_details.quantity) as units')
            ->selectRaw('SUM(sale_details.subtotal) as revenue')
            ->selectRaw(self::COST.' as cost')
            ->groupBy('sale_details.product_id', 'products.presentation')
            ->groupByRaw($nombre)
            ->orderByRaw('SUM(sale_details.subtotal) - '.self::COST.' DESC')
            ->take($limit)
            ->get()
            ->map(fn ($fila) => (object) [
                'name' => $fila->name,
                'presentation' => $fila->presentation,
                'units' => (int) $fila->units,
                'revenue' => round((float) $fila->revenue, 2),
                'cost' => round((float) $fila->cost, 2),
                'profit' => round((float) $fila->revenue - (float) $fila->cost, 2),
            ]);
    }
}
