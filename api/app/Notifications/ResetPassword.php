<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification
{
    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $url = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Reset Password - AI Plan Studio')
            ->greeting('Halo!')
            ->line('Kamu menerima email ini karena kami menerima permintaan reset password untuk akunmu.')
            ->action('Reset Password', $url)
            ->line('Tautan ini berlaku selama 60 menit.')
            ->line('Jika kamu tidak meminta reset password, abaikan email ini.');
    }
}
