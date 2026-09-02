<?php

namespace App\Mail;

use App\Models\Sale;
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
     * Build the message.
     */
    public function build()
    {
        return $this->from(config('mail.from.address'), config('app.name'))
                    ->subject($this->statusConfig['subject'])
                    ->view('emails.order-status')
                    ->with([
                        'sale' => $this->sale,
                        'config' => $this->statusConfig,
                        'order' => $this->orderSummary,
                        'customer' => $this->sale->customer
                    ]);
    }
}
