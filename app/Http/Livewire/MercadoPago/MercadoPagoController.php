<?php

namespace App\Http\Livewire\MercadoPago;

use App\Models\MercadoPagoSetting;
use Livewire\Component;
use Throwable;

class MercadoPagoController extends Component
{
    public $pageTitle = 'Mercado Pago';
    public $componentName = 'Sistema';
    public $name = 'Configuracion principal';
    public $accessToken = '';
    public $publicKey = '';
    public $sandbox = true;
    public $active = true;
    public $accessTokenMasked = 'No configurada';
    public $publicKeyMasked = 'No configurada';
    public $settingId = null;

    public function mount(): void
    {
        $this->loadSetting();
    }

    public function render()
    {
        return view('livewire.mercado-pago.mercado-pago-controller')
            ->extends('layouts.theme.app')
            ->section('content');
    }

    public function save(): void
    {
        $setting = $this->settingId
            ? MercadoPagoSetting::find($this->settingId)
            : MercadoPagoSetting::active();

        $requiresCredentials = ! $setting;

        $this->validate([
            'name' => 'required|string|max:100',
            'accessToken' => ($requiresCredentials ? 'required' : 'nullable') . '|string|min:20',
            'publicKey' => ($requiresCredentials ? 'required' : 'nullable') . '|string|min:20',
            'sandbox' => 'required|boolean',
            'active' => 'required|boolean',
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
                'active' => (bool) $this->active,
            ];

            if (trim($this->accessToken) !== '') {
                $data['access_token'] = trim($this->accessToken);
            }

            if (trim($this->publicKey) !== '') {
                $data['public_key'] = trim($this->publicKey);
            }

            if ($data['active']) {
                MercadoPagoSetting::query()
                    ->when($setting, fn ($query) => $query->where('id', '!=', $setting->id))
                    ->update(['active' => false]);
            }

            if ($setting) {
                $setting->update($data);
            } else {
                $setting = MercadoPagoSetting::create($data);
            }

            $this->settingId = $setting->id;
            $this->accessToken = '';
            $this->publicKey = '';
            $this->loadSetting();

            $this->emit('mercadopago-success', 'Credenciales guardadas correctamente.');
        } catch (Throwable $e) {
            $this->emit('mercadopago-error', 'Error al guardar credenciales: ' . $e->getMessage());
        }
    }

    private function loadSetting(): void
    {
        $setting = MercadoPagoSetting::active() ?: MercadoPagoSetting::latest()->first();

        if (! $setting) {
            return;
        }

        $this->settingId = $setting->id;
        $this->name = $setting->name;
        $this->sandbox = (bool) $setting->sandbox;
        $this->active = (bool) $setting->active;
        $this->accessTokenMasked = MercadoPagoSetting::mask($setting->access_token);
        $this->publicKeyMasked = MercadoPagoSetting::mask($setting->public_key);
    }
}
