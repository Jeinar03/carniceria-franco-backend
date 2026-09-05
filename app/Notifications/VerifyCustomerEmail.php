<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyCustomerEmail extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Confirma tu correo - Carnicería Franco')
            ->view('emails.verify-customer', [
                'customer' => $notifiable,
                'url' => $this->verificationUrl($notifiable),
            ]);
    }

    protected function verificationUrl($notifiable)
    {
        return URL::temporarySignedRoute(
            'api.v1.clientes.verificar',
            now()->addHours(24),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );
    }
}
