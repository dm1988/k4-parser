<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BidPortalException;
use App\Http\Controllers\Controller;
use App\Services\CrewBids\CrewBidVaultService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        try {
            app(CrewBidVaultService::class)->changePassword($request->user(), $validated['current_password'], $validated['password']);
        } catch (BidPortalException) {
            throw ValidationException::withMessages(['current_password' => 'The K4 connection could not be re-encrypted. Forget the connection, then retry the password change.'])->errorBag('updatePassword');
        }
        app(CrewBidVaultService::class)->lock($request->session());

        return back()->with('status', 'password-updated');
    }
}
