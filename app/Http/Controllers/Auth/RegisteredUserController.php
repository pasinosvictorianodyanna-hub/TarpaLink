<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The customer's front door.
 *
 * Registration creates exactly one kind of account: a customer. `role` is
 * never read from the form — an administrator or a shop owner can only be
 * created inside the admin panel by somebody who already has that power.
 */
class RegisteredUserController extends Controller
{
    public function show(): RedirectResponse|View
    {
        // Already signed in? A second registration would silently replace the
        // current session with a different account.
        if (Auth::check()) {
            return redirect()->route('account');
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('account');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            // The User model's `hashed` cast handles this; doing it here too
            // would hash the hash.
            'password' => $data['password'],
            'role' => 'customer',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account')
            ->with('status', 'Welcome to TarpaLink, '.$user->name.'.');
    }
}
