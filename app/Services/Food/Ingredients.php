<?php

namespace App\Services\Food;

/**
 * The everyday ingredients Tribe knows by name, in Afrikaans, with the words they go by on recipes,
 * shelves and till slips (Afrikaans and English). Lets "WW FREE RANGE CHICKEN FILLETS 500G" and
 * "hoenderborsies" both count as hoender.
 */
final class Ingredients
{
    /**
     * Checked in order, so the more specific ones come first ("sweet potato" is patat, not aartappels).
     * Patterns are regular expressions matched against lower-case text.
     */
    private const WORDS = [
        'patat' => ['patat', 'sweet ?potato'],
        'tamatiesous' => ['tamatiesous', 'tomato sauce', 'ketchup', 'all gold'],
        'gebakte bone' => ['gebakte bone', 'baked beans'],
        'roomkaas' => ['roomkaas', 'cream cheese'],
        'bladerdeeg' => ['bladerdeeg', 'puff pastry'],
        'hoender' => ['hoender', 'chicken', 'drumstick', 'boudjie'],
        'maalvleis' => ['maalvleis', 'mince'],
        'wors' => ['wors', 'sausage', 'vienna', 'russian', 'frankfurter'],
        'spek' => ['\bspek', 'bacon'],
        'ham' => ['\bham\b'],
        'tuna' => ['tuna'],
        'vis' => ['\bvis\b', 'visfilet', 'stokvis', 'hake', '\bfish', 'viskoek'],
        'eiers' => ['\beier', '\beggs?\b'],
        'kaas' => ['kaas', 'cheese', 'cheddar', 'gouda', 'mozzarella', 'parmesan'],
        'pasta' => ['pasta', 'macaroni', 'spaghetti', 'penne', 'fusilli', 'lasagn', 'skulpies'],
        'noedels' => ['noedel', 'noodle'],
        'rys' => ['\brys\b', '\brice\b'],
        'aartappels' => ['aartappel', 'potato', 'slap ?chips', 'skyfies'],
        'wraps' => ['\bwraps?\b', 'tortilla', '\bpita'],
        'brood' => ['\bbrood', 'bread', '\brolls?\b', 'rolletjie'],
        'mielies' => ['mielie', 'corn\b', 'sweetcorn'],
        'ertjies' => ['ertjie', '\bpeas\b'],
        'tamaties' => ['tamatie', 'tomato'],
        'uie' => ['\buie?\b', 'onion'],
        'wortels' => ['wortel', 'carrot'],
        'broccoli' => ['broc+ol+i'],
        'spinasie' => ['spinasie', 'spinach'],
        'botterskorsie' => ['botterskorsie', 'butternut'],
        'sampioene' => ['sampioen', 'mushroom'],
        'boontjies' => ['boontjie', '\bbeans\b'],
        'melk' => ['\bmelk\b', '\bmilk\b'],
        'room' => ['\broom\b', '\bcream\b'],
        'jogurt' => ['jogurt', 'yog(h)?urt'],
        'botter' => ['\bbotter\b', '\bbutter\b'],
    ];

    /** Slip lines that look like food words but aren't cooking ingredients. */
    private const NOT_INGREDIENTS = ['ice ?cream', 'roomys', 'crisps', '\\bchips\\b', 'simba', '\\blays\\b', '\\bsweets\\b', 'lekkers', 'cream soda', 'shampoo', 'tandepasta', 'toothpaste', 'dog food', 'cat food', 'hondekos', 'katkos'];

    /** Things nearly every kitchen has, never put on a shopping list for a suggestion. */
    public const STAPLES = ['olie', 'sout', 'peper', 'botter', 'meel', 'suiker', 'water', 'kruie'];

    /** @return list<string> */
    public static function known(): array
    {
        return array_keys(self::WORDS);
    }

    /** The ingredient a word or slip line stands for, or null if Tribe doesn't know it. */
    public static function canonical(string $text): ?string
    {
        $text = mb_strtolower(trim($text));
        if ($text === '') {
            return null;
        }

        foreach (self::NOT_INGREDIENTS as $pattern) {
            if (preg_match('/'.$pattern.'/u', $text) === 1 && ! preg_match('/slap ?chips|sweet ?potato/u', $text)) {
                return null;
            }
        }

        foreach (self::WORDS as $name => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match('/'.$pattern.'/u', $text) === 1) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Every known ingredient in a pasted till slip, order or list, once each, in the order found.
     *
     * @return list<string>
     */
    public static function fromText(string $text): array
    {
        $found = [];
        foreach (preg_split('/\R|,|;/u', $text) ?: [] as $line) {
            $name = self::canonical($line);
            if ($name !== null && ! in_array($name, $found, true)) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /** What someone typed, tidied: the known name when there is one, else their own word. */
    public static function tidy(string $text): string
    {
        return self::canonical($text) ?? mb_substr(mb_strtolower(trim($text)), 0, 60);
    }
}
