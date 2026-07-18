<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyEmailOtp
{
    /** @throws ValidationException */
    public function handle(User $user, string $otp): void
    {
        $result = DB::transaction(function () use ($user, $otp): string {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->getKey());

            if ($lockedUser->hasVerifiedEmail()) {
                $this->invalidateOtp($lockedUser);

                return 'verified';
            }

            if ($lockedUser->email_verification_otp_expires_at?->isPast()) {
                $this->invalidateOtp($lockedUser);

                return 'expired';
            }

            if ($lockedUser->email_verification_otp_hash === null
                || ! hash_equals(
                    $lockedUser->email_verification_otp_hash,
                    User::hashEmailVerificationOtp($otp),
                )) {
                return 'invalid';
            }

            $this->invalidateOtp($lockedUser);

            if ($lockedUser->markEmailAsVerified()) {
                event(new Verified($lockedUser));
            }

            return 'verified';
        });

        if ($result === 'expired') {
            throw ValidationException::withMessages([
                'otp' => 'This verification code has expired. Request a new email and try again.',
            ]);
        }

        if ($result === 'invalid') {
            throw ValidationException::withMessages([
                'otp' => 'The verification code is invalid.',
            ]);
        }
    }

    private function invalidateOtp(User $user): void
    {
        $user->forceFill([
            'email_verification_otp_hash' => null,
            'email_verification_otp_expires_at' => null,
        ])->save();
    }
}
