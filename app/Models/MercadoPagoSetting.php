<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class MercadoPagoSetting extends Model
{
    use HasFactory;

    protected $table = 'mercado_pago_settings';

    protected $fillable = [
        'name',
        'access_token',
        'public_key',
        'sandbox',
        'active',
    ];

    protected $casts = [
        'sandbox' => 'boolean',
        'active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'public_key',
    ];

    public function setAccessTokenAttribute($value): void
    {
        $this->attributes['access_token'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAccessTokenAttribute($value): ?string
    {
        return $this->decryptCredential($value);
    }

    public function setPublicKeyAttribute($value): void
    {
        $this->attributes['public_key'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getPublicKeyAttribute($value): ?string
    {
        return $this->decryptCredential($value);
    }

    public static function active(): ?self
    {
        return self::where('active', true)->latest()->first();
    }

    public static function credentials(): array
    {
        $setting = self::active();

        return [
            'access_token' => $setting && $setting->access_token
                ? $setting->access_token
                : config('mercadopago.access_token'),
            'public_key' => $setting && $setting->public_key
                ? $setting->public_key
                : config('mercadopago.public_key'),
            'sandbox' => $setting
                ? (bool) $setting->sandbox
                : (bool) config('mercadopago.sandbox'),
        ];
    }

    public static function mask(?string $value): string
    {
        if (! $value) {
            return 'No configurada';
        }

        $length = strlen($value);

        if ($length <= 10) {
            return str_repeat('*', $length);
        }

        return substr($value, 0, 6) . str_repeat('*', max(6, $length - 10)) . substr($value, -4);
    }

    private function decryptCredential($value): ?string
    {
        if (! $value) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value;
        }
    }
}
