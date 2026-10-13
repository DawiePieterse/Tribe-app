<?php

namespace App\Services;

use App\Models\Member;
use App\Models\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Sends a short notification to members' phones (Web Push with VAPID keys from `tribe:vapid-keys`).
 * Does nothing when the keys aren't set. A device that is gone (404/410, or failing again and again)
 * is forgotten.
 */
class Push
{
    private const MAX_FAILURES = 5;

    private static bool $faking = false;

    /** @var list<array{member_id: int, title: string, body: string, url: string}> */
    public static array $sent = [];

    /** In tests: record instead of sending. */
    public static function fake(): void
    {
        self::$faking = true;
        self::$sent = [];
    }

    public static function enabled(): bool
    {
        return self::$faking || (filled(config('services.webpush.public_key')) && filled(config('services.webpush.private_key')));
    }

    /**
     * @param  iterable<Member>|Member  $members
     */
    public function send(iterable|Member $members, string $title, string $body, string $url): void
    {
        if (! self::enabled()) {
            return;
        }

        $members = $members instanceof Member ? [$members] : $members;
        $ids = collect($members)->map(fn (Member $m) => $m->id)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        if (self::$faking) {
            foreach ($ids as $id) {
                self::$sent[] = ['member_id' => $id, 'title' => $title, 'body' => $body, 'url' => $url];
            }

            return;
        }

        $subscriptions = PushSubscription::query()->whereIn('member_id', $ids)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $this->deliver($subscriptions, (string) json_encode(['title' => $title, 'body' => mb_substr($body, 0, 300), 'url' => $url]));
    }

    /** @param  Collection<int, PushSubscription>  $subscriptions */
    private function deliver(Collection $subscriptions, string $payload): void
    {
        try {
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
            $webPush->setReuseVAPIDHeaders(true);

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->p256dh,
                    'authToken' => $subscription->auth,
                    'contentEncoding' => 'aes128gcm',
                ]), $payload);
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

                    continue;
                }
                if ($report->isSubscriptionExpired() || $subscription->failures + 1 >= self::MAX_FAILURES) {
                    $subscription->delete();
                } else {
                    $subscription->increment('failures');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Push: '.$e->getMessage());
        }
    }
}
