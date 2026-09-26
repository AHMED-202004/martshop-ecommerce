<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class StaffInvitation extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = rtrim((string) config('password_recovery.url'), '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);

        return (new MailMessage)
            ->subject('دعوة فريق العمل — Mart.ps')
            ->greeting('مرحبًا،')
            ->line('تم إنشاء حساب موظف لك على Mart.ps.')
            ->action('اختيار كلمة المرور وتفعيل الحساب', $url)
            ->line('الرابط صالح لمدة '.config('auth.passwords.users.expire').' دقيقة ولمرة واحدة.')
            ->line('إذا لم تكن تتوقع هذه الدعوة، لا تستخدم الرابط وتواصل مع إدارة المنصة. لا تشارك الرابط مع أحد.');
    }
}
