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
        $formattedOtp = substr($this->otp, 0, 3).' - '.substr($this->otp, 3);

        return (new MailMessage)
            ->subject('Verify Your Account')
            ->greeting('Verify your email address')
            ->line('Please click the button below to complete your account setup:')
            ->action('Verify Email Address', $verificationUrl)
            ->line('---')
            ->line('**Alternative Verification Code**')
            ->line('If you are on an enterprise network where links are blocked, or if the button above has expired, enter this code on the verification page:')
            ->line('**'.$formattedOtp.'**')
            ->line('*This code expires in 15 minutes.*')
            ->line('---')
            ->line('If you did not create an account, you can safely ignore this email.');
    }
}
