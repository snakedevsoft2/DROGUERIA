<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    protected $fillable = [
        'opened_at', 'closed_at', 'sales_count', 'units_sold',
        'revenue', 'discount', 'cost', 'profit',
        'cash_sales', 'card_sales', 'transfer_sales',
        'opening_cash', 'expected_cash', 'counted_cash', 'difference',
        'cashier', 'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'sales_count' => 'integer',
        'units_sold' => 'integer',
        'revenue' => 'float',
        'discount' => 'float',
        'cost' => 'float',
        'profit' => 'float',
        'cash_sales' => 'float',
        'card_sales' => 'float',
        'transfer_sales' => 'float',
        'opening_cash' => 'float',
        'expected_cash' => 'float',
        'counted_cash' => 'float',
        'difference' => 'float',
    ];

    /** El cierre más reciente: de ahí arranca la caja que está abierta. */
    public static function latestClosing(): ?self
    {
        return static::orderByDesc('closed_at')->orderByDesc('id')->first();
    }

    /** Número que se muestra y se imprime: CC-00000001. */
    public function getNumberAttribute(): string
    {
        return 'CC-'.str_pad((string) $this->id, 8, '0', STR_PAD_LEFT);
    }
}
