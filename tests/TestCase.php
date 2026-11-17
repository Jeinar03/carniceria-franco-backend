<?php

namespace Tests;

use App\Models\Customers;
use App\Support\Legales;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Cliente de prueba que ya acepto los textos legales vigentes, como uno que se registro
     * despues de este cambio. Sin esto, comprar desde la tienda pide la casilla (422).
     */
    protected function clienteConLegales(array $datos): Customers
    {
        $cliente = Customers::create($datos);
        $cliente->forceFill([
            'legales_aceptados_at' => now(),
            'legales_version' => Legales::version(),
        ])->save();

        return $cliente;
    }
}
