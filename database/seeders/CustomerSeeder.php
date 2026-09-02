<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\Customers;

class CustomerSeeder extends Seeder
{
    /**
     * Datos de prueba — clientes del sitio web. Nuevo 2026-09-02.
     * Contraseña de todos: "cliente123".
     */
    public function run()
    {
        $clientes = [
            [
                'nombre' => 'María', 'apellido' => 'González', 'apellido2' => 'López',
                'correo' => 'maria.cliente@test.com', 'telefono' => '7531234567',
                'direccion' => 'Av. Lázaro Cárdenas 123', 'ciudad' => 'Lázaro Cárdenas',
                'estado' => 'Michoacán', 'codigo_postal' => '60950',
                'tipo_cliente' => 'minorista',
            ],
            [
                'nombre' => 'Jorge', 'apellido' => 'Ramírez', 'apellido2' => null,
                'correo' => 'jorge.cliente@test.com', 'telefono' => '7539876543',
                'direccion' => 'Calle Melchor Ocampo 45', 'ciudad' => 'Lázaro Cárdenas',
                'estado' => 'Michoacán', 'codigo_postal' => '60950',
                'tipo_cliente' => 'mayorista', 'limite_credito' => 5000,
            ],
            [
                'nombre' => 'Restaurante', 'apellido' => 'El Fogón', 'apellido2' => null,
                'correo' => 'compras.elfogon@test.com', 'telefono' => '7535551212',
                'direccion' => 'Blvd. Costero 800', 'ciudad' => 'Lázaro Cárdenas',
                'estado' => 'Michoacán', 'codigo_postal' => '60950',
                'tipo_cliente' => 'distribuidor', 'limite_credito' => 20000,
                'descuento_preferencial' => 8,
            ],
        ];

        foreach ($clientes as $c) {
            Customers::create($c + [
                'password'       => Hash::make('cliente123'),
                'pais'           => 'México',
                'estatus'        => 'activo',
                'fecha_registro' => now(),
            ]);
        }
    }
}
