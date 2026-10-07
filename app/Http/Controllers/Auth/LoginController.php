<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Login;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function sendCode(Request $request, Login $login): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $login->sendCode($data['email']);
        $request->session()->put('login_email', $data['email']);

        return redirect()->route('login.code');
    }

    public function showCode(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('login_email')) {
            return redirect()->route('login');
        }

        return view('auth.code', ['email' => $request->session()->get('login_email')]);
    }

    public function verify(Request $request, Login $login): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $email = (string) $request->session()->get('login_email', '');

        if ($email === '' || $login->attemptCode($email, $data['code']) === null) {
            return back()->withErrors(['code' => __('Die kode is verkeerd of het verval. Probeer weer, of vra ’n nuwe kode.')]);
        }

        $request->session()->forget('login_email');

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('logged_out', true);
    }
}
