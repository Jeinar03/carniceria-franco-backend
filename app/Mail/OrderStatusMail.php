<?php

namespace App\Mail;

use App\Models\Sale;
use App\Models\SiteConfig;
use App\Services\OrderNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public $sale;
    public $statusConfig;
    public $orderSummary;

    /**
     * Create a new message instance.
     */
    public function __construct(Sale $sale, $statusConfig)
    {
        $this->sale = $sale;
        $this->statusConfig = $statusConfig;
        $this->orderSummary = OrderNotificationService::getOrderSummary($sale);
    }

    /**
     * Datos bancarios del sitio, solo para el correo que los pide (transferencia pendiente).
     */
    private function datosBancarios(): ?array
    {
        if (! ($this->statusConfig['mostrar_datos_bancarios'] ?? false)) {
            return null;
        }

        $config = SiteConfig::where('activo', true)->first();

        return $config ? $config->datosBancarios() : null;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), 'Carnicería Franko')
                    ->subject($this->statusConfig['subject'])
                    ->view('emails.order-status')
                    ->with([
                        'sale' => $this->sale,
                        'config' => $this->statusConfig,
                        'order' => $this->orderSummary,
                        'customer' => $this->sale->customer,
                        'datosBancarios' => $this->datosBancarios()
                    ]);
    }
}
