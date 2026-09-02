<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SiteConfig;
use App\Models\SiteAlert;

class SiteConfigSeeder extends Seeder
{
    /**
     * Datos de prueba — configuración del sitio web + una alerta. Nuevo 2026-09-02.
     * El frontend llama GET /api/v1/sitio/config al cargar.
     */
    public function run()
    {
        SiteConfig::create([
            'nombre'        => 'Carnicería Franco',
            'logo'          => null,
            'direccion'     => 'Av. Lázaro Cárdenas 500, Lázaro Cárdenas, Michoacán',
            'correo'        => 'contacto@carniceriafranco.test',
            'telefono'      => '753-100-2000',
            'facebook_url'  => 'https://facebook.com/carniceriafranco',
            'instagram_url' => 'https://instagram.com/carniceriafranco',
            'whatsapp'      => '5217531002000',
            'horarios'      => [
                'lunes'     => '08:00 - 20:00',
                'martes'    => '08:00 - 20:00',
                'miercoles' => '08:00 - 20:00',
                'jueves'    => '08:00 - 20:00',
                'viernes'   => '08:00 - 20:00',
                'sabado'    => '08:00 - 21:00',
                'domingo'   => '08:00 - 15:00',
            ],
            'activo'        => true,
        ]);

        SiteAlert::create([
            'titulo'        => 'Ofertas de la semana',
            'descripcion'   => 'Aprovecha los descuentos en molida especial, costilla de cerdo y tocino.',
            'imagen'        => null,
            'link_url'      => '/productos?en_oferta=1',
            'link_texto'    => 'Ver ofertas',
            'fecha_inicio'  => null,
            'dias_duracion' => 7,
            'tipo'          => 'oferta',
            'activo'        => true,
        ]);
    }
}
