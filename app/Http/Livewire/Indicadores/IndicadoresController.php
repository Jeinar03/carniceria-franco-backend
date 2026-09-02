<?php

namespace App\Http\Livewire\Indicadores;

use App\Models\IndicadorPregunta;
use App\Models\IndicadorRespuesta;
use Livewire\Component;
use Livewire\WithPagination;

class IndicadoresController extends Component
{
    use WithPagination;

    public $pageTitle = 'Preguntas de satisfaccion';
    public $componentName = 'Indicadores';
    public $pregunta = '';
    public $descripcion = '';
    public $activo = 1;
    public $mostrar_al_finalizar_pedido = 1;
    public $orden = 0;
    public $selected_id = 0;
    public $search = '';

    private $pagination = 10;

    protected $listeners = [
        'deleteRow' => 'destroy',
        'resetUI' => 'resetUI',
    ];

    public function paginationView()
    {
        return 'vendor.livewire.bootstrap';
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim((string) $this->search);

        $preguntas = IndicadorPregunta::query()
            ->withCount('respuestas')
            ->selectSub(function ($query) {
                $query->from('indicador_respuestas')
                    ->selectRaw('AVG(respuesta)')
                    ->whereColumn('indicador_respuestas.pregunta_id', 'indicador_preguntas.id');
            }, 'promedio_respuestas')
            ->when($term !== '', function ($query) use ($term) {
                $query->where('pregunta', 'like', '%' . $term . '%')
                    ->orWhere('descripcion', 'like', '%' . $term . '%');
            })
            ->orderBy('orden')
            ->orderBy('id')
            ->paginate($this->pagination);

        $resumen = [
            'preguntas_activas' => IndicadorPregunta::where('activo', true)->count(),
            'preguntas_finalizar' => IndicadorPregunta::where('activo', true)->where('mostrar_al_finalizar_pedido', true)->count(),
            'respuestas_total' => IndicadorRespuesta::count(),
            'promedio_general' => IndicadorRespuesta::avg('respuesta'),
        ];

        return view('livewire.indicadores.indicadores-controller', [
            'preguntas' => $preguntas,
            'resumen' => $resumen,
        ])->extends('layouts.theme.app')
            ->section('content');
    }

    public function resetUI()
    {
        $this->pregunta = '';
        $this->descripcion = '';
        $this->activo = 1;
        $this->mostrar_al_finalizar_pedido = 1;
        $this->orden = 0;
        $this->selected_id = 0;
        $this->resetValidation();
    }

    public function edit(IndicadorPregunta $pregunta)
    {
        $this->selected_id = $pregunta->id;
        $this->pregunta = $pregunta->pregunta;
        $this->descripcion = $pregunta->descripcion ?? '';
        $this->activo = $pregunta->activo ? 1 : 0;
        $this->mostrar_al_finalizar_pedido = $pregunta->mostrar_al_finalizar_pedido ? 1 : 0;
        $this->orden = $pregunta->orden;

        $this->emit('show-modal');
    }

    public function Store()
    {
        $this->validate($this->rules(), $this->messages());

        IndicadorPregunta::create($this->payload());

        $this->resetUI();
        $this->emit('indicador-added', 'Pregunta registrada correctamente');
    }

    public function Update()
    {
        $this->validate($this->rules(), $this->messages());

        IndicadorPregunta::findOrFail($this->selected_id)->update($this->payload());

        $this->resetUI();
        $this->emit('indicador-updated', 'Pregunta actualizada correctamente');
    }

    public function toggleActivo(int $id)
    {
        $pregunta = IndicadorPregunta::findOrFail($id);
        $pregunta->activo = ! $pregunta->activo;
        $pregunta->save();

        $this->emit('indicador-updated', 'Estado actualizado correctamente');
    }

    public function destroy(IndicadorPregunta $pregunta)
    {
        $pregunta->delete();
        $this->resetUI();
        $this->emit('indicador-deleted', 'Pregunta eliminada correctamente');
    }

    private function payload(): array
    {
        return [
            'pregunta' => trim($this->pregunta),
            'descripcion' => trim((string) $this->descripcion) ?: null,
            'activo' => (bool) $this->activo,
            'mostrar_al_finalizar_pedido' => (bool) $this->mostrar_al_finalizar_pedido,
            'orden' => (int) $this->orden,
        ];
    }

    private function rules(): array
    {
        return [
            'pregunta' => 'required|string|min:10|max:1000',
            'descripcion' => 'nullable|string|max:1000',
            'activo' => 'required|boolean',
            'mostrar_al_finalizar_pedido' => 'required|boolean',
            'orden' => 'required|integer|min:0',
        ];
    }

    private function messages(): array
    {
        return [
            'pregunta.required' => 'Ingresa la pregunta',
            'pregunta.min' => 'La pregunta debe tener al menos 10 caracteres',
            'pregunta.max' => 'La pregunta no puede superar 1000 caracteres',
            'descripcion.max' => 'La descripcion no puede superar 1000 caracteres',
            'orden.required' => 'Ingresa el orden',
            'orden.integer' => 'El orden debe ser numerico',
        ];
    }
}
