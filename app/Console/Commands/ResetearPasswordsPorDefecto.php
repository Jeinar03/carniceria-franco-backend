<?php

namespace App\Console\Commands;

use App\Models\Customers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Los clientes creados desde el panel antes del 2026-10-30 quedaron con una contrasena fija conocida.
 * Este comando les pone una aleatoria y cierra sus sesiones; entran con "Olvide mi contrasena".
 */
class ResetearPasswordsPorDefecto extends Command
{
    protected $signature = 'clientes:resetear-password-default {--dry-run : Solo cuenta cuantos serian}';

    protected $description = 'Quita la contrasena fija de los clientes creados desde el panel y cierra sus sesiones';

    public function handle(): int
    {
        $afectados = 0;

        Customers::query()->orderBy('id')->each(function (Customers $cliente) use (&$afectados) {
            if (!$cliente->password || !Hash::check('defaultpassword', $cliente->password)) {
                return;
            }

            $afectados++;

            if (!$this->option('dry-run')) {
                $cliente->password = Hash::make(Str::random(40));
                $cliente->save();
                $cliente->tokens()->delete();
            }
        });

        $this->info(($this->option('dry-run') ? 'Se reseteaban ' : 'Reseteados ') . $afectados . ' clientes con la contrasena fija.');

        return self::SUCCESS;
    }
}
