<?php

namespace App\Http\Livewire\Equipo;

use Livewire\Component;
use App\Models\Equipo;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class EquipoController extends Component
{
    use WithPagination;
    use WithFileUploads;
    private $pagination = 3;
    public $imagen;
    public $nombre;
    public $descripcion;
    public $pageTitle, $componentName;
    public $selected_id;
    public function render()
    {
        // Obtener los equipos únicos por nombre y paginarlos
        $tags = Equipo::select('id', 'nombre')->distinct()->paginate($this->pagination);

        return view('livewire.equipo.equipo-controller', [
            'tags' => $tags
        ])->extends('layouts.theme.app')
            ->section('content');
    }

    public function mount()
    {
        $this->pageTitle = 'Listado';
        $this->componentName = 'Equipo de Trabajo';
    }
    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }
    public function resetUI()
    {
        $this->nombre = '';
        $this->descripcion = '';
        $this->imagen = '';


        $this->selected_id = 0;
        $this->resetValidation();
        $this->resetPage();
    }

    public function edit(Equipo $user)
    {
        $this->selected_id = $user->id;
        $this->nombre = $user->nombre;


        $this->emit('show-modal', 'open!');
    }
    public function Store()
    {
        $rules = [
            'nombre' => 'required|min:3',

        ];

        $this->validate($rules);

        try {


            $user = Equipo::create([
                'nombre' => $this->nombre,

            ]);

            $this->resetUI();
            $this->emit('user-added', 'Usuario Registrado');
        } catch (\Exception $e) {
            dd($e);
            // Manejar la excepción (puedes personalizar este mensaje o realizar otras acciones)
            $this->emit('error', 'Ocurrió un error al registrar el usuario: ' . $e->getMessage());
        }
    }


    public function Update()
    {
        $rules = [
            'nombre' => 'required|min:3',


        ];



        $this->validate($rules);

        $user = Equipo::find($this->selected_id);
        $user->update([
            'nombre' => $this->nombre,


        ]);

        $this->resetUI();
        $this->emit('user-updated', 'Usuario Actualizado');
    }
    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI' => 'resetUI'

    ];

    public function destroy($id)
    {
        try {
            //   dd($id);
            // Buscar el equipo por su ID
            $user = Equipo::find($id);
            //   dd($user);

            // Verificar si el equipo existe
            if (!$user) {
                $this->emit('global-msg', 'No se puede eliminar: equipo no encontrado.');
                return;
            }

            // Verificar si el equipo tiene relaciones con videos o grupos musculares
            if ($user->videos()->exists()) {
                $this->emit('global-msg', 'No se puede eliminar: el equipo está asociado a uno o más videos.');
                return;
            }

            if ($user->gruposMusculares()->exists()) {
                $this->emit('global-msg', 'No se puede eliminar: el equipo está asociado a uno o más grupos musculares.');
                return;
            }

            // Si no tiene relaciones, se puede eliminar
            $user->delete();
            $this->resetUI();
            $this->emit('user-deleted', 'Equipo eliminado correctamente');
        } catch (\Exception $e) {
            dd($e); // Útil para depuración
            $this->emit('global-msg', 'Ocurrió un error al intentar eliminar el equipo.');
        }
    }
}
