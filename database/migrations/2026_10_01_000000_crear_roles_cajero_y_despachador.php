<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles del panel: Admin, Cajero y Despachador.
 * Los usuarios que ya existen y no tienen rol lo reciben segun su perfil anterior:
 * ADMIN pasa a Admin; cualquier otro pasa a Cajero (para que no pierdan el acceso que tenian).
 * Se puede correr varias veces sin duplicar nada.
 * Ver: Carnicería Franco/Spec - Roles Admin, Cajero y Despachador.md
 */
return new class extends Migration
{
    public function up()
    {
        foreach (['Admin', 'Cajero', 'Despachador'] as $nombre) {
            Role::findOrCreate($nombre, 'web');
        }

        $conRol = DB::table('model_has_roles')
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id')
            ->all();

        \App\Models\User::whereNotIn('id', $conRol)->get()->each(function ($user) {
            $user->assignRole(strtoupper((string) $user->profile) === 'ADMIN' ? 'Admin' : 'Cajero');
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        // Los roles creados no estorban: se dejan como estan.
    }
};
