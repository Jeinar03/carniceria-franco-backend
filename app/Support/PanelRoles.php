<?php

namespace App\Support;

use App\Models\User;

/**
 * Roles del panel y a donde manda a cada uno.
 * Ver: Carnicería Franco/Spec - Roles Admin, Cajero y Despachador.md
 */
class PanelRoles
{
    public const ADMIN = 'Admin';
    public const CAJERO = 'Cajero';
    public const DESPACHADOR = 'Despachador';

    /** Pantalla a la que entra cada rol al iniciar sesion y a la que lleva el logo. */
    public static function homeUrl(?User $user): string
    {
        if ($user && $user->hasRole(self::ADMIN)) {
            return '/clientes';
        }

        return '/clientes/despachos';
    }

    /** Url del logo del encabezado: el Admin ve el dashboard; los demas, Despachos. */
    public static function logoUrl(?User $user): string
    {
        return ($user && $user->hasRole(self::ADMIN)) ? url('home') : url('clientes/despachos');
    }

    public static function esAdmin(?User $user): bool
    {
        return (bool) ($user && $user->hasRole(self::ADMIN));
    }

    public static function puedeCrearOrdenes(?User $user): bool
    {
        return (bool) ($user && $user->hasAnyRole([self::ADMIN, self::CAJERO]));
    }

    /** Valor de la columna users.profile que acompana a un rol (se mantiene por compatibilidad). */
    public static function perfilParaRol(string $rol): string
    {
        return $rol === self::ADMIN ? 'ADMIN' : 'EMPLOYEE';
    }
}
