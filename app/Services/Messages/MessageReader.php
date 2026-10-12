<?php

namespace App\Services\Messages;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/** Uses Claude when an API key is set, and the built-in reader otherwise (or when Claude fails). */
class MessageReader
{
    public function read(string $text, ?CarbonImmutable $today = null): Suggestion
    {
        $today ??= CarbonImmutable::today();
        $key = (string) config('services.anthropic.key');

        if ($key !== '') {
            try {
                return (new ClaudeReader($key, (string) config('services.anthropic.model')))->read($text, $today);
            } catch (\Throwable $e) {
                Log::warning('Stuur na Tribe: falling back to the simple reader. '.$e->getMessage());
            }
        }

        return (new SimpleReader)->read($text, $today);
    }

    public function usesClaude(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }
}
