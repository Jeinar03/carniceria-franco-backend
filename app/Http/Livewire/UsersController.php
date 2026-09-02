<?php
namespace App\Http\Livewire;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;
use Livewire\TemporaryUploadedFile;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

class UsersController extends Component
{

    use WithPagination;
    use WithFileUploads;

    public $name, $phone, $email, $status, $image, $password, $selected_id, $fileLoaded, $profile;
    public $pageTitle, $componentName, $search;
    private $pagination = 3;

    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI'   => 'resetUI',
    ];

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function mount()
    {
        $this->pageTitle     = 'Listado';
        $this->componentName = 'Usuarios';
        $this->status        = 'Elegir';
        $this->profile       = 'Elegir';
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim((string) $this->search);

        $data = User::query()
            ->with('roles')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', '%' . $term . '%')
                        ->orWhere('email', 'like', '%' . $term . '%')
                        ->orWhere('phone', 'like', '%' . $term . '%');
                });
            })
            ->orderBy('name', 'asc')
            ->paginate($this->pagination);

        return view('livewire.users.component', [
            'data'  => $data,
            'roles' => Role::orderBy('name', 'asc')->get(),
        ])
            ->extends('layouts.theme.app')
            ->section('content');
    }

    public function resetUI()
    {
        $this->name        = '';
        $this->email       = '';
        $this->password    = '';
        $this->phone       = '';
        $this->image       = null;
        $this->search      = '';
        $this->status      = 'Elegir';
        $this->profile     = 'Elegir';
        $this->selected_id = 0;
        $this->resetValidation();
        $this->resetPage();
    }

    public function edit(User $user)
    {
        $this->selected_id = $user->id;
        $this->name        = $user->name;
        $this->phone       = $user->phone;
        $this->profile     = $user->getRoleNames()->first() ?? $user->profile;
        $this->status      = strtoupper((string) $user->status);
        $this->email       = $user->email;
        $this->password    = '';
        $this->image       = null;

        $this->emit('show-modal', 'open!');
    }

    public function Store()
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $user = User::create([
                ...$this->userPayload(),
                'password' => Hash::make((string) $this->password),
            ]);

            $user->syncRoles([$this->resolveProfileValue($this->profile)]);

            if ($this->hasNewImage()) {
                $imageName = $this->storeImage();
                $user->update(['image' => $imageName]);
            }

            $this->resetUI();
            $this->emit('user-added', 'Usuario registrado');
        } catch (Throwable $e) {
            Log::error('Error al registrar usuario', [
                'email' => $this->email,
                'profile' => $this->profile,
                'error' => $e->getMessage(),
            ]);

            $this->emit('user-withsales', $this->buildFailureMessage($e, 'registrar'));
        }
    }

    public function Update()
    {
        $this->validate($this->rules($this->selected_id), $this->messages());

        try {
            $user = User::findOrFail($this->selected_id);
            $previousImage = $user->image;

            $payload = $this->userPayload();
            if (! empty($this->password)) {
                $payload['password'] = Hash::make((string) $this->password);
            }

            if ($this->hasNewImage()) {
                $payload['image'] = $this->storeImage();
            }

            $user->update($payload);
            $user->syncRoles([$this->resolveProfileValue($this->profile)]);

            if ($this->hasNewImage() && $previousImage) {
                $this->deleteImage($previousImage);
            }

            $this->resetUI();
            $this->emit('user-updated', 'Usuario actualizado');
        } catch (Throwable $e) {
            Log::error('Error al actualizar usuario', [
                'user_id' => $this->selected_id,
                'email' => $this->email,
                'profile' => $this->profile,
                'error' => $e->getMessage(),
            ]);

            $this->emit('user-withsales', $this->buildFailureMessage($e, 'actualizar'));
        }
    }

    public function destroy(User $user)
    {
        if ($user) {
            if ($user->image) {
                $this->deleteImage($user->image);
            }
            $user->delete();
            $this->resetUI();
            $this->emit('user-deleted', 'Usuario eliminado');
        }
    }

    private function rules(int $ignoreId = 0): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email', 'max:120', Rule::unique('users', 'email')->ignore($ignoreId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'not_in:Elegir', Rule::in(['ACTIVE', 'LOCKED'])],
            'profile' => ['required', 'not_in:Elegir', 'string', 'exists:roles,name'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
            'password' => [$ignoreId > 0 ? 'nullable' : 'required', 'string', 'min:6', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre',
            'name.min' => 'El nombre del usuario debe tener al menos 3 caracteres',
            'email.required' => 'Ingresa el correo',
            'email.email' => 'Ingresa un correo válido',
            'email.unique' => 'El email ya existe en sistema',
            'status.required' => 'Selecciona el estatus del usuario',
            'status.not_in' => 'Selecciona el estatus',
            'status.in' => 'Estatus inválido',
            'profile.required' => 'Selecciona el perfil/role del usuario',
            'profile.not_in' => 'Selecciona un perfil/role distinto a Elegir',
            'profile.exists' => 'El rol seleccionado no existe',
            'image.image' => 'El archivo debe ser una imagen válida',
            'image.mimes' => 'La imagen debe ser JPG, JPEG, PNG, GIF o WEBP',
            'image.max' => 'La imagen no puede superar 2MB',
            'password.required' => 'Ingresa el password',
            'password.min' => 'El password debe tener al menos 6 caracteres',
        ];
    }

    private function userPayload(): array
    {
        return [
            'name' => trim((string) $this->name),
            'email' => Str::lower(trim((string) $this->email)),
            'phone' => $this->normalizeNullableText($this->phone),
            'status' => $this->resolveStatusValue($this->status),
            'profile' => $this->resolveProfileValue($this->profile),
        ];
    }

    private function storeImage(): string
    {
        if (! $this->hasNewImage()) {
            throw new \RuntimeException('No hay imagen válida para almacenar.');
        }

        $fileName = Str::uuid() . '.' . strtolower($this->image->getClientOriginalExtension());
        $this->image->storeAs('users', $fileName, 'public');

        return $fileName;
    }

    private function hasNewImage(): bool
    {
        return $this->image instanceof TemporaryUploadedFile;
    }

    private function deleteImage(string $image): void
    {
        $normalized = ltrim($image, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        $path = Str::contains($normalized, '/') ? $normalized : 'users/' . $normalized;

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function normalizeNullableText($value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function resolveProfileValue($profile)
    {
        $profileName = strtoupper(trim((string) $profile));

        if (in_array($profileName, ['ADMIN', 'ADMINISTRADOR', 'ADMINISTRATOR'], true)) {
            return 'ADMIN';
        }

        return 'EMPLOYEE';
    }

    private function resolveStatusValue($status)
    {
        $statusName = strtoupper(trim((string) $status));

        if ($statusName === 'LOCKED' || $statusName === 'BLOQUEADO') {
            return 'LOCKED';
        }

        return 'ACTIVE';
    }

    private function buildFailureMessage(Throwable $e, string $action): string
    {
        if ($e instanceof QueryException) {
            $errorCode = $e->errorInfo[1] ?? null;

            if ($errorCode === 1062) {
                return "No se pudo {$action} el usuario: el correo ya existe.";
            }

            return "No se pudo {$action} el usuario por un conflicto en base de datos.";
        }

        $message = trim((string) $e->getMessage());

        if ($message === '') {
            return "No se pudo {$action} el usuario.";
        }

        return "No se pudo {$action} el usuario: {$message}";
    }
}
