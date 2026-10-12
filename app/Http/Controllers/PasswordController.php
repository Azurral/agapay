<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Every user changes their own password here; accounts set up by the administrator are sent here first. */
class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.change-password', ['forced' => (bool) $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'current_password' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) use ($user) {
                if (! is_string($value) || ! Hash::check($value, $user->password)) {
                    $fail('That is not your current password.');
                }
            }],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', Password::min(8)],
        ], [
            'current_password.required' => 'Enter your current password.',
            'password.required' => 'Enter a new password.',
            'password.min' => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'Choose a password different from your current one.',
        ]);

        // Quietly: the audit entry below says what happened without any password in it.
        $user->forceFill(['password' => $request->input('password'), 'must_change_password' => false])->saveQuietly();
        AuditLogger::record('Changed Own Password', $user, null, [], [], $user);

        return redirect()->route('dashboard')->with('status', 'Your password was changed.');
    }
}
