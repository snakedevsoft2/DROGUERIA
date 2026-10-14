<?php

use App\Http\Controllers\CashClosingPdfController;
use App\Http\Controllers\ReceiptController;
use App\Livewire\CashClosingComponent;
use App\Livewire\InventoryComponent;
use App\Livewire\PosComponent;
use App\Livewire\ProductListComponent;
use App\Livewire\ReportsComponent;
use Illuminate\Support\Facades\Route;

Route::get('/', PosComponent::class)->name('pos');
Route::get('/inventario', InventoryComponent::class)->name('inventory');
Route::get('/listado', ProductListComponent::class)->name('product-list');
Route::get('/reportes', ReportsComponent::class)->name('reports');
Route::get('/cierre-de-caja', CashClosingComponent::class)->name('cash-closing');

// PDF del cierre de caja. Sin extensión en la ruta: el servidor del programa
// de escritorio buscaría un archivo .pdf en public/ en vez de pasarle a Laravel.
Route::get('/cierre-de-caja/informe/pdf', [CashClosingPdfController::class, 'report'])->name('cash-closing.report-pdf');
Route::get('/cierre-de-caja/historial/pdf', [CashClosingPdfController::class, 'history'])->name('cash-closing.history-pdf');
Route::get('/cierre-de-caja/{closing}/pdf', [CashClosingPdfController::class, 'closing'])->name('cash-closing.pdf');

// Comprobante imprimible (80mm). Con ?print=1 se imprime al cargar.
Route::get('/venta/{sale}/comprobante', ReceiptController::class)->name('receipt');
