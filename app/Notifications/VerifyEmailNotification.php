<?php

namespace App\Notifications;

use App\Mail\VerifyEmailMail;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Contracts\Mail\Mailable;

class VerifyEmailNotification extends BaseVerifyEmail
{
    /**
     * Build the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): Mailable
    {
        return (new VerifyEmailMail($this->verificationUrl($notifiable), $notifiable->name))
            ->to($notifiable->getEmailForVerification());
    }
}
