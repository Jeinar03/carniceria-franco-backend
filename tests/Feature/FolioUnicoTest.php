<?php

namespace Tests\Feature;

use App\Models\Customers;
use App\Models\Sale;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Venta que calcula primero un folio ya usado, como lo haria otra venta simultanea. */
class SaleConFolioChocado extends Sale
{
    public static array $primeros = [];

    public static function generarFolio()
    {
        return array_shift(static::$primeros) ?? parent::generarFolio();
    }
}

class FolioUnicoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]);
    }

    private function datos(array $extra = []): array
    {
        $cliente = Customers::first() ?? Customers::create([
            'nombre' => 'C', 'apellido' => 'X', 'correo' => 'folio@test.com',
            'password' => Hash::make('cliente123'), 'estatus' => 'activo',
        ]);

        return array_merge([
            'customer_id' => $cliente->id,
            'fecha_venta' => now(),
            'metodo_pago' => 'efectivo',
            'estatus' => 'completada',
            'estado_envio' => 'Pendiente',
            'subtotal' => 10,
            'total' => 10,
        ], $extra);
    }

    public function test_el_folio_es_unico_en_la_base(): void
    {
        Sale::create($this->datos(['folio' => 'X-0001']));

        $this->expectException(QueryException::class);
        Sale::create($this->datos(['folio' => 'X-0001']));
    }

    public function test_si_el_folio_automatico_choca_se_recalcula(): void
    {
        $primera = Sale::create($this->datos());

        SaleConFolioChocado::$primeros = [$primera->folio];
        $segunda = SaleConFolioChocado::create($this->datos());

        $this->assertNotSame($primera->folio, $segunda->folio);
        $this->assertSame(2, Sale::count());
    }

    public function test_un_folio_escrito_a_mano_que_choca_falla_sin_cambiarse(): void
    {
        Sale::create($this->datos(['folio' => 'MANUAL-1']));

        try {
            Sale::create($this->datos(['folio' => 'MANUAL-1']));
            $this->fail('Debio rechazar el folio repetido');
        } catch (QueryException $e) {
            $this->assertSame(1, Sale::where('folio', 'MANUAL-1')->count());
        }
    }
}
