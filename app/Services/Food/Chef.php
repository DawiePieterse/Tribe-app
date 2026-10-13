<?php

namespace App\Services\Food;

use App\Support\Claude;
use Illuminate\Support\Facades\Log;

/** Claude's ideas when an API key is set, Tribe's own cookbook otherwise (or when Claude fails). */
class Chef
{
    /**
     * @param  list<string>  $have
     * @return array{ideas: list<MealIdea>, source: string}
     */
    public function suggest(array $have, string $level, bool $fromPantry = false, int $people = 4): array
    {
        if (Claude::available()) {
            try {
                return ['ideas' => (new ClaudeChef)->suggest($have, $level, $fromPantry, $people), 'source' => 'ai'];
            } catch (\Throwable $e) {
                Log::warning('Kosidees: falling back to the cookbook. '.$e->getMessage());
            }
        }

        return ['ideas' => (new BuiltInChef)->suggest($have, $level, $fromPantry, $people), 'source' => 'cookbook'];
    }
}
