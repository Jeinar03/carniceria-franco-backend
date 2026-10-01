<?php

namespace App\Http\Livewire\Concerns;

/**
 * Candado dentro de una pantalla Livewire: ademas del candado de la ruta, las acciones
 * sensibles revisan el rol de quien las llama (error 403 si no le toca).
 * Ver: Carnicería Franco/Spec - Roles Admin, Cajero y Despachador.md
 */
trait RequiresPanelRole
{
    protected function requirePanelRole(string ...$roles): void
    {
        $user = auth()->user();

        abort_unless($user && $user->hasAnyRole($roles), 403, 'No tienes permiso para esta acción.');
    }
}
