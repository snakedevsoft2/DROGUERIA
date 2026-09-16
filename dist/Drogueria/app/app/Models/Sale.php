<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}