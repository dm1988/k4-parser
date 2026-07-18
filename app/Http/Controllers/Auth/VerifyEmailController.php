<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\VerifyEmailOtp;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyEmailOtpRequest;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    public function verifyOtp(VerifyEmailOtpRequest $request, VerifyEmailOtp $verifyEmailOtp): RedirectResponse
    {
        $verifyEmailOtp->handle($request->user(), $request->validated('otp'));

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }

    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        $request->user()->forceFill([
            'email_verification_otp_hash' => null,
            'email_verification_otp_expires_at' => null,
        ])->save();

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
