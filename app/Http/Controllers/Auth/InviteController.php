<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Login;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InviteController extends Controller
{
    public function accept(string $token, Login $login): View|RedirectResponse
    {
        $member = $login->acceptInvite($token);

        if ($member === null) {
            return redirect()->route('login')->withErrors([
                'email' => __('Die uitnodiging het verval of is al gebruik. Meld aan met jou e-posadres, of vra vir ’n nuwe skakel.'),
            ]);
        }

        return view('auth.welcome', ['member' => $member]);
    }
}
