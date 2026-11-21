<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The customer's own details.
 *
 * Two separate writes on purpose: changing a name and changing a password are
 * different kinds of event. One is cosmetic and undoable, the other invalidates
 * what the account is protected by — so the password form asks for the current
 * password, and the details form does not.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profile', [
            'props' => [
                'user' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'city' => $user->city,
                ],
                'updateUrl' => route('api.profile.update'),
                'passwordUrl' => route('api.profile.password'),
                'passwordChangedAt' => $user->password_changed_at?->diffForHumans(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // Ignoring this user's own row: saving the form without touching the
            // email must not trip the unique rule against itself.
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        $user->update($data);

        return response()->json([
            'message' => 'Details saved.',
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'city' => $user->city,
            ],
        ]);
    }

    public function password(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            // 422 rather than 403: the form needs to know *which* field is wrong,
            // and a 403 would read as "you are not allowed to change your own
            // password", which is not what happened.
            throw ValidationException::withMessages([
                'current_password' => 'That is not your current password.',
            ]);
        }

        $user->forceFill([
            'password' => $data['password'],
            'password_changed_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Password changed.',
            'changedAt' => $user->password_changed_at->diffForHumans(),
        ]);
    }
}
