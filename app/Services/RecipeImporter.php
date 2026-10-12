<?php

namespace App\Services;

use App\Support\SafeUrl;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a recipe from a web page. Almost every recipe site (and food blog plugin) publishes the
 * recipe as schema.org/Recipe JSON-LD for search engines, so that is what is read; nothing else on
 * the page is guessed at.
 */
class RecipeImporter
{
    /**
     * @return array{title: string, servings: string|null, time: string|null, ingredients: string, method: string, source_url: string}
     *
     * @throws RuntimeException with an Afrikaans message for the person importing
     */
    public function fromUrl(string $url): array
    {
        if (! SafeUrl::isPublicHttp($url)) {
            throw new RuntimeException(__('Daardie skakel kan nie oopgemaak word nie. Kyk of dit met https:// begin.'));
        }

        try {
            $response = Http::timeout(10)
                ->withOptions(SafeUrl::httpOptions($url))
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Tribe recipe import)', 'Accept' => 'text/html'])
                ->get($url);
        } catch (\Throwable) {
            throw new RuntimeException(__('Die webwerf het nie geantwoord nie. Probeer later weer, of tik die resep self in.'));
        }

        if (! $response->successful()) {
            throw new RuntimeException(__('Die webwerf het nie geantwoord nie. Probeer later weer, of tik die resep self in.'));
        }

        $recipe = $this->fromHtml(substr($response->body(), 0, 3_000_000));

        if ($recipe === null) {
            throw new RuntimeException(__('Geen resep op daardie bladsy gevind nie. Kopieer die bestanddele en metode eerder self.'));
        }

        return $recipe + ['source_url' => $url];
    }

    /**
     * @return array{title: string, servings: string|null, time: string|null, ingredients: string, method: string}|null
     */
    public function fromHtml(string $html): ?array
    {
        preg_match_all('~<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>~is', $html, $matches);

        foreach ($matches[1] as $json) {
            $data = json_decode(trim(html_entity_decode($json, ENT_QUOTES | ENT_HTML5)), true);
            if (! is_array($data)) {
                $data = json_decode(trim($json), true);
            }

            $recipe = is_array($data) ? $this->findRecipe($data) : null;

            if ($recipe !== null && ! empty($recipe['name'])) {
                return [
                    'title' => $this->clean($recipe['name']),
                    'servings' => $this->yield($recipe['recipeYield'] ?? null),
                    'time' => $this->duration($recipe['totalTime'] ?? $recipe['cookTime'] ?? null),
                    'ingredients' => implode("\n", array_map(fn ($i) => $this->clean($i), $this->list($recipe['recipeIngredient'] ?? $recipe['ingredients'] ?? []))),
                    'method' => implode("\n", $this->steps($recipe['recipeInstructions'] ?? [])),
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $data
     * @return array<string, mixed>|null
     */
    private function findRecipe(array $data): ?array
    {
        $type = $data['@type'] ?? null;
        if ($type === 'Recipe' || (is_array($type) && in_array('Recipe', $type, true))) {
            return $data;
        }

        foreach ($data as $value) {
            if (is_array($value) && ($found = $this->findRecipe($value)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /** @return list<mixed> */
    private function list(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        return is_array($value) ? array_values($value) : [];
    }

    /** @return list<string> */
    private function steps(mixed $instructions): array
    {
        if (is_string($instructions)) {
            return array_values(array_filter(array_map(fn ($l) => $this->clean($l), preg_split('/\R|<br\s*\/?>|<\/p>/i', $instructions) ?: [])));
        }

        $steps = [];
        foreach ($this->list($instructions) as $step) {
            if (is_string($step)) {
                $steps[] = $this->clean($step);
            } elseif (is_array($step) && isset($step['itemListElement'])) {
                // A HowToSection: its name as a heading line, then its steps.
                if (! empty($step['name'])) {
                    $steps[] = $this->clean($step['name']).':';
                }
                array_push($steps, ...$this->steps($step['itemListElement']));
            } elseif (is_array($step)) {
                $steps[] = $this->clean($step['text'] ?? $step['name'] ?? '');
            }
        }

        return array_values(array_filter($steps, fn ($s) => $s !== ''));
    }

    private function yield(mixed $value): ?string
    {
        $value = is_array($value) ? ($value[0] ?? null) : $value;
        if ($value === null || $value === '') {
            return null;
        }

        $text = $this->clean((string) $value);

        return ctype_digit($text) ? trans_choice(':count porsie|:count porsies', (int) $text) : mb_substr($text, 0, 50);
    }

    /** "PT1H30M" becomes "1 uur 30 min". */
    private function duration(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^P(?:T)?(?:(\d+)H)?(?:(\d+)M)?$/i', $value, $m)) {
            return null;
        }

        $hours = (int) ($m[1] ?? 0);
        $minutes = (int) ($m[2] ?? 0);
        $hours += intdiv($minutes, 60);
        $minutes %= 60;

        $parts = array_filter([$hours ? $hours.' uur' : null, $minutes ? $minutes.' min' : null]);

        return $parts ? implode(' ', $parts) : null;
    }

    private function clean(mixed $text): string
    {
        $text = html_entity_decode(strip_tags((string) (is_array($text) ? ($text['text'] ?? '') : $text)), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
