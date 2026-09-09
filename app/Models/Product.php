<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'barcode', 'name', 'presentation', 'description',
        'cost_price', 'selling_price', 'min_stock',
        'requires_prescription', 'is_active'
    ];

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function totalStock()
    {
        return $this->batches()->sum('stock');
    }
}