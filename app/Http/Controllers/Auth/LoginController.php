<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Support\KnownAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.login', ['username' => $request->query('username')]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        KnownAccounts::remember($user, $request);

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->signOut($request);

        return redirect()->route('login');
    }

    /** "Return to login" card and account-menu entries: sign out, prefill the chosen username. */
    public function switch(Request $request): RedirectResponse
    {
        $username = $request->string('username')->limit(255, '')->toString();
        $this->signOut($request);

        return redirect()->route('login', array_filter(['username' => $username]));
    }

    private function signOut(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
