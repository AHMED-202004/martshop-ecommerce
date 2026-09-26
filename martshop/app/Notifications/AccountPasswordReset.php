<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class AccountPasswordReset extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        // Use a configured origin, never the incoming Host header.
        $url = rtrim((string) config('password_recovery.url'), '/').route('password.reset', [
            'token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        return (new MailMessage)
            ->subject('استعادة كلمة المرور — Mart.ps')
            ->greeting('مرحبًا،')
            ->line('وصلنا طلب لإعادة تعيين كلمة مرور حسابك.')
            ->action('اختيار كلمة مرور جديدة', $url)
            ->line('هذا الرابط صالح لمدة '.config('auth.passwords.users.expire').' دقيقة ولمرة واحدة.')
            ->line('إذا لم تطلب تغيير كلمة المرور، تجاهل هذه الرسالة. لا تشارك الرابط مع أحد.');
    }
}
