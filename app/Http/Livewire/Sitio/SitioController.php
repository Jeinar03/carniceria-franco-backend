<?php

namespace App\Http\Livewire\Sitio;

use App\Models\SiteAlert;
use App\Models\SiteConfig;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Throwable;

class SitioController extends Component
{
    use WithPagination, WithFileUploads;

    public $pageTitle     = 'Sitio Web';
    public $componentName = 'Sistema';
    public $activeTab     = 'config';

    // ── Config form ────────────────────────────────────────────────
    public $configId         = null;
    public $configNombre     = '';
    public $configDireccion  = '';
    public $configCorreo     = '';
    public $configTelefono   = '';
    public $configFacebook   = '';
    public $configInstagram  = '';
    public $configWhatsapp   = '';
    public $configLogo       = null;
    public $configLogoActual = null;
    public $configHorarios   = [];
    public $configActiveTab  = 'datos';

    // ── Alert form ─────────────────────────────────────────────────
    public $alertId           = null;
    public $alertTitulo       = '';
    public $alertDescripcion  = '';
    public $alertImagen       = null;
    public $alertImagenActual = null;
    public $alertLinkUrl      = '';
    public $alertLinkTexto    = '';
    public $alertFechaInicio  = '';
    public $alertDiasDuracion = 1;
    public $alertTipo         = 'anuncio';
    public $alertActivo       = true;

    protected $listeners = [
        'deleteConfigConfirmed' => 'deleteConfig',
        'deleteAlertConfirmed'  => 'deleteAlert',
    ];

    public function mount(): void
    {
        $this->resetHorarios();
    }

    public function paginationView(): string
    {
        return 'vendor.livewire.bootstrap';
    }

    public function updatingActiveTab(): void
    {
        $this->resetPage();
        $this->resetPage('alertas_page');
    }

    public function setConfigActiveTab(string $tab): void
    {
        if (in_array($tab, ['datos', 'horarios'], true)) {
            $this->configActiveTab = $tab;
        }
    }

    // ── Horarios ───────────────────────────────────────────────────

    private function resetHorarios(): void
    {
        $this->configHorarios = $this->defaultHorarios();
    }

    private function defaultHorarios(): array
    {
        return [
            'lunes'     => ['abierto' => true,  'apertura' => '09:00', 'cierre' => '18:00'],
            'martes'    => ['abierto' => true,  'apertura' => '09:00', 'cierre' => '18:00'],
            'miercoles' => ['abierto' => true,  'apertura' => '09:00', 'cierre' => '18:00'],
            'jueves'    => ['abierto' => true,  'apertura' => '09:00', 'cierre' => '18:00'],
            'viernes'   => ['abierto' => true,  'apertura' => '09:00', 'cierre' => '18:00'],
            'sabado'    => ['abierto' => true,  'apertura' => '09:00', 'cierre' => '14:00'],
            'domingo'   => ['abierto' => false, 'apertura' => '',      'cierre' => ''],
        ];
    }

