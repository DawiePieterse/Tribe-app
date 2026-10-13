<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * One forced tool call to Claude, so the answer always comes back in the shape asked for. Used when
 * ANTHROPIC_API_KEY is set; callers fall back to their built-in way when it isn't, or when this throws.
 */
final class Claude
{
    public static function available(): bool
    {
        return (string) config('services.anthropic.key') !== '';
    }

    /**
     * @param  string|list<array<string, mixed>>  $content  text, or content blocks (images, documents, text)
     * @param  array<string, mixed>  $schema  JSON schema of the tool's input
     * @return array<string, mixed> the tool input Claude gave
     */
    public static function tool(string $system, string|array $content, string $name, string $description, array $schema, int $maxTokens = 2000, int $timeout = 45): array
    {
        try {
            $response = Http::timeout($timeout)
                ->withHeaders(['x-api-key' => (string) config('services.anthropic.key'), 'anthropic-version' => '2023-06-01'])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('services.anthropic.model'),
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'tools' => [['name' => $name, 'description' => $description, 'input_schema' => $schema]],
                    'tool_choice' => ['type' => 'tool', 'name' => $name],
                    'messages' => [['role' => 'user', 'content' => $content]],
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Claude did not answer: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Claude answered '.$response->status());
        }

        foreach ((array) $response->json('content', []) as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use' && is_array($block['input'] ?? null)) {
                return $block['input'];
            }
        }

        throw new RuntimeException('Claude gave no '.$name);
    }
}
