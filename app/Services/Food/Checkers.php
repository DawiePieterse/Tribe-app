<?php

namespace App\Services\Food;

/**
 * Links a shopping-list line to a Checkers search, so it can be added to the Sixty60 basket from
 * there (Checkers has no way to take a list). The site searches in English, so Tribe's ingredient
 * names are turned into what the shelf label says.
 */
final class Checkers
{
    private const SEARCH_TERMS = [
        'hoender' => 'chicken', 'maalvleis' => 'beef mince', 'wors' => 'boerewors', 'spek' => 'bacon', 'vis' => 'hake',
        'eiers' => 'eggs', 'kaas' => 'cheddar cheese', 'noedels' => 'noodles', 'rys' => 'rice', 'aartappels' => 'potatoes',
        'brood' => 'bread', 'mielies' => 'sweetcorn', 'ertjies' => 'peas', 'tamaties' => 'tomatoes', 'uie' => 'onions',
        'wortels' => 'carrots', 'spinasie' => 'spinach', 'botterskorsie' => 'butternut', 'sampioene' => 'mushrooms',
        'boontjies' => 'green beans', 'melk' => 'milk', 'room' => 'fresh cream', 'jogurt' => 'yoghurt', 'botter' => 'butter',
        'patat' => 'sweet potato', 'tamatiesous' => 'tomato sauce', 'gebakte bone' => 'baked beans', 'roomkaas' => 'cream cheese',
        'bladerdeeg' => 'puff pastry', 'kerriepoeier' => 'curry powder', 'souspoeier' => 'gravy', 'koeksoda' => 'bicarbonate of soda',
        'sojasous' => 'soy sauce', 'heuning' => 'honey', 'broodkrummels' => 'breadcrumbs', 'tamatiepuree' => 'tomato paste',
    ];

    /**
     * One search link per shop in config('services.shops'): Checkers, Woolworths. The same English
     * shelf name works for both.
     *
     * @return list<array{key: string, name: string, label: string, colour: string, logo: string|null, term: string, url: string}>
     */
    public static function links(string $line): array
    {
        $term = self::term($line);
        $links = [];

        foreach ((array) config('services.shops', []) as $shop) {
            $search = trim((string) ($shop['search'] ?? ''));
            if ($search === '' || ! str_contains($search, '%s')) {
                continue;
            }
            $name = (string) ($shop['name'] ?? '');
            $key = (string) ($shop['key'] ?? strtolower($name));
            $links[] = [
                'key' => $key,
                'name' => $name,
                'colour' => (string) ($shop['colour'] ?? ''),
                'logo' => self::logo($key),
                'label' => (string) ($shop['label'] ?? $name),
                'term' => $term,
                'url' => sprintf($search, rawurlencode($term)),
            ];
        }

        return $links;
    }

    /** The shop's own logo, if one has been saved in public/icons/shops/ (Tribe doesn't ship any). */
    private static function logo(string $key): ?string
    {
        foreach (['svg', 'png', 'webp'] as $ext) {
            if ($key !== '' && is_file(public_path("icons/shops/{$key}.{$ext}"))) {
                return "/icons/shops/{$key}.{$ext}";
            }
        }

        return null;
    }

    /** @return array{term: string, url: string} */
    public static function link(string $line): array
    {
        $term = self::term($line);

        return ['term' => $term, 'url' => sprintf((string) config('services.checkers.search'), rawurlencode($term))];
    }

    /** "500 g maalvleis" → "beef mince"; an unknown line keeps its own words without the quantity. */
    public static function term(string $line): string
    {
        $name = Ingredients::canonical($line);
        if ($name !== null && isset(self::SEARCH_TERMS[$name])) {
            return self::SEARCH_TERMS[$name];
        }

        $words = (string) preg_replace('/^[\d¼½¾⅓⅔.,\s]+(?:(?:g|kg|ml|l|e|t|koppies?|blikkies?|pakkies?|snye?)\b\s*)?/u', '', trim($line));
        $words = (string) preg_replace('/[(,].*$/u', '', $words);

        return trim($name ?? $words) ?: trim($line);
    }
}
