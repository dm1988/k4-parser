<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailWithOtp extends VerifyEmail
{
    public function __construct(public string $otp) {}

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);
        $formattedOtp = substr($this->otp, 0, 3).' '.substr($this->otp, 3);

        return (new MailMessage)
            ->subject('Verify Your Account')
            ->line('Click the button below to verify your email address:')
            ->action('Verify Email Address', $verificationUrl)
            ->line('Using an enterprise email network?')
            ->line('If the button above says expired or does not work, enter this 6-digit code on the verification page instead:')
            ->line($formattedOtp.' (Expires in 15 minutes)')
            ->line('If you did not create an account, no further action is required.');
    }
}
