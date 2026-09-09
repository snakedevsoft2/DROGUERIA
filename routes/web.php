<?php

use App\Http\Controllers\ReceiptController;
use App\Livewire\InventoryComponent;
use App\Livewire\PosComponent;
use App\Livewire\ReportsComponent;
use Illuminate\Support\Facades\Route;

Route::get('/', PosComponent::class)->name('pos');
Route::get('/inventario', InventoryComponent::class)->name('inventory');
Route::get('/reportes', ReportsComponent::class)->name('reports');

// Comprobante imprimible (80mm). Con ?print=1 se imprime al cargar.
Route::get('/venta/{sale}/comprobante', ReceiptController::class)->name('receipt');
