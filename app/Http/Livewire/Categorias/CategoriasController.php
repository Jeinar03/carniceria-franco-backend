<?php

namespace App\Http\Livewire\Categorias;

use Livewire\Component;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class CategoriasController extends Component
{
    use WithPagination;
    use WithFileUploads;

    public $pageTitle, $componentName;
    private $pagination = 10;
    public $nombre;
    public $descripcion;
    public $imagen;
    public $activo = 1;
    public $orden = 0;
    public $selected_id = 0;
    public $search;

    public function mount()
    {
        $this->pageTitle = 'Listado';
        $this->componentName = 'Categorías';
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function render()
    {
        $term = trim((string) $this->search);

        $data = Category::query()
            ->select(['id', 'nombre', 'descripcion', 'imagen', 'activo', 'orden'])
            ->withCount('products')
            ->when($term !== '', function ($query) use ($term) {
                return $query->where('nombre', 'like', '%' . $term . '%');
            })
            ->orderBy('orden', 'asc')
            ->paginate($this->pagination);

        return view('livewire.categorias.categorias-controller', ['data' => $data])
            ->extends('layouts.theme.app')
            ->section('content');
    }

    public function resetUI()
    {
        $this->nombre = '';
        $this->descripcion = '';
        $this->imagen = null;
        $this->activo = 1;
        $this->orden = 0;
        $this->selected_id = 0;
        $this->resetValidation();
        $this->resetPage();
    }

    public function edit(Category $category)
    {
        $this->selected_id = $category->id;
        $this->nombre = $category->nombre;
        $this->descripcion = $category->descripcion;
        $this->activo = $category->activo;
        $this->orden = $category->orden;
        $this->emit('show-modal', 'open!');
    }

    public function Store()
    {
        $this->validate($this->rules(), $this->messages());

        try {
            $imageName = $this->imagen ? $this->storeImage() : null;

            Category::create([
                'nombre' => trim($this->nombre),
                'descripcion' => $this->descripcion,
                'imagen' => $imageName,
                'activo' => (bool) $this->activo,
                'orden' => (int) $this->orden,
            ]);

            $this->resetUI();
            $this->emit('category-added', 'Categoría registrada');
        } catch (\Throwable $e) {
            $this->emit('category-error', 'No se pudo registrar la categoría.');
        }
    }

    public function Update()
    {
        $this->validate($this->rules($this->selected_id), $this->messages());

        try {
            $category = Category::findOrFail($this->selected_id);
            $previousImage = $category->imagen;
            $imageName = $previousImage;

            if ($this->imagen) {
                $imageName = $this->storeImage();
            }

            $category->update([
                'nombre' => trim($this->nombre),
                'descripcion' => $this->descripcion,
                'imagen' => $imageName,
                'activo' => (bool) $this->activo,
                'orden' => (int) $this->orden,
            ]);

            if ($this->imagen && $previousImage) {
                $this->deleteImage($previousImage);
            }

            $this->resetUI();
            $this->emit('category-updated', 'Categoría actualizada');
        } catch (\Throwable $e) {
            $this->emit('category-error', 'No se pudo actualizar la categoría.');
        }
    }

    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI' => 'resetUI',
    ];

    public function destroy(Category $category)
    {
        try {
            if ($category->products()->exists()) {
                $this->emit('category-error', 'No se puede eliminar. Tiene productos asociados.');
                return;
            }

            $image = $category->imagen;

            $category->delete();

            if ($image) {
                $this->deleteImage($image);
            }

            $this->resetUI();
            $this->emit('category-deleted', 'Categoría eliminada con éxito');
        } catch (\Throwable $e) {
            $this->emit('category-error', 'No se pudo eliminar la categoría.');
        }
    }

    private function rules(int $ignoreId = 0): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:100', Rule::unique('categories', 'nombre')->ignore($ignoreId)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048'],
            'activo' => ['required', 'boolean'],
            'orden' => ['required', 'integer', 'min:0'],
        ];
    }

    private function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa el nombre de la categoría',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres',
            'nombre.max' => 'El nombre no puede superar 100 caracteres',
            'nombre.unique' => 'Esta categoría ya existe',
            'descripcion.max' => 'La descripción no puede superar 255 caracteres',
            'imagen.image' => 'El archivo debe ser una imagen válida',
            'imagen.mimes' => 'La imagen debe ser JPG, JPEG, PNG, WEBP o GIF',
            'imagen.max' => 'La imagen no puede superar 2MB',
            'activo.required' => 'Selecciona el estado',
            'orden.required' => 'Ingresa el orden',
            'orden.integer' => 'El orden debe ser un número',
        ];
    }

    private function storeImage(): string
    {
        $extension = strtolower($this->imagen->getClientOriginalExtension());
        $fileName = Str::uuid() . '.' . $extension;

        $this->imagen->storeAs('categories', $fileName, 'public');

        return $fileName;
    }

    private function deleteImage(string $image): void
    {
        $normalized = ltrim($image, '/');

        if (Str::startsWith($normalized, 'storage/')) {
            $normalized = Str::after($normalized, 'storage/');
        }

        $paths = [];

        if (Str::contains($normalized, '/')) {
            $paths[] = $normalized;
        } else {
            $paths[] = 'categories/' . $normalized;
            $paths[] = 'categorias/' . $normalized;
        }

        foreach (array_unique($paths) as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            // Compatibilidad con archivos antiguos guardados directo en /public
            $legacyPath = public_path($path);
            if (file_exists($legacyPath)) {
                @unlink($legacyPath);
            }
        }
    }
}
