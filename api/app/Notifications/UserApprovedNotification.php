<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun Disetujui — Selamat Datang')
            ->greeting("Halo {$notifiable->name},")
            ->line('Akun Anda di AI Plan Studio telah disetujui.')
            ->line('Anda sekarang dapat login menggunakan email dan password yang Anda daftarkan.')
            ->action('Login Sekarang', url('/login'))
            ->salutation('Tim AI Plan Studio');
    }
}
