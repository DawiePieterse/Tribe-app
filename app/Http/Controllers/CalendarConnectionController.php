<?php

namespace App\Http\Controllers;

use App\Models\CalendarConnection;
use App\Models\Event;
use App\Models\EventLink;
use App\Services\Sync\ICloudCalendar;
use App\Services\Sync\ProviderFactory;
use App\Services\Sync\TwoWaySync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Meer › Kalenders: connecting your own Google, Outlook or iCloud calendar, kept in step both ways. */
class CalendarConnectionController extends Controller
{
    public function __construct(private ProviderFactory $providers) {}

    /** Off to Google or Microsoft to sign in; they send the person back to callback(). */
    public function connect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(CalendarConnection::isAvailable($provider), 404);

        $state = Str::random(40);
        $request->session()->put('calendar_oauth', ['state' => $state, 'provider' => $provider]);

        return redirect()->away($this->providers->oauth($provider)->authorizeUrl($state, route('connections.callback', $provider)));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(CalendarConnection::isAvailable($provider), 404);
        $expected = $request->session()->pull('calendar_oauth');

        if (! is_array($expected) || $expected['provider'] !== $provider || ! hash_equals((string) $expected['state'], (string) $request->query('state'))) {
            return redirect()->route('feeds.index')->with('status', __('Die koppeling het nie gewerk nie. Probeer weer.'));
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('feeds.index')->with('status', __('Die koppeling is gekanselleer.'));
        }

        try {
            $result = $this->providers->oauth($provider)->connect((string) $request->query('code'), route('connections.callback', $provider));
        } catch (\RuntimeException $e) {
            return redirect()->route('feeds.index')->with('status', $e->getMessage());
        }

        return $this->created($provider, $result);
    }

    public function icloud(): View
    {
        return view('connections.icloud');
    }

    public function storeICloud(Request $request, ICloudCalendar $icloud): RedirectResponse
    {
        $data = $request->validate([
            'apple_id' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:64'],
        ]);

        try {
            // App-specific passwords are shown as xxxx-xxxx-xxxx-xxxx; spaces are a common slip.
            $result = $icloud->connect($data['apple_id'], str_replace(' ', '', $data['password']));
        } catch (\RuntimeException $e) {
            return back()->withInput($request->only('apple_id'))->withErrors(['password' => $e->getMessage()]);
        }

        return $this->created(CalendarConnection::ICLOUD, $result);
    }

    /** @param  array{account: string|null, credentials: array<string, mixed>}  $result */
    private function created(string $provider, array $result): RedirectResponse
    {
        $me = $this->me();

        $connection = CalendarConnection::query()->create([
            'member_id' => $me->id,
            'provider' => $provider,
            'account' => $result['account'],
            'credentials' => $result['credentials'],
            'household_id' => $me->household_id,
        ]);

        return redirect()->route('connections.edit', $connection);
    }

    public function edit(CalendarConnection $connection): View|RedirectResponse
    {
        $this->ensureMine($connection);

        try {
            $calendars = $this->providers->for($connection->provider)->calendars($connection);
        } catch (\RuntimeException $e) {
            return redirect()->route('feeds.index')->with('status', $e->getMessage());
        }

        return view('connections.edit', ['connection' => $connection, 'calendars' => $calendars]);
    }

    public function update(Request $request, CalendarConnection $connection, TwoWaySync $sync): RedirectResponse
    {
        $this->ensureMine($connection);

        $data = $request->validate([
            'calendar' => ['required', 'string', 'max:1000'],
            'share' => ['required', Rule::in(['household', 'family'])],
            'send' => ['required', Rule::in([CalendarConnection::SEND_ALL, CalendarConnection::SEND_OURS])],
        ]);

        $calendar = collect($this->providers->for($connection->provider)->calendars($connection))->firstWhere('id', $data['calendar']);
        abort_if($calendar === null, 422);

        // A different calendar starts afresh: the old links point into the other one.
        if ($connection->calendar_id !== null && $connection->calendar_id !== $calendar['id']) {
            $connection->links()->delete();
        }

        $connection->update([
            'calendar_id' => $calendar['id'],
            'calendar_name' => $calendar['name'],
            'household_id' => $data['share'] === 'family' ? null : $this->me()->household_id,
            'send' => $data['send'],
        ]);

        return redirect()->route('feeds.index')->with('status', $sync->sync($connection)
            ? __('“:name” en Tribe is nou gekoppel.', ['name' => $calendar['name']])
            : __('Gekoppel, maar die eerste sinkronisering het nie gewerk nie: :error', ['error' => $connection->last_error]));
    }

    public function sync(CalendarConnection $connection, TwoWaySync $sync): RedirectResponse
    {
        $this->ensureMine($connection);

        return redirect()->route('feeds.index')->with('status', $sync->sync($connection)
            ? __('Gesinkroniseer met :provider.', ['provider' => $connection->providerName()])
            : __('Kon nie sinkroniseer nie: :error', ['error' => $connection->last_error]));
    }

    /**
     * Stops syncing. Events that came from that calendar leave Tribe; Tribe's copies in that calendar
     * stay there as ordinary events.
     */
    public function destroy(CalendarConnection $connection): RedirectResponse
    {
        $this->ensureMine($connection);

        $imported = $connection->links()->where('origin', EventLink::FROM_REMOTE)->whereNotNull('event_id')->pluck('event_id');
        TwoWaySync::$running = true;
        try {
            Event::query()->whereIn('id', $imported)->get()->each->delete();
        } finally {
            TwoWaySync::$running = false;
        }
        $connection->delete();

        return redirect()->route('feeds.index')->with('status', __('Die kalender is ontkoppel.'));
    }

    private function ensureMine(CalendarConnection $connection): void
    {
        abort_unless($connection->member_id === $this->me()->id, 404);
    }
}
