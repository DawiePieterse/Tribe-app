<?php

namespace App\Http\Controllers;

use App\Models\CalendarConnection;
use App\Models\CalendarFeed;
use App\Models\Household;
use App\Services\CalendarSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Meer › Kalenders: your own calendar synced both ways, Tribe in the iPhone Calendar app, and other calendars in Tribe. */
class CalendarFeedController extends Controller
{
    public function index(): View
    {
        $me = $this->me();
        $feedUrl = route('ics', ['token' => $me->calendarToken()]);

        return view('feeds.index', [
            'connections' => CalendarConnection::query()->where('member_id', $me->id)->withCount('links')->orderBy('id')->get(),
            'providers' => array_values(array_filter(CalendarConnection::PROVIDERS, CalendarConnection::isAvailable(...))),
            'feeds' => CalendarFeed::query()->visibleTo($me)->withCount('events')->orderBy('name')->get(),
            'webcal' => (string) preg_replace('~^https?://~', 'webcal://', $feedUrl),
            'feedUrl' => $feedUrl,
        ]);
    }

    public function store(Request $request, CalendarSync $sync): RedirectResponse
    {
        $me = $this->me();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'string', 'max:1000', 'regex:~^(https?|webcals?)://~i'],
            'share' => ['required', Rule::in(['household', 'family'])],
        ], ['url.regex' => __('Die skakel moet met https:// of webcal:// begin.')]);

        $feed = CalendarFeed::query()->create([
            'name' => $data['name'],
            'url' => trim($data['url']),
            'household_id' => $data['share'] === 'family' ? null : $me->household_id,
            'colour' => Household::COLOURS[(CalendarFeed::query()->count() + 3) % count(Household::COLOURS)],
            'created_by' => $me->id,
        ]);

        return $sync->sync($feed)
            ? redirect()->route('feeds.index')->with('status', __('“:name” is bygevoeg.', ['name' => $feed->name]))
            : redirect()->route('feeds.index')->with('status', __('“:name” is bygevoeg, maar kon nog nie gelees word nie: :error', ['name' => $feed->name, 'error' => $feed->last_error]));
    }

    public function sync(CalendarFeed $feed, CalendarSync $sync): RedirectResponse
    {
        $this->ensureVisible($feed);

        return redirect()->route('feeds.index')->with('status', $sync->sync($feed)
            ? __('“:name” is opgedateer.', ['name' => $feed->name])
            : __('Kon nie “:name” lees nie: :error', ['name' => $feed->name, 'error' => $feed->fresh()?->last_error]));
    }

    public function destroy(CalendarFeed $feed): RedirectResponse
    {
        $this->ensureVisible($feed);
        $feed->delete();

        return redirect()->route('feeds.index')->with('status', __('Kalender verwyder.'));
    }

    /** A new link, for when the old one was shared by mistake; the old link stops working. */
    public function newLink(): RedirectResponse
    {
        $this->me()->forceFill(['calendar_token' => Str::random(40)])->save();

        return redirect()->route('feeds.index')->with('status', __('Jou kalender het ’n nuwe skakel. Teken weer in op jou iPhone.'));
    }
}
