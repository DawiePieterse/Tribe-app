<?php

namespace App\Http\Controllers;

use App\Services\Push;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function more(): View
    {
        return view('more', ['me' => $this->me()]);
    }

    public function edit(): View
    {
        return view('settings', ['me' => $this->me(), 'push' => Push::enabled() ? (string) config('services.webpush.public_key') : null]);
    }

    public function update(Request $request): RedirectResponse
    {
        $me = $this->me();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('members', 'email')->ignore($me->id)],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'large_text' => ['nullable', 'boolean'],
            'evening_email' => ['nullable', 'boolean'],
        ]);

        $me->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'birthday' => $data['birthday'] ?? null,
            'large_text' => $request->boolean('large_text'),
            'evening_email' => $request->boolean('evening_email'),
        ]);

        return redirect()->route('settings')->with('status', __('Gestoor.'));
    }
}
