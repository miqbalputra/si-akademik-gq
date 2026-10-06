<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Kata sandi akun Ruang GQ telah diubah')
            ->greeting('Assalamu’alaikum '.$notifiable->name)
            ->line('Kata sandi akun Ruang GQ Anda baru saja diubah.')
            ->line('Jika Anda tidak melakukan perubahan ini, segera hubungi administrator sekolah.');
    }
}
