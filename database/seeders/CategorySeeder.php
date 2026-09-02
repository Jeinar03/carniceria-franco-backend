<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Datos de prueba — catálogo de una carnicería.
     * Reescrito 2026-09-02 al esquema real del modelo (nombre/descripcion/imagen/activo/orden).
     */
    public function run()
    {
        $categorias = [
            ['nombre' => 'Res',                    'descripcion' => 'Cortes de res frescos',                 'orden' => 1],
            ['nombre' => 'Cerdo',                  'descripcion' => 'Cortes y productos de cerdo',           'orden' => 2],
            ['nombre' => 'Pollo',                  'descripcion' => 'Pollo entero y en piezas',              'orden' => 3],
            ['nombre' => 'Embutidos',              'descripcion' => 'Chorizo, salchicha, jamón y más',       'orden' => 4],
            ['nombre' => 'Marinados y Preparados', 'descripcion' => 'Cortes marinados listos para asar',     'orden' => 5],
            ['nombre' => 'Ofertas',               'descripcion' => 'Promociones de la semana',              'orden' => 6],
        ];

        foreach ($categorias as $cat) {
            Category::create($cat + ['activo' => true]);
        }
    }
}
