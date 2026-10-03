<?php

namespace App\Http\Livewire\MercadoPago;

use App\Models\MercadoPagoSetting;
use Illuminate\Support\Facades\Http;
use Livewire\Component;
use Throwable;

/**
 * Sistema → Mercado Pago. Guarda varias configuraciones de credenciales (prueba y producción)
 * y deja elegir cuál está en uso, sin volver a capturar nada. Solo una puede estar activa.
 */
class MercadoPagoController extends Component
{
    public $pageTitle = 'Mercado Pago';
    public $componentName = 'Sistema';
    public $name = 'Configuracion principal';
    public $accessToken = '';
    public $publicKey = '';
    public $webhookSecret = '';
    // 1 = Prueba, 0 = Producción (entero porque se enlaza a un <select>; un booleano no empata con sus opciones).
    public $sandbox = 1;
    public $active = true;
    public $accessTokenMasked = 'No configurada';
    public $publicKeyMasked = 'No configurada';
    public $webhookSecretMasked = 'No configurada';
    public $settingId = null;

    public function mount(): void
    {
        $this->loadSetting();
    }

    public function render()
    {
        return view('livewire.mercado-pago.mercado-pago-controller', [
            'settings' => MercadoPagoSetting::orderByDesc('active')->orderBy('id')->get(),
            'enUso' => MercadoPagoSetting::active(),
        ])
            ->extends('layouts.theme.app')
            ->section('content');
    }

    /** Limpia el formulario para capturar una configuración nueva (no toca las guardadas). */
    public function newSetting(): void
    {
        $this->resetValidation();
        $this->settingId = null;
        $this->name = '';
        $this->accessToken = '';
        $this->publicKey = '';
        $this->webhookSecret = '';
        $this->sandbox = 1;
        $this->active = false;
        $this->accessTokenMasked = 'No configurada';
        $this->publicKeyMasked = 'No configurada';
        $this->webhookSecretMasked = 'No configurada';
    }

    public function edit(int $id): void
    {
        $setting = MercadoPagoSetting::find($id);

        if ($setting) {
            $this->resetValidation();
            $this->fillFrom($setting);
        }
    }

    /** Pone en uso una configuración guardada: las demás quedan inactivas. */
    public function activate(int $id): void
    {
        $setting = MercadoPagoSetting::find($id);

        if (! $setting) {
            return;
        }

        MercadoPagoSetting::where('id', '!=', $setting->id)->update(['active' => false]);
        $setting->update(['active' => true]);

        if ($this->settingId === $setting->id) {
            $this->fillFrom($setting->fresh());
        }

        $tipo = $setting->sandbox ? 'de PRUEBA (los pagos no son reales)' : 'de PRODUCCION (se cobra dinero real)';
        [$conectado, $mensaje] = $this->verifyConnection($setting);

        $this->emit(
            $conectado ? 'mercadopago-success' : 'mercadopago-error',
            "En uso: {$setting->name}, {$tipo}. " . ($conectado ? $mensaje : 'Ojo: ' . $mensaje)
        );
    }

    public function deleteSetting(int $id): void
    {
        $setting = MercadoPagoSetting::find($id);

        if (! $setting) {
            return;
        }

        if ($setting->active) {
            $this->emit('mercadopago-error', 'No se puede eliminar la configuracion en uso. Activa otra primero.');
            return;
        }

        $setting->delete();

        if ($this->settingId === $id) {
            $this->settingId = null;
            $this->loadSetting();
        }

        $this->emit('mercadopago-success', 'Configuracion eliminada.');
    }

