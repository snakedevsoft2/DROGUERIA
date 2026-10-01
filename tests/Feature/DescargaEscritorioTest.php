<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El sitio web ofrece bajar el programa de escritorio; el programa ya
 * instalado no se ofrece a sí mismo.
 */
class DescargaEscritorioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_sitio_muestra_el_boton_de_descarga(): void
    {
        config([
            'drogueria.escritorio' => false,
            'drogueria.descarga_url' => 'https://ejemplo.com/Drogueria-Instalador.zip',
        ]);

        $this->get(route('pos'))
            ->assertOk()
            ->assertSee('Descargar para escritorio')
            ->assertSee('https://ejemplo.com/Drogueria-Instalador.zip', false);
    }

    public function test_el_programa_instalado_no_muestra_el_boton(): void
    {
        config(['drogueria.escritorio' => true]);

        $this->get(route('pos'))
            ->assertOk()
            ->assertDontSee('Descargar para escritorio');
    }
}
