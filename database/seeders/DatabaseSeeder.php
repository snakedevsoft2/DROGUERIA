<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * La aplicación no tiene inicio de sesión, así que no se crea ningún
     * usuario: sólo el catálogo, los lotes y el histórico de ventas.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
