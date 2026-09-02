<?php

namespace App\Http\Livewire\Clientes;

use Livewire\Component;
use App\Models\Customers;
use App\Models\Sale;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\WithPagination;

class ClientesController extends Component
{
    use WithPagination;

    public $pageTitle, $componentName;
    private $pagination = 5;
    public $nombre;
    public $apellido;
    public $apellido2;
    public $correo;
    public $telefono;
    public $direccion;
    public $ciudad;
    public $estado;
    public $codigo_postal;
    public $pais = 'México';
    public $rfc;
    public $tipo_cliente = 'minorista';
    public $estatus = 'activo';
    public $descuento_preferencial = 0;
    public $notas;

    public $selected_id = 0;
    public $search;
    public $profile;
    public $roles;

    public function buscar()
    {
        $this->resetPage();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatedTipoCliente($value)
    {
        if ($value !== 'mayorista') {
            $this->descuento_preferencial = 0;
            $this->resetValidation('descuento_preferencial');
        }
    }

    public function mount()
    {
        $this->pageTitle = 'Listado';
        $this->componentName = 'Clientes';
    }
    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function render()
    {
        $term = trim((string) $this->search);

        $query = Customers::query();

        if ($term !== '') {
            $query->where(function ($q) use ($term) {
                $q->where('correo', 'like', '%' . $term . '%')
                    ->orWhere('nombre', 'like', '%' . $term . '%')
                    ->orWhere('apellido', 'like', '%' . $term . '%')
                    ->orWhere('telefono', 'like', '%' . $term . '%')
                    ->orWhere('rfc', 'like', '%' . $term . '%');
            });
        }

        return view('livewire.clientes.clientes-controller', [
            'data' => $query->latest('id')->paginate($this->pagination),
        ])
            ->extends('layouts.theme.app')
            ->section('content');
    }


    public function resetUI()
    {
        $this->nombre = '';
        $this->apellido = '';
        $this->apellido2 = '';
        $this->correo = '';
        $this->telefono = '';
        $this->direccion = '';
        $this->ciudad = '';
        $this->estado = '';
        $this->codigo_postal = '';
        $this->pais = 'México';
        $this->rfc = '';
        $this->tipo_cliente = 'minorista';
        $this->estatus = 'activo';
        $this->descuento_preferencial = 0;
        $this->notas = '';

        $this->selected_id = 0;
        $this->resetValidation();
        $this->resetPage();
    }
    public function edit(Customers $user)
    {
        $this->selected_id = $user->id;
        $this->nombre = $user->nombre;
        $this->apellido = $user->apellido;
        $this->apellido2 = $user->apellido2;
        $this->correo = $user->correo;
        $this->telefono = $user->telefono;
        $this->direccion = $user->direccion;
        $this->ciudad = $user->ciudad;
        $this->estado = $user->estado;
        $this->codigo_postal = $user->codigo_postal;
        $this->pais = $user->pais;
        $this->rfc = $user->rfc;
        $this->tipo_cliente = $user->tipo_cliente;
        $this->estatus = $user->estatus;
        $this->descuento_preferencial = $user->descuento_preferencial;
        $this->notas = $user->notas;
        $this->emit('show-modal', 'open!');
    }
    public function Store()
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $payload = $this->customerPayload();
            $payload['password'] = Hash::make('defaultpassword');

            Customers::create($payload);

            $this->resetUI();
            $this->emit('user-added', 'Cliente registrado');
        } catch (\Throwable $e) {
            $this->emit('global-msg', 'No se pudo registrar el cliente.');
        }
    }

    public function Update()
    {
        try {
            $user = Customers::findOrFail($this->selected_id);

            $this->validate($this->rules($this->selected_id), $this->messages());

            $payload = $this->customerPayload();

            if (! empty($this->password)) {
                $payload['password'] = Hash::make($this->password);
            }

            $user->update($payload);

            $this->resetUI();
            $this->emit('global-msg', 'Cliente actualizado');
            $this->emit('user-updated', 'Cliente actualizado');
        } catch (\Throwable $e) {
            $this->emit('global-msg', 'No se pudo actualizar el cliente.');
        }
    }

    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI' => 'resetUI',

    ];

    public function destroy(Customers $user)
    {
        try {
            $hasSales = Sale::where('customer_id', $user->id)->exists();

            if ($hasSales) {
                $user->update(['estatus' => 'inactivo']);
                $this->emit('user-deleted', 'Cliente inactivado (tiene ventas relacionadas)');
            } else {
                $user->delete();
                $this->emit('user-deleted', 'Cliente eliminado correctamente');
            }

            $this->resetUI();
        } catch (\Throwable $e) {
            $this->emit('global-msg', 'No se pudo eliminar o inactivar el cliente.');
        }
    }

    private function rules(int $ignoreId = 0): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
            'apellido' => ['required', 'string', 'min:3', 'max:100'],
            'apellido2' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:120', Rule::unique('customers', 'correo')->ignore($ignoreId)],
            'telefono' => ['nullable', 'string', 'min:10', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'string', 'max:100'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'pais' => ['nullable', 'string', 'max:100'],
            'rfc' => ['nullable', 'string', 'max:20'],
            'tipo_cliente' => ['required', Rule::in(['minorista', 'mayorista', 'distribuidor'])],
            'estatus' => ['required', Rule::in(['activo', 'inactivo', 'suspendido'])],
            'descuento_preferencial' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres',
            'apellido.required' => 'Ingresa el apellido',
            'apellido.min' => 'El apellido debe tener al menos 3 caracteres',
            'correo.required' => 'Ingresa el correo',
            'correo.email' => 'Ingresa un correo válido',
            'correo.unique' => 'El email ya existe en el sistema',
            'telefono.min' => 'El teléfono debe tener al menos 10 caracteres',
            'tipo_cliente.required' => 'Selecciona el tipo de cliente',
            'estatus.required' => 'Selecciona el estatus',
            'descuento_preferencial.max' => 'El descuento no puede ser mayor a 100%',
        ];
    }

    private function customerPayload(): array
    {
        return [
            'nombre' => trim((string) $this->nombre),
            'apellido' => trim((string) $this->apellido),
            'apellido2' => $this->normalizeNullableText($this->apellido2),
            'correo' => Str::lower(trim((string) $this->correo)),
            'telefono' => $this->normalizeNullableText($this->telefono),
            'direccion' => $this->normalizeNullableText($this->direccion),
            'ciudad' => $this->normalizeNullableText($this->ciudad),
            'estado' => $this->normalizeNullableText($this->estado),
            'codigo_postal' => $this->normalizeNullableText($this->codigo_postal),
            'pais' => $this->normalizeNullableText($this->pais) ?: 'México',
            'rfc' => $this->normalizeNullableText($this->rfc),
            'tipo_cliente' => $this->tipo_cliente,
            'estatus' => $this->estatus,
            'descuento_preferencial' => $this->tipo_cliente === 'mayorista'
                ? ($this->descuento_preferencial !== null && $this->descuento_preferencial !== '' ? (float) $this->descuento_preferencial : 0)
                : 0,
            'notas' => $this->normalizeNullableText($this->notas),
        ];
    }

    private function normalizeNullableText($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
