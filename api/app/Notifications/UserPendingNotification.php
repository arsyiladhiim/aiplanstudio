<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPendingNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftaran Diterima — Menunggu Persetujuan')
            ->greeting("Halo {$notifiable->name},")
            ->line('Akun Anda berhasil didaftarkan di AI Plan Studio.')
            ->line('Akun menunggu persetujuan administrator sebelum dapat digunakan untuk login.')
            ->line('Anda akan menerima email lain saat akun disetujui.')
            ->salutation('Tim AI Plan Studio');
    }
}