    public function save(): void
    {
        $setting = $this->settingId ? MercadoPagoSetting::find($this->settingId) : null;

        $requiresCredentials = ! $setting;

        $this->validate([
            'name' => 'required|string|max:100',
            'accessToken' => ($requiresCredentials ? 'required' : 'nullable') . '|string|min:20',
            'publicKey' => ($requiresCredentials ? 'required' : 'nullable') . '|string|min:20',
            'sandbox' => 'required|boolean',
        ], [
            'name.required' => 'Ingresa el nombre de la configuracion.',
            'accessToken.required' => 'Ingresa el Access Token de Mercado Pago.',
            'accessToken.min' => 'El Access Token parece demasiado corto.',
            'publicKey.required' => 'Ingresa la Public Key de Mercado Pago.',
            'publicKey.min' => 'La Public Key parece demasiado corta.',
        ]);

        try {
            $data = [
                'name' => trim($this->name),
                'sandbox' => (bool) $this->sandbox,
            ];

            if (trim($this->accessToken) !== '') {
                $data['access_token'] = trim($this->accessToken);
            }

            if (trim($this->publicKey) !== '') {
                $data['public_key'] = trim($this->publicKey);
            }

            if (trim($this->webhookSecret) !== '') {
                $data['webhook_secret'] = trim($this->webhookSecret);
            }

            $esNueva = ! $setting;

            if ($setting) {
                $setting->update($data);
            } else {
                // La primera configuración queda en uso; las siguientes se activan a mano con "Usar".
                $data['active'] = ! MercadoPagoSetting::exists();
                $setting = MercadoPagoSetting::create($data);
            }

            $this->fillFrom($setting->fresh());

            [$conectado, $mensaje] = $this->verifyConnection($setting);

            if ($conectado && $esNueva && ! $setting->active) {
                $mensaje .= ' Todavia no esta en uso: presiona "Usar" en la lista para activarla.';
            }

            $this->emit($conectado ? 'mercadopago-success' : 'mercadopago-error', $mensaje);
        } catch (Throwable $e) {
            $this->emit('mercadopago-error', 'Error al guardar credenciales: ' . $e->getMessage());
        }
    }

    /**
     * Comprueba contra Mercado Pago que el Access Token guardado sea válido, para no
     * enterarse hasta el checkout de un token mal copiado. Las credenciales se guardan
     * de todos modos; esto solo avisa.
     *
     * @return array{0: bool, 1: string}
     */
    private function verifyConnection(MercadoPagoSetting $setting): array
    {
        if (! $setting->access_token) {
            return [false, 'Configuración guardada, pero falta el Access Token.'];
        }

        try {
            $response = Http::timeout(8)
                ->withToken($setting->access_token)
                ->get('https://api.mercadopago.com/users/me');
        } catch (Throwable $e) {
            return [false, 'Credenciales guardadas, pero no se pudo comprobar la conexión con Mercado Pago (sin respuesta).'];
        }

        if (in_array($response->status(), [401, 403], true)) {
            return [false, 'Credenciales guardadas, pero Mercado Pago rechazó el Access Token. Revisa que esté completo y vigente.'];
        }

        if (! $response->successful()) {
            return [false, 'Credenciales guardadas, pero Mercado Pago respondió con error ' . $response->status() . '.'];
        }

        $cuenta = $response->json('nickname') ?: 'tu cuenta';
        $sitio = $response->json('site_id');

        return [true, 'Credenciales guardadas. Conectado a Mercado Pago como ' . $cuenta . ($sitio ? " ({$sitio})" : '') . '.'];
    }

    private function loadSetting(): void
    {
        $setting = MercadoPagoSetting::active() ?: MercadoPagoSetting::latest()->first();

        if ($setting) {
            $this->fillFrom($setting);
        }
    }

    /** Carga una configuración en el formulario. Los campos secretos quedan vacíos (solo se muestran enmascarados). */
    private function fillFrom(MercadoPagoSetting $setting): void
    {
        $this->settingId = $setting->id;
        $this->name = $setting->name;
        $this->sandbox = $setting->sandbox ? 1 : 0;
        $this->active = (bool) $setting->active;
        $this->accessToken = '';
        $this->publicKey = '';
        $this->webhookSecret = '';
        $this->accessTokenMasked = MercadoPagoSetting::mask($setting->access_token);
        $this->publicKeyMasked = MercadoPagoSetting::mask($setting->public_key);
        $this->webhookSecretMasked = MercadoPagoSetting::mask($setting->webhook_secret);
    }
}
