<?php

namespace App\Livewire;

use App\Models\Product;
use App\Support\Like;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Listado interno de todos los productos guardados con su costo y su precio
 * de venta por unidad. A diferencia de la lista de precios (pública), muestra
 * el costo y no oculta los productos sin precio.
 */
#[Layout('components.layouts.app')]
#[Title('Listado de productos')]
class ProductListComponent extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'inactivos', except: false)]
    public bool $showInactive = true;

    public function render()
    {
        return view('livewire.product-list-component', [
            'products' => $this->products,
        ]);
    }

    #[Computed(persist: false)]
    public function products()
    {
        return Product::query()
            ->unless($this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $like = Like::operator();
                $query->where(function ($q) use ($term, $like) {
                    $q->where('name', $like, "%{$term}%")
                        ->orWhere('barcode', $like, "%{$term}%")
                        ->orWhere('presentation', $like, "%{$term}%");
                });
            })
            ->orderBy('name')
            ->get();
    }
}
