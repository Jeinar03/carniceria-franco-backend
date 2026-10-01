<?php

namespace App\Http\Livewire\AvisosPedidos;

use App\Models\PanelSetting;
use Livewire\Component;

/**
 * Pantalla "Avisos de pedidos": minutos a partir de los cuales el aviso "Por terminar"
 * pasa a amarillo y a rojo. Solo administrador (ver routes/web.php).
 */
class AvisosPedidosController extends Component
{
    public $pageTitle = 'Avisos de pedidos';
    public $componentName = 'Sistema';

    public $amarilloMin;
    public $rojoMin;

    public function mount(): void
    {
        $this->amarilloMin = (int) PanelSetting::get('aviso_pedidos_amarillo_min', 30);
        $this->rojoMin = (int) PanelSetting::get('aviso_pedidos_rojo_min', 60);
    }

    public function save(): void
    {
        $this->validate([
            'amarilloMin' => 'required|integer|min:1|max:1440',
            'rojoMin' => 'required|integer|min:2|max:1440|gt:amarilloMin',
        ], [
            'amarilloMin.required' => 'Escribe los minutos para el amarillo.',
            'amarilloMin.integer' => 'Los minutos deben ser un numero entero.',
            'amarilloMin.min' => 'El amarillo debe ser de al menos 1 minuto.',
            'amarilloMin.max' => 'El maximo es 1440 minutos (24 horas).',
            'rojoMin.required' => 'Escribe los minutos para el rojo.',
            'rojoMin.integer' => 'Los minutos deben ser un numero entero.',
            'rojoMin.max' => 'El maximo es 1440 minutos (24 horas).',
            'rojoMin.gt' => 'El rojo debe ser mayor que el amarillo.',
        ]);

        PanelSetting::set('aviso_pedidos_amarillo_min', (int) $this->amarilloMin);
        PanelSetting::set('aviso_pedidos_rojo_min', (int) $this->rojoMin);

        $this->emit('global-msg', 'Avisos guardados correctamente');
    }

    public function render()
    {
        return view('livewire.avisos-pedidos.avisos-pedidos-controller')
            ->extends('layouts.theme.app')
            ->section('content');
    }
}
