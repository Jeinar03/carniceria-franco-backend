<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage; // usado para delete en el controlador

class SiteConfig extends Model
{
    use HasFactory;

    protected $table = 'site_configs';

    protected $fillable = [
        'nombre',
        'logo',
        'direccion',
        'correo',
        'telefono',
        'facebook_url',
        'instagram_url',
        'whatsapp',
        'banco',
        'titular_cuenta',
        'numero_cuenta',
        'clabe',
        'horarios',
        'limitar_horario',
        'activo',
    ];

    protected $casts = [
        'horarios'        => 'array',
        'limitar_horario' => 'boolean',
        'activo'          => 'boolean',
    ];

    /** Configuracion activa del sitio. Si por error hay varias, gana la editada mas recientemente. */
    public static function activa(): ?self
    {
        return static::where('activo', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Indica si la tienda puede recibir compras en este momento.
     * Sin configuracion activa o con el interruptor apagado, siempre esta abierta.
     * El horario se evalua en la zona horaria de la tienda, no en la del servidor.
     *
     * @return array{abierto: bool, horario: string}
     */
    public static function estadoDeAtencion(?Carbon $ahora = null): array
    {
        $config = static::activa();

        if (! $config || ! $config->limitar_horario) {
            return ['abierto' => true, 'horario' => ''];
        }

        $ahora = ($ahora ?? Carbon::now())->copy()->setTimezone(config('app.horario_timezone'));
        $dias  = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $dia   = $dias[$ahora->dayOfWeek];

        $horario = self::textoDeHorario(($config->horarios ?? [])[$dia] ?? null);

        if (! preg_match('/^(\d{1,2}):(\d{2})\s*-\s*(\d{1,2}):(\d{2})$/', $horario, $m)) {
            return ['abierto' => false, 'horario' => $horario];
        }

        $minutos = $ahora->hour * 60 + $ahora->minute;
        $inicio  = (int) $m[1] * 60 + (int) $m[2];
        $fin     = (int) $m[3] * 60 + (int) $m[4];

        return ['abierto' => $minutos >= $inicio && $minutos <= $fin, 'horario' => $horario];
    }

    /** El horario de un dia puede venir como texto ("08:00 - 20:00") o como arreglo del panel. */
    private static function textoDeHorario($valor): string
    {
        if (is_string($valor)) {
            return trim($valor) ?: 'Cerrado';
        }

        if (! is_array($valor) || empty($valor['abierto'])) {
            return 'Cerrado';
        }

        $apertura = trim((string) ($valor['apertura'] ?? ''));
        $cierre   = trim((string) ($valor['cierre'] ?? ''));

        return $apertura !== '' && $cierre !== '' ? "{$apertura} - {$cierre}" : 'Cerrado';
    }

    /**
     * Horarios capturados, de lunes a domingo, como lineas para mostrar al cliente
     * ("Lunes: 08:00 - 20:00"). Los dias sin captura no aparecen.
     *
     * @return string[]
     */
    public function horariosParaMostrar(): array
    {
        $nombres = [
            'lunes' => 'Lunes', 'martes' => 'Martes', 'miercoles' => 'Miércoles', 'jueves' => 'Jueves',
            'viernes' => 'Viernes', 'sabado' => 'Sábado', 'domingo' => 'Domingo',
        ];
        $horarios = is_array($this->horarios) ? $this->horarios : [];
        $lineas = [];

        foreach ($nombres as $clave => $nombre) {
            if (array_key_exists($clave, $horarios)) {
                $lineas[] = $nombre . ': ' . self::textoDeHorario($horarios[$clave]);
            }
        }

        return $lineas;
    }

    /**
     * Datos bancarios capturados (solo los que no estan vacios), o null si no hay ninguno.
     * Se usan para mostrar al cliente a donde transferir.
     */
    public function datosBancarios(): ?array
    {
        $datos = array_filter([
            'banco' => $this->banco,
            'titular' => $this->titular_cuenta,
            'cuenta' => $this->numero_cuenta,
            'clabe' => $this->clabe,
        ], fn ($valor) => $valor !== null && $valor !== '');

        return $datos ?: null;
    }

    /**
     * URL completa del logo.
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo ? asset('storage/' . $this->logo) : null;
    }
}
