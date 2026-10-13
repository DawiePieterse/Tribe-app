<?php

namespace App\Services\Sync;

use Carbon\CarbonImmutable;

/** An event as a connected calendar has it. */
final readonly class RemoteEvent
{
    /**
     * @param  string  $version  changes whenever the event changes there (an ETag or change key)
     * @param  int|null  $tribeId  the Tribe event this is a copy of, when Tribe put it there
     */
    public function __construct(
        public string $id,
        public string $version,
        public EventData $data,
        public ?CarbonImmutable $updatedAt = null,
        public ?int $tribeId = null,
    ) {}
}
