<?php

namespace App\Http\Controllers;

use App\Models\InboxItem;
use App\Models\Member;
use App\Services\Messages\MessageReader;
use App\Services\Messages\Suggestion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Stuur na Tribe": a message from the family chat becomes a calendar entry to check. Two ways in:
 * pasting it in Tribe, or the iPhone share sheet via an Apple Shortcut that posts it here with the
 * member's key. Shared messages wait on the home screen; nothing reaches the calendar unchecked.
 */
class ShareController extends Controller
{
    public function paste(MessageReader $reader): View
    {
        return view('share.paste', ['usesClaude' => $reader->usesClaude()]);
    }

    public function read(Request $request, MessageReader $reader): RedirectResponse
    {
        $data = $request->validate(['text' => ['required', 'string', 'max:4000']]);
        $suggestion = $reader->read($data['text']);

        return redirect()->route('events.create', $suggestion->formQuery($data['text']) + ['gelees' => 1]);
    }

    /** Called by the Shortcut. The answer is plain text, which the Shortcut shows as a notification. */
    public function receive(Request $request, MessageReader $reader): Response
    {
        $token = (string) $request->bearerToken();
        $member = strlen($token) === 40
            ? Member::query()->where('share_token_hash', hash('sha256', $token))->first()
            : null;

        if ($member === null || ! $member->canLogIn()) {
            return response(__('Tribe ken nie hierdie sleutel nie. Maak ’n nuwe sleutel in Tribe onder Meer › Stuur na Tribe.'), 401)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $text = trim((string) ($request->input('text') ?? $request->getContent()));
        if ($text === '') {
            return response(__('Daar was geen teks om te stuur nie.'), 422)->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $suggestion = $reader->read(mb_substr($text, 0, 4000));
        InboxItem::query()->create(['member_id' => $member->id, 'text' => mb_substr($text, 0, 4000), 'suggestion' => $suggestion->toArray()]);

        $when = $suggestion->startsOn ? ', '.CarbonImmutable::parse($suggestion->startsOn)->translatedFormat('l j F') : '';

        return response(__('In Tribe: :title:when. Maak Tribe oop om dit te bevestig.', ['title' => $suggestion->title, 'when' => $when]))
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    /** Opens a shared message as a new calendar entry, filled in. */
    public function open(InboxItem $inboxItem): RedirectResponse
    {
        $this->ensureMine($inboxItem);
        $suggestion = Suggestion::fromArray($inboxItem->suggestion ?? []);

        return redirect()->route('events.create', $suggestion->formQuery($inboxItem->text) + ['inkassie' => $inboxItem->id, 'gelees' => 1]);
    }

    public function discard(InboxItem $inboxItem): RedirectResponse
    {
        $this->ensureMine($inboxItem);
        $inboxItem->update(['handled_at' => now()]);

        return redirect()->route('home')->with('status', __('Weggegooi.'));
    }

    public function setup(MessageReader $reader): View
    {
        return view('share.setup', [
            'me' => $this->me(),
            'usesClaude' => $reader->usesClaude(),
            'key' => session('share_key'),
            'endpoint' => route('share.receive'),
        ]);
    }

    /** A new key for the Shortcut, shown once; any older key stops working. */
    public function newKey(): RedirectResponse
    {
        $key = Str::random(40);
        $this->me()->forceFill(['share_token_hash' => hash('sha256', $key)])->save();

        return redirect()->route('share.setup')->with('share_key', $key);
    }

    private function ensureMine(InboxItem $item): void
    {
        abort_unless($item->member_id === $this->me()->id && $item->handled_at === null, 404);
    }
}
