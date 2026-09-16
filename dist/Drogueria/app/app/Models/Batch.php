<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'batch_number', 'barcode', 'expiration_date', 'stock', 'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * El código con el que se escanea este lote.
     *
     * Casi siempre es el del producto; sólo cuando el lote llegó con un empaque
     * de código distinto se guarda uno propio y ese manda.
     */
    public function getEffectiveBarcodeAttribute(): ?string
    {
        return filled($this->barcode) ? $this->barcode : $this->product?->barcode;
    }

    /** True cuando el lote trae un código distinto al del producto. */
    public function getHasOwnBarcodeAttribute(): bool
    {
        return filled($this->barcode) && $this->barcode !== $this->product?->barcode;
    }
}
