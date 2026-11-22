<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Password reset, for every role.
 *
 * Administrators are refused at the storefront *sign-in* page and must use
 * /admin/login, but they still need a way to recover an account — so this door
 * is open to all of them. Where they sign in afterwards is their own business.
 *
 * The response never says whether an address is registered. Laravel's default
 * broker does say so ("we can't find a user with that email address"), which
 * turns this form into a free account-enumeration tool for anyone who wants to
 * know which of a list of addresses have accounts here.
 */
class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(['email' => $data['email']]);

        // Throttling is the one outcome worth naming: a real user who asked
        // twice in a minute needs to know why nothing arrived. Everything else
        // gets the same sentence, whether the address exists or not.
        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages([
                'email' => 'A reset link was sent moments ago. Please wait a minute before asking for another.',
            ]);
        }

        return back()->with('status', 'If that address has an account, a reset link is on its way.');
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                // Invalidate "remember me" cookies issued before the reset: the
                // reason somebody resets a password is usually that they think
                // someone else has it.
                'remember_token' => Str::random(60),
            ])->save();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'That reset link is no longer valid. Please request a new one.',
            ]);
        }

        return redirect()
            ->route('login')
            ->with('status', 'Your password has been changed. You can sign in now.');
    }
}
