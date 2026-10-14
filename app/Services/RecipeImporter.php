<?php

namespace App\Services;

use App\Support\Claude;
use App\Support\SafeUrl;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Reads a recipe from a web page. Most recipe sites (and food blog plugins) publish the recipe as
 * schema.org/Recipe JSON-LD for search engines, so that is read first. Some (Shopify blogs such as
 * Paarman's) only have the recipe as text; then Claude reads it from the page text when
 * ANTHROPIC_API_KEY is set, and otherwise the lines under an "Ingredients" and a "Method" heading are
 * taken.
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
        $recipe = $this->fromJsonLd($html);
        if ($recipe !== null) {
            return $recipe;
        }

        $text = $this->pageText($html);
        if ($text === '') {
            return null;
        }

        if (Claude::available()) {
            try {
                return $this->fromTextWithClaude($this->pageTitle($html), $text);
            } catch (RuntimeException) {
                // Fall through to the headings below.
            }
        }

        return $this->fromHeadings($this->pageTitle($html), $text);
    }

    /**
     * @return array{title: string, servings: string|null, time: string|null, ingredients: string, method: string}|null
     */
    private function fromJsonLd(string $html): ?array
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

    /** Words a recipe uses above its ingredients and above its method, in English and Afrikaans. */
    private const INGREDIENT_HEADINGS = 'ingredients?|you will need|you\'ll need|what you need|you need|bestanddele|wat jy nodig het|jy benodig|benodigdhede';

    private const METHOD_HEADINGS = 'method|instructions?|directions?|preparation|steps|how to make it|metode|werkwyse|bereiding|aanwysings|so maak jy dit';

    /** Lines that end a recipe's method on a blog page. */
    private const END_LINES = 'share|tags?|related|you may also like|comments?|leave a comment|previous|next|back to|print|subscribe|nutrition|deel|terug na|kommentaar';

    /**
     * The page as lines of text: no scripts, styles, menus, header or footer.
     */
    private function pageText(string $html): string
    {
        $html = (string) preg_replace('~<(script|style|noscript|svg|template|iframe|nav|header|footer|form|select|button)\b[^>]*>.*?</\1>~is', ' ', $html);
        $html = (string) preg_replace('~<!--.*?-->~s', ' ', $html);
        // Each block element on its own line; a list item keeps nothing of its bullet.
        $html = (string) preg_replace('~<(br|hr)\b[^>]*>|</?(p|div|li|ul|ol|h[1-6]|tr|table|section|article|aside|main|blockquote|dt|dd)\b[^>]*>~i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        $lines = [];
        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $line));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private function pageTitle(string $html): string
    {
        foreach ([
            '~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']*)["\']~i',
            '~<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']og:title["\']~i',
            '~<h1\b[^>]*>(.*?)</h1>~is',
            '~<title\b[^>]*>(.*?)</title>~is',
        ] as $pattern) {
            if (preg_match($pattern, $html, $m) && ($title = $this->clean($m[1])) !== '') {
                return mb_substr($title, 0, 150);
            }
        }

        return '';
    }

    /**
     * @return array{title: string, servings: string|null, time: string|null, ingredients: string, method: string}|null
     *
     * @throws RuntimeException when Claude does not answer
     */
    private function fromTextWithClaude(string $title, string $text): ?array
    {
        $input = Claude::tool(
            implode("\n", [
                'You read the text of a web page and copy out the recipe on it, for a family recipe book.',
                'Copy it as written, in the language it is written in: do not translate, shorten, reword or add anything.',
                'Ingredients: one ingredient per line with its quantity. Where the recipe groups them (for the sauce, for the garnish), put the group name on its own line ending with a colon before its ingredients.',
                'Method: one step per line, without step numbers. Same rule for groups.',
                'Pages sometimes show the recipe twice; give it once. Leave out menus, adverts, product links, comments and anything that is not the recipe.',
                'If the page has no recipe, set found to false.',
            ]),
            "Page title: {$title}\n\n".mb_substr($text, 0, 20000),
            'recipe',
            'The recipe on the page.',
            [
                'type' => 'object',
                'properties' => [
                    'found' => ['type' => 'boolean'],
                    'title' => ['type' => 'string'],
                    'servings' => ['type' => 'string', 'description' => 'How many it serves or makes, as a number or a short phrase; empty if not given.'],
                    'time' => ['type' => 'string', 'description' => 'Total time if given, e.g. "2 hours"; empty if not given.'],
                    'ingredients' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'method' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['found', 'title', 'ingredients', 'method'],
            ],
            4000,
            60,
        );

        $ingredients = $this->lines($input['ingredients'] ?? []);
        $method = $this->lines($input['method'] ?? []);

        if (($input['found'] ?? false) !== true || $ingredients === [] || $method === []) {
            return null;
        }

        $time = $this->clean($input['time'] ?? '');

        return [
            'title' => mb_substr($this->clean($input['title'] ?? '') ?: $title, 0, 150),
            'servings' => $this->yield($input['servings'] ?? null),
            'time' => $time === '' ? null : mb_substr($time, 0, 50),
            'ingredients' => implode("\n", $ingredients),
            'method' => implode("\n", $method),
        ];
    }

    /**
     * Without Claude: the lines under an ingredients heading up to a method heading, and the lines
     * under that up to the end of the recipe.
     *
     * @return array{title: string, servings: string|null, time: string|null, ingredients: string, method: string}|null
     */
    private function fromHeadings(string $title, string $text): ?array
    {
        $lines = explode("\n", $text);
        $isHeading = fn (string $line, string $words) => (bool) preg_match('/^(?:'.$words.')\s*:?$/iu', $line);

        $ingredients = $method = [];
        $section = null;

        foreach ($lines as $line) {
            if ($isHeading($line, self::INGREDIENT_HEADINGS)) {
                if ($method !== []) {
                    break; // The recipe again, or the next one.
                }
                $section = 'ingredients';

                continue;
            }
            if ($section !== null && $isHeading($line, self::METHOD_HEADINGS)) {
                $section = 'method';

                continue;
            }
            // The end of the method: a closing line, or the start of the recipe again (its title or servings).
            if ($section === 'method' && (preg_match('/^(?:'.self::END_LINES.')\b|^(?:serves|porsies|genoeg vir|makes \d)/iu', $line) || mb_strtolower($line) === mb_strtolower($title))) {
                break;
            }

            if ($section === 'ingredients' && count($ingredients) < 60 && mb_strlen($line) <= 200) {
                $ingredients[] = $line;
            } elseif ($section === 'method' && count($method) < 40) {
                $method[] = $line;
            }
        }

        if (count($ingredients) < 2 || $method === [] || $title === '') {
            return null;
        }

        return [
            'title' => $title,
            'servings' => $this->servingsIn($text),
            'time' => null,
            'ingredients' => implode("\n", $ingredients),
            'method' => implode("\n", $method),
        ];
    }

    /** "Serves 4", "Makes 6-8", "Porsies: 6", "Genoeg vir 4". */
    private function servingsIn(string $text): ?string
    {
        if (! preg_match('/\b(?:serves|makes|servings|yield|porsies|genoeg vir|bedien)\s*:?\s*(\d+(?:\s*(?:-|–|to|tot)\s*\d+)?)\b/iu', $text, $m)) {
            return null;
        }

        $count = (string) preg_replace('/\s*(?:-|–|to|tot)\s*/u', '-', $m[1]);

        return ctype_digit($count) ? $this->yield($count) : $count.' '.__('porsies');
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        return array_values(array_filter(array_map(fn ($l) => $this->clean($l), $this->list($value)), fn ($l) => $l !== ''));
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
