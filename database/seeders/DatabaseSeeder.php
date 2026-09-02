<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Orden: usuarios/roles primero, luego catálogo (categorías → productos +
     * inventario inicial), luego clientes. Actualizado 2026-09-02.
     */
    public function run()
    {
        $this->call([
            DenominationSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            CustomerSeeder::class,
            SiteConfigSeeder::class,
        ]);
    }
}
