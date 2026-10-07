<?php

namespace App\Services;

use App\Mail\LoginCodeMail;
use App\Models\Invite;
use App\Models\LoginCode;
use App\Models\Member;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Passwordless logins. An emailed six-digit code works inside the installed iPhone app (a link from
 * Mail would open in Safari, which keeps its own cookies), and an admin's invite link gets someone
 * in the first time. Either way the login is remembered on that device.
 */
class Login
{
    /** Sends a code if the address belongs to an adult; says nothing either way. */
    public function sendCode(string $email): void
    {
        $member = Member::query()
            ->where('email', Str::lower(trim($email)))
            ->where('kind', Member::ADULT)
            ->first();

        if ($member === null) {
            return;
        }

        LoginCode::query()->where('member_id', $member->id)->delete();

        $code = (string) random_int(100000, 999999);

        LoginCode::query()->create([
            'member_id' => $member->id,
            'code_hash' => LoginCode::hash($code),
            'expires_at' => now()->addMinutes(LoginCode::VALID_MINUTES),
        ]);

        Mail::to($member->email)->send(new LoginCodeMail($member->firstName(), $code));
    }

    public function attemptCode(string $email, string $code): ?Member
    {
        $member = Member::query()->where('email', Str::lower(trim($email)))->first();
        $login = $member ? LoginCode::query()->where('member_id', $member->id)->latest('id')->first() : null;

        if ($member === null || $login === null) {
            return null;
        }

        if ($login->expires_at->isPast() || $login->attempts >= LoginCode::MAX_ATTEMPTS) {
            $login->delete();

            return null;
        }

        $code = preg_replace('/\D+/', '', $code) ?? '';

        if (! hash_equals($login->code_hash, LoginCode::hash($code))) {
            $login->increment('attempts');

            return null;
        }

        $login->delete();
        $this->logIn($member);

        return $member;
    }

    /** Creates an invite and returns the full link; only its hash is stored. */
    public function createInvite(Member $member, ?Member $by = null): string
    {
        $token = Str::random(40);

        Invite::query()->where('member_id', $member->id)->whereNull('used_at')->delete();
        Invite::query()->create([
            'member_id' => $member->id,
            'token_hash' => Invite::hash($token),
            'expires_at' => now()->addDays(Invite::VALID_DAYS),
            'created_by' => $by?->id,
        ]);

        return route('invite', $token);
    }

    public function acceptInvite(string $token): ?Member
    {
        $invite = Invite::query()->with('member')->where('token_hash', Invite::hash($token))->first();

        if ($invite === null || $invite->used_at !== null || $invite->expires_at->isPast()
            || ! $invite->member->canLogIn()) {
            return null;
        }

        $invite->update(['used_at' => now()]);
        $this->logIn($invite->member);

        return $invite->member;
    }

    private function logIn(Member $member): void
    {
        Auth::login($member, remember: true);
        session()->regenerate();
        $member->forceFill(['last_login_at' => now()])->save();
    }
}
