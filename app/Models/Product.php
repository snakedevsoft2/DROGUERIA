<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode', 'name', 'presentation', 'units_per_package', 'description',
        'cost_price', 'selling_price', 'min_stock',
        'requires_prescription', 'is_active'
    ];

    /**
     * Lo que cuesta cada unidad suelta.
     *
     * El costo se registra por presentación —lo que se le paga al proveedor por
     * la caja— pero el mostrador vende tabletas, así que el costo real de lo
     * que sale es este.
     */
    public function getUnitCostAttribute(): float
    {
        $unidades = max(1, (int) $this->units_per_package);

        return round((float) $this->cost_price / $unidades, 2);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function totalStock()
    {
        return $this->batches()->sum('stock');
    }
}