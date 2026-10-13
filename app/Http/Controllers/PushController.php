<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A device turning Tribe's notifications on or off (from My instellings). */
class PushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // A device that logs in as someone else moves to them.
        PushSubscription::query()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            ['member_id' => $this->me()->id, 'p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth'], 'failures' => 0],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        PushSubscription::query()->where('member_id', $this->me()->id)->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true]);
    }
}
