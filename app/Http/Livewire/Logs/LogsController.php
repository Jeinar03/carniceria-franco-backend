<?php

namespace App\Http\Livewire\Logs;

use App\Models\Customers;
use App\Models\Logs;
use Livewire\Component;

class LogsController extends Component
{
    public $permissionName, $search, $selected_id, $pageTitle, $componentName;
    public $selectedClient; // Variable para almacenar el cliente seleccionado
    private $pagination = 10;

    public function mount()
    {
        $this->pageTitle = 'Buscador';
        $this->componentName = 'Historial';
        $this->selectedClient = null; // Inicializar el cliente seleccionado como null
    }

    public function updatedSelectedClient()
    {
        // Este método se llama automáticamente cuando cambia el valor de $selectedClient
    }

    public function render()
    {
        $logs = Logs::when($this->selectedClient, function ($query) {
            $query->where('usuario', $this->selectedClient);
        })
        ->orderBy('created_at', 'desc')
        ->get();


        $clientes = Customers::all();

        return view('livewire.logs.logs-controller', [
            'logs' => $logs,
            'clientes' => $clientes
        ])
        ->extends('layouts.theme.app')
        ->section('content');
    }
}