    private function normalizeHorarios($horarios): array
    {
        $defaults   = $this->defaultHorarios();
        $normalized = [];

        if (! is_array($horarios)) {
            return $defaults;
        }

        foreach ($defaults as $dia => $defaultData) {
            $diaData = is_array($horarios[$dia] ?? null) ? $horarios[$dia] : [];

            $abierto = filter_var($diaData['abierto'] ?? $defaultData['abierto'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $apertura = (string) ($diaData['apertura'] ?? $defaultData['apertura']);
            $cierre   = (string) ($diaData['cierre'] ?? $defaultData['cierre']);

            $normalized[$dia] = [
                'abierto'  => $abierto ?? false,
                'apertura' => $apertura,
                'cierre'   => $cierre,
            ];
        }

        return $normalized;
    }

    // ── Site Config CRUD ──────────────────────────────────────────

    public function openConfigModal(?int $id = null): void
    {
        $this->resetConfigForm();

        if ($id) {
            $config = SiteConfig::findOrFail($id);

            $this->configId         = $config->id;
            $this->configNombre     = $config->nombre;
            $this->configDireccion  = $config->direccion     ?? '';
            $this->configCorreo     = $config->correo        ?? '';
            $this->configTelefono   = $config->telefono      ?? '';
            $this->configFacebook   = $config->facebook_url  ?? '';
            $this->configInstagram  = $config->instagram_url ?? '';
            $this->configWhatsapp   = $config->whatsapp      ?? '';
            $this->configLogoActual = $config->logo;
            $this->configHorarios   = $this->normalizeHorarios($config->horarios);
        }

        $this->emit('show-config-modal');
    }

    public function saveConfig(): void
    {
        $this->configHorarios = $this->normalizeHorarios($this->configHorarios);

        $rules = [
            'configNombre'    => 'required|string|max:100',
            'configDireccion' => 'nullable|string|max:255',
            'configCorreo'    => 'nullable|email|max:100',
            'configTelefono'  => 'nullable|string|max:30',
            'configFacebook'  => 'nullable|url|max:255',
            'configInstagram' => 'nullable|url|max:255',
            'configWhatsapp'  => 'nullable|string|max:30',
            'configHorarios'  => 'required|array',
            'configHorarios.*.abierto'  => 'required|boolean',
            'configHorarios.*.apertura' => 'nullable|date_format:H:i',
            'configHorarios.*.cierre'   => 'nullable|date_format:H:i',
        ];

        if ($this->configLogo) {
            $rules['configLogo'] = 'image|max:2048';
        }

        $this->validate($rules, [
            'configNombre.required' => 'El nombre de la configuración es obligatorio.',
            'configCorreo.email'    => 'El correo no tiene un formato válido.',
            'configFacebook.url'    => 'La URL de Facebook no es válida.',
            'configInstagram.url'   => 'La URL de Instagram no es válida.',
            'configLogo.image'      => 'El logo debe ser una imagen.',
            'configLogo.max'        => 'El logo no debe exceder 2 MB.',
            'configHorarios.required' => 'Debe definir los horarios de atención.',
            'configHorarios.*.apertura.date_format' => 'La hora de apertura debe tener formato HH:MM.',
            'configHorarios.*.cierre.date_format' => 'La hora de cierre debe tener formato HH:MM.',
        ]);

        try {
            $logoPath = $this->configLogoActual;

            if ($this->configLogo) {
                if ($logoPath) {
                    Storage::disk('public')->delete($logoPath);
                }
                $logoPath = $this->configLogo->store('sitio', 'public');
            }

            $data = [
                'nombre'        => $this->configNombre,
                'direccion'     => $this->configDireccion  ?: null,
                'correo'        => $this->configCorreo     ?: null,
                'telefono'      => $this->configTelefono   ?: null,
                'facebook_url'  => $this->configFacebook   ?: null,
                'instagram_url' => $this->configInstagram  ?: null,
                'whatsapp'      => $this->configWhatsapp   ?: null,
                'logo'          => $logoPath,
                'horarios'      => $this->configHorarios,
            ];

            if ($this->configId) {
                SiteConfig::findOrFail($this->configId)->update($data);
                $this->emit('sitio-success', 'Configuración actualizada correctamente.');
            } else {
                SiteConfig::create($data);
                $this->emit('sitio-success', 'Configuración creada correctamente.');
            }

            $this->emit('hide-config-modal');
            $this->resetConfigForm();
        } catch (Throwable $e) {
            $this->emit('sitio-error', 'Error al guardar la configuración: ' . $e->getMessage());
        }
    }

    public function activateConfig(int $id): void
    {
        try {
            SiteConfig::query()->update(['activo' => false]);
            SiteConfig::findOrFail($id)->update(['activo' => true]);
            $this->emit('sitio-success', 'Configuración activada correctamente.');
        } catch (Throwable $e) {
            $this->emit('sitio-error', 'Error al activar: ' . $e->getMessage());
        }
    }

    public function confirmDeleteConfig(int $id): void
    {
        $this->emit('confirm-delete-config', $id);
    }

    public function deleteConfig(int $id): void
    {
        try {
            $config = SiteConfig::findOrFail($id);

            if ($config->activo) {
                $this->emit('sitio-error', 'No se puede eliminar la configuración activa.');
                return;
            }

            if ($config->logo) {
                Storage::disk('public')->delete($config->logo);
            }

            $config->delete();
            $this->emit('sitio-success', 'Configuración eliminada correctamente.');
        } catch (Throwable $e) {
            $this->emit('sitio-error', 'Error al eliminar: ' . $e->getMessage());
        }
    }

    private function resetConfigForm(): void
    {
        $this->configId         = null;
        $this->configNombre     = '';
        $this->configDireccion  = '';
        $this->configCorreo     = '';
        $this->configTelefono   = '';
        $this->configFacebook   = '';
        $this->configInstagram  = '';
        $this->configWhatsapp   = '';
        $this->configLogo       = null;
        $this->configLogoActual = null;
        $this->configActiveTab  = 'datos';
        $this->resetHorarios();
        $this->resetValidation();
    }

    // ── Site Alerts CRUD ──────────────────────────────────────────

    public function openAlertModal(?int $id = null): void
    {
        $this->resetAlertForm();

        if ($id) {
            $alert = SiteAlert::findOrFail($id);

            $this->alertId           = $alert->id;
            $this->alertTitulo       = $alert->titulo;
            $this->alertDescripcion  = $alert->descripcion ?? '';
            $this->alertImagenActual = $alert->imagen;
            $this->alertLinkUrl      = $alert->link_url    ?? '';
            $this->alertLinkTexto    = $alert->link_texto  ?? '';
            $this->alertFechaInicio  = $alert->fecha_inicio
                ? $alert->fecha_inicio->format('Y-m-d\TH:i')
                : '';
            $this->alertDiasDuracion = $alert->dias_duracion;
            $this->alertTipo         = $alert->tipo;
            $this->alertActivo       = (bool) $alert->activo;
        }

        $this->emit('show-alert-modal');
    }

    public function saveAlert(): void
    {
        $rules = [
            'alertTitulo'       => 'required|string|max:150',
            'alertDescripcion'  => 'nullable|string',
            'alertLinkUrl'      => 'nullable|url|max:255',
            'alertLinkTexto'    => 'nullable|string|max:100',
            'alertFechaInicio'  => 'nullable|date',
            'alertDiasDuracion' => 'required|integer|min:1',
            'alertTipo'         => 'required|in:oferta,alerta,novedad,anuncio',
        ];

        if ($this->alertImagen) {
            $rules['alertImagen'] = 'image|max:2048';
        }

        $this->validate($rules, [
            'alertTitulo.required'  => 'El título es obligatorio.',
            'alertLinkUrl.url'      => 'El enlace no tiene un formato válido.',
            'alertDiasDuracion.min' => 'La duración mínima es 1 día.',
            'alertTipo.in'          => 'El tipo seleccionado no es válido.',
            'alertImagen.image'     => 'El archivo debe ser una imagen.',
            'alertImagen.max'       => 'La imagen no debe exceder 2 MB.',
        ]);

        try {
            $imagenPath = $this->alertImagenActual;

            if ($this->alertImagen) {
                if ($imagenPath) {
                    Storage::disk('public')->delete($imagenPath);
                }
                $imagenPath = $this->alertImagen->store('sitio/alertas', 'public');
            }

            $data = [
                'titulo'        => $this->alertTitulo,
                'descripcion'   => $this->alertDescripcion  ?: null,
                'imagen'        => $imagenPath,
                'link_url'      => $this->alertLinkUrl       ?: null,
                'link_texto'    => $this->alertLinkTexto     ?: null,
                'fecha_inicio'  => $this->alertFechaInicio   ?: null,
                'dias_duracion' => (int) $this->alertDiasDuracion,
                'tipo'          => $this->alertTipo,
                'activo'        => $this->alertActivo,
            ];

            if ($this->alertId) {
                SiteAlert::findOrFail($this->alertId)->update($data);
                $this->emit('sitio-success', 'Alerta actualizada correctamente.');
            } else {
                SiteAlert::create($data);
                $this->emit('sitio-success', 'Alerta creada correctamente.');
            }

            $this->emit('hide-alert-modal');
            $this->resetAlertForm();
        } catch (Throwable $e) {
            $this->emit('sitio-error', 'Error al guardar la alerta: ' . $e->getMessage());
        }
    }

    public function toggleAlertActivo(int $id): void
    {
        try {
            $alert         = SiteAlert::findOrFail($id);
            $alert->activo = ! $alert->activo;
            $alert->save();
            $this->emit('sitio-success', 'Estado de la alerta actualizado.');
        } catch (Throwable $e) {
            $this->emit('sitio-error', 'Error al actualizar estado: ' . $e->getMessage());
        }
    }

    public function confirmDeleteAlert(int $id): void
    {
        $this->emit('confirm-delete-alert', $id);
    }

    public function deleteAlert(int $id): void
    {
        try {
            $alert = SiteAlert::findOrFail($id);

            if ($alert->imagen) {
                Storage::disk('public')->delete($alert->imagen);
            }

            $alert->delete();
            $this->emit('sitio-success', 'Alerta eliminada correctamente.');
        } catch (Throwable $e) {
            $this->emit('sitio-error', 'Error al eliminar: ' . $e->getMessage());
        }
    }

    private function resetAlertForm(): void
    {
        $this->alertId           = null;
        $this->alertTitulo       = '';
        $this->alertDescripcion  = '';
        $this->alertImagen       = null;
        $this->alertImagenActual = null;
        $this->alertLinkUrl      = '';
        $this->alertLinkTexto    = '';
        $this->alertFechaInicio  = '';
        $this->alertDiasDuracion = 1;
        $this->alertTipo         = 'anuncio';
        $this->alertActivo       = true;
        $this->resetValidation();
    }

    // ── Render ─────────────────────────────────────────────────────

    public function render()
    {
        $configs = SiteConfig::orderByDesc('activo')
            ->orderByDesc('created_at')
            ->paginate(10);

        $alertas = SiteAlert::orderByDesc('created_at')
            ->paginate(10, ['*'], 'alertas_page');

        return view('livewire.sitio.sitio-controller', compact('configs', 'alertas'))
            ->extends('layouts.theme.app')
            ->section('content');
    }
}
