<?php

use App\Mail\LoginCodeMail;
use App\Models\Invite;
use App\Models\LoginCode;
use App\Services\Login;
use Illuminate\Support\Facades\Mail;

it('sends guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('logs in with an emailed code', function () {
    Mail::fake();
    $me = adult(household(), ['email' => 'ma@example.com']);

    $this->post(route('login.send'), ['email' => 'MA@example.com '])->assertRedirect(route('login.code'));

    $code = null;
    Mail::assertSent(LoginCodeMail::class, function (LoginCodeMail $mail) use (&$code) {
        $code = $mail->code;

        return $mail->hasTo('ma@example.com');
    });

    $this->post(route('login.verify'), ['code' => $code])->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($me);
    expect(LoginCode::query()->count())->toBe(0);
});

it('does not reveal whether an address is known', function () {
    Mail::fake();

    $this->post(route('login.send'), ['email' => 'nobody@example.com'])->assertRedirect(route('login.code'));
    Mail::assertNothingSent();
});

it('sends no code to children', function () {
    Mail::fake();
    child(household(), ['email' => null]);

    $this->post(route('login.send'), ['email' => 'kind@example.com']);
    Mail::assertNothingSent();
});

it('rejects a wrong code and locks after five tries', function () {
    Mail::fake();
    $me = adult(household(), ['email' => 'pa@example.com']);
    app(Login::class)->sendCode('pa@example.com');

    $this->withSession(['login_email' => 'pa@example.com']);
    foreach (range(1, LoginCode::MAX_ATTEMPTS) as $i) {
        $this->post(route('login.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
    }

    // Even the right code no longer works: the code was used up.
    $login = LoginCode::query()->first();
    expect($login->attempts)->toBe(LoginCode::MAX_ATTEMPTS);
    expect(app(Login::class)->attemptCode('pa@example.com', '123456'))->toBeNull();
    $this->assertGuest();
});

it('rejects an expired code', function () {
    Mail::fake();
    $me = adult(household(), ['email' => 'pa@example.com']);
    LoginCode::query()->create([
        'member_id' => $me->id,
        'code_hash' => LoginCode::hash('123456'),
        'expires_at' => now()->subMinute(),
    ]);

    expect(app(Login::class)->attemptCode('pa@example.com', '123456'))->toBeNull();
});

it('logs in once with an invite link', function () {
    $me = adult(household());
    $link = app(Login::class)->createInvite($me);

    $this->get($link)->assertOk()->assertSee('Welkom');
    $this->assertAuthenticatedAs($me);

    auth()->logout();
    $this->get($link)->assertRedirect(route('login'));
    $this->assertGuest();
});

it('refuses an expired invite', function () {
    $me = adult(household());
    $link = app(Login::class)->createInvite($me);
    Invite::query()->update(['expires_at' => now()->subDay()]);

    $this->get($link)->assertRedirect(route('login'));
    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(adult(household()))->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();
});
