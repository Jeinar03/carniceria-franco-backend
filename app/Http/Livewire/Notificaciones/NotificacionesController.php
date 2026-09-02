<?php

namespace App\Http\Livewire\Notificaciones;

use App\Models\Notificaciones;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;

class NotificacionesController extends Component
{
    use WithPagination;
    use WithFileUploads;


    public $nombre, $descripcion, $fecha_envio, $logo, $titulo_notificacion, $selected_id;
    public $pageTitle, $componentName;

    public function mount()
    {
        $this->pageTitle = 'Listado de Notificaciones';
        $this->componentName = 'Notificaciones';
    }

    public function render()
    {
        $notificaciones = Notificaciones::orderBy('fecha_envio', 'desc')->paginate(10);

        return view('livewire.notificaciones.notificaciones-controller', [
            'notificaciones' => $notificaciones,
        ])
            ->extends('layouts.theme.app')
            ->section('content');
    }

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function resetUI()
    {
        $this->nombre = '';
        $this->descripcion = '';
        $this->fecha_envio = '';
        $this->logo = '';
        $this->titulo_notificacion = '';
        $this->selected_id = 0;

        $this->resetValidation();
        $this->resetPage();
    }

    public function edit(Notificaciones $notificacion)
    {
        $this->selected_id = $notificacion->id;
        $this->nombre = $notificacion->nombre;
        $this->descripcion = $notificacion->descripcion;
        $this->fecha_envio = $notificacion->fecha_envio;
        $this->logo = $notificacion->logo;
        $this->titulo_notificacion = $notificacion->titulo_notificacion;

        $this->emit('show-modal', 'Abrir Modal de Edición');
    }

    public function Store()
    {
        $rules = [
            'nombre' => 'required|min:3',
            'descripcion' => 'required|min:5',
            'fecha_envio' => 'required|date',
            'titulo_notificacion' => 'required|min:3',
            'logo' => 'nullable|image|max:1024', // Solo imágenes de hasta 1MB
        ];

        $this->validate($rules);

        try {
            // Convertir la imagen a Base64 si se ha cargado
            $logoBase64 = null;
            if ($this->logo) {
                $logoBase64 = base64_encode(file_get_contents($this->logo->getRealPath()));
            }

            Notificaciones::create([
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion,
                'fecha_envio' => $this->fecha_envio,
                'logo' => $logoBase64, // Guardamos la imagen en Base64
                'titulo_notificacion' => $this->titulo_notificacion,
            ]);

            $this->resetUI();
            $this->emit('video-updated', 'Notificación creada exitosamente');
        } catch (\Exception $e) {
            $this->emit('error', 'Error al crear la notificación: ' . $e->getMessage());
        }
    }


    public function update()
    {
        $rules = [
            'nombre' => 'required|min:3',
            'descripcion' => 'required|min:5',
            'fecha_envio' => 'required|date',
            'titulo_notificacion' => 'required|min:3',
        ];

        $this->validate($rules);

        if ($this->selected_id) {
            $notificacion = Notificaciones::find($this->selected_id);
            $notificacion->update([
                'nombre' => $this->nombre,
                'descripcion' => $this->descripcion,
                'fecha_envio' => $this->fecha_envio,
                'logo' => $this->logo,
                'titulo_notificacion' => $this->titulo_notificacion,
            ]);

            $this->resetUI();
            $this->emit('video-updated', 'Notificación actualizada correctamente');
        }
    }

    public function destroy(Notificaciones $notificacion)
    {
        $notificacion->delete();
        $this->resetUI();
        $this->emit('video-updated', 'Notificación eliminada correctamente');
    }

    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI' => 'resetUI'
    ];
}
