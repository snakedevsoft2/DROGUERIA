<?php

namespace Tests\Feature;

use App\Livewire\ReportsComponent;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Los reportes enseñan cuánto vende el negocio, así que van detrás de una
 * clave que el dueño cambia desde la propia aplicación.
 */
class ClaveReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function venta(float $total = 50000): Sale
    {
        return Sale::create([
            'invoice_number' => 'FAC-00000001',
            'subtotal' => $total, 'tax' => 0, 'discount' => 0, 'total' => $total,
            'paid_amount' => $total, 'change_amount' => 0, 'payment_method' => 'cash',
        ]);
    }

    public function test_sin_clave_no_se_ven_las_ventas(): void
    {
        $this->venta(50000);

        Livewire::test(ReportsComponent::class)
            ->assertSee('Reportes protegidos')
            ->assertDontSee('50.000');
    }

    public function test_con_la_clave_correcta_se_abren_los_reportes(): void
    {
        $this->venta(50000);

        Livewire::test(ReportsComponent::class)
            ->set('password', '1234')
            ->call('unlock')
            ->assertHasNoErrors()
            ->assertSee('50.000')
            ->assertDontSee('Reportes protegidos');
    }

    public function test_una_clave_equivocada_no_abre_nada(): void
    {
        $this->venta(50000);

        Livewire::test(ReportsComponent::class)
            ->set('password', 'loquesea')
            ->call('unlock')
            ->assertHasErrors('password')
            ->assertSee('Reportes protegidos')
            ->assertDontSee('50.000');
    }

    public function test_se_puede_volver_a_bloquear(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('password', '1234')
            ->call('unlock')
            ->call('lock')
            ->assertSee('Reportes protegidos');
    }

    public function test_avisa_mientras_siga_la_clave_de_fabrica(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('password', '1234')
            ->call('unlock')
            ->assertSee('clave de fábrica');
    }

    public function test_cambiar_la_clave_reemplaza_la_anterior(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('password', '1234')
            ->call('unlock')
            ->set('current_password', '1234')
            ->set('new_password', 'drogueria2026')
            ->set('new_password_confirmation', 'drogueria2026')
            ->call('changePassword')
            ->assertHasNoErrors()
            ->call('lock')
            ->set('password', '1234')
            ->call('unlock')
            ->assertHasErrors('password')
            ->set('password', 'drogueria2026')
            ->call('unlock')
            ->assertHasNoErrors();
    }

    public function test_no_cambia_la_clave_sin_la_actual(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('password', '1234')
            ->call('unlock')
            ->set('current_password', 'equivocada')
            ->set('new_password', 'nueva1234')
            ->set('new_password_confirmation', 'nueva1234')
            ->call('changePassword')
            ->assertHasErrors('current_password');

        $this->assertTrue(Hash::check('1234', Setting::get('reports.password')));
    }

    public function test_la_confirmacion_debe_coincidir(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('password', '1234')
            ->call('unlock')
            ->set('current_password', '1234')
            ->set('new_password', 'nueva1234')
            ->set('new_password_confirmation', 'otracosa')
            ->call('changePassword')
            ->assertHasErrors('new_password');
    }

    public function test_la_clave_queda_guardada_cifrada(): void
    {
        Livewire::test(ReportsComponent::class)
            ->set('password', '1234')
            ->call('unlock');

        $guardado = Setting::get('reports.password');

        $this->assertNotSame('1234', $guardado, 'La clave no puede quedar en texto plano.');
        $this->assertTrue(Hash::check('1234', $guardado));
    }

    public function test_el_comprobante_sigue_abierto_para_reimprimir(): void
    {
        $sale = $this->venta();

        // La clave protege el reporte de ventas; el comprobante de una venta
        // puntual se sigue pudiendo reimprimir desde el punto de venta.
        $this->get(route('receipt', $sale))->assertOk();
    }
}
