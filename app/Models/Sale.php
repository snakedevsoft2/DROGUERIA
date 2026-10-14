<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'subtotal', 'tax', 'discount',
        'total', 'paid_amount', 'change_amount',
        'payment_method', 'user_id'
    ];

    public function details()
    {
        return $this->hasMany(SaleDetail::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Renglones del comprobante, los mismos en pantalla y en la tiquetera.
     *
     * El descuento de stock es FEFO, así que un mismo producto puede quedar
     * repartido en varios sale_details (uno por lote). En el comprobante el
     * cliente debe ver una sola línea por producto y precio.
     *
     * Un producto borrado del catálogo pierde su id: ahí lo que lo distingue
     * es el nombre que se copió en la venta, para que dos borrados con el
     * mismo precio no se fundan en un solo renglón.
     */
    public function receiptLines(): Collection
    {
        $this->loadMissing('details.product');

        return $this->details
            ->groupBy(fn ($detail) => ($detail->product_id
                ? 'p'.$detail->product_id
                : 'n'.$detail->product_name).'-'.$detail->unit_price)
            ->map(fn ($group) => (object) [
                'name' => $group->first()->product?->name
                    ?? $group->first()->product_name
                    ?? 'Producto eliminado',
                'presentation' => $group->first()->product?->presentation,
                'unit_price' => (float) $group->first()->unit_price,
                'quantity' => (int) $group->sum('quantity'),
                'subtotal' => (float) $group->sum('subtotal'),
            ])
            ->values();
    }
}