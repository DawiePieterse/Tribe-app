<?php

namespace App\Services;

use App\Models\Member;

/** Push for tests (`Push::fake()`): always on, and records what would have gone out. */
class FakePush extends Push
{
    /** @var list<array{member_id: int, title: string, body: string, url: string}> */
    public array $sent = [];

    public function enabled(): bool
    {
        return true;
    }

    /** @return list<array{member_id: int, title: string, body: string, url: string}> */
    public function sentTo(Member $member): array
    {
        return array_values(array_filter($this->sent, fn ($n) => $n['member_id'] === $member->id));
    }

    protected function queue(array $ids, string $title, string $body, string $url): void
    {
        foreach ($ids as $id) {
            $this->sent[] = ['member_id' => $id, 'title' => $title, 'body' => $body, 'url' => $url];
        }
    }
}
