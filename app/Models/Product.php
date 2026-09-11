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
     * En pesos no hay centavos: los precios se manejan como números enteros,
     * y así los formularios no muestran el "100,00" que devuelve la base.
     */
    protected $casts = [
        'cost_price' => 'float',
        'selling_price' => 'float',
        'units_per_package' => 'integer',
        'min_stock' => 'integer',
        'requires_prescription' => 'boolean',
        'is_active' => 'boolean',
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