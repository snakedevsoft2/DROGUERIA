<?php

namespace App\Livewire;

use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Lista de precios al público: cada producto registrado con lo que vale la
 * unidad suelta y la caja completa. Sólo consulta; los precios se cambian
 * desde Inventario.
 */
#[Layout('components.layouts.app')]
#[Title('Lista de precios')]
class PriceListComponent extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** Por defecto se ocultan los productos desactivados: no se venden. */
    #[Url(as: 'inactivos', except: false)]
    public bool $showInactive = false;

    public function render()
    {
        return view('livewire.price-list-component', [
            'products' => $this->products,
        ]);
    }

    /**
     * Sin paginar: la lista se imprime o se recorre entera en el mostrador.
     * Un producto aparece en cuanto tiene precio de venta.
     */
    #[Computed(persist: false)]
    public function products()
    {
        return Product::query()
            ->where('selling_price', '>', 0)
            ->unless($this->showInactive, fn ($q) => $q->where('is_active', true))
            ->when(trim($this->search) !== '', function ($query) {
                $term = trim($this->search);
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('barcode', 'like', "%{$term}%")
                        ->orWhere('presentation', 'like', "%{$term}%")
                        ->orWhereHas('batches', fn ($b) => $b->where('barcode', 'like', "%{$term}%"));
                });
            })
            ->orderBy('name')
            ->get();
    }
}
