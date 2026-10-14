<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends a short notification to members' phones (Web Push with VAPID keys from `tribe:vapid-keys`).
 * Does nothing when the keys aren't set. Notifications are queued and sent together once the page
 * (or the command) is done, so nobody waits for Apple's servers; a device that is gone (404/410, or
 * failing again and again) is forgotten. Bound as a singleton; tests swap in `FakePush`.
 */
class Push
{
    private const MAX_FAILURES = 5;

    /** @var list<array{ids: list<int>, payload: string}> */
    private array $queued = [];

    private bool $flushing = false;

    /** In tests: record instead of sending. */
    public static function fake(): FakePush
    {
        $fake = new FakePush;
        app()->instance(self::class, $fake);

        return $fake;
    }

    public function enabled(): bool
    {
        return filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key'));
    }

    /**
     * @param  iterable<Member>|Member  $members
     */
    public function send(iterable|Member $members, string $title, string $body, string $url): void
    {
        if (! $this->enabled()) {
            return;
        }

        $ids = collect($members instanceof Member ? [$members] : $members)->map(fn (Member $m) => $m->id)->unique()->values()->all();
        if ($ids === []) {
            return;
        }

        $this->queue($ids, $title, mb_substr($body, 0, 300), $url);
    }

    /** @param  list<int>  $ids */
    protected function queue(array $ids, string $title, string $body, string $url): void
    {
        $this->queued[] = ['ids' => $ids, 'payload' => (string) json_encode(['title' => $title, 'body' => $body, 'url' => $url])];

        if (! $this->flushing) {
            $this->flushing = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    /** Sends everything queued, on one connection and with one signed VAPID header. */
    public function flush(): void
    {
        $queued = $this->queued;
        $this->queued = [];
        $this->flushing = false;
        if ($queued === []) {
            return;
        }

        try {
            $subscriptions = PushSubscription::query()->whereIn('member_id', array_merge(...array_column($queued, 'ids')))->get();
            if ($subscriptions->isEmpty()) {
                return;
            }

            $webPush = $this->client();
            foreach ($queued as $notification) {
                foreach ($subscriptions->whereIn('member_id', $notification['ids']) as $subscription) {
                    $webPush->queueNotification(Subscription::create([
                        'endpoint' => $subscription->endpoint,
                        'publicKey' => $subscription->p256dh,
                        'authToken' => $subscription->auth,
                        'contentEncoding' => 'aes128gcm',
                    ]), $notification['payload']);
                }
            }

            $byEndpoint = $subscriptions->keyBy('endpoint');
            foreach ($webPush->flush() as $report) {
                $subscription = $byEndpoint->get($report->getEndpoint());
                if ($subscription === null) {
                    continue;
                }
                if ($report->isSuccess()) {
                    if ($subscription->failures > 0) {
                        $subscription->update(['failures' => 0]);
                    }
                } elseif ($report->isSubscriptionExpired() || $subscription->failures + 1 >= self::MAX_FAILURES) {
                    $subscription->delete();
                } else {
                    $subscription->increment('failures');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Push: '.$e->getMessage());
        }
    }

    private function client(): WebPush
    {
        // Without the gmp or bcmath extension the library raises a notice (slower maths); that's not an error.
        set_error_handler(fn () => true, E_USER_NOTICE);
        try {
            $webPush = new WebPush(['VAPID' => [
                'subject' => (string) (config('services.webpush.subject') ?: 'mailto:'.config('mail.from.address')),
                'publicKey' => (string) config('services.webpush.public_key'),
                'privateKey' => (string) config('services.webpush.private_key'),
            ]], ['TTL' => 3600]);
        } finally {
            restore_error_handler();
        }

        return $webPush->setReuseVAPIDHeaders(true);
    }
}
