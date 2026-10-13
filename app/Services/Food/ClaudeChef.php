<?php

namespace App\Services\Food;

use App\Support\Claude;

/** Asks Claude for meal ideas. Only the ingredient names and the chosen time are sent. */
final class ClaudeChef
{
    private const TIMES = [
        MealIdea::QUICK => 'quick: 20 minutes or less from start to table, very few steps',
        MealIdea::MEDIUM => 'medium: 20 to 40 minutes, still simple',
        MealIdea::LONG => 'longer: 40 to 90 minutes, a bit more involved (a bake, a pie, a slow sauce), for a weekend or a calmer evening',
    ];

    /**
     * @param  list<string>  $have
     * @return list<MealIdea>
     */
    public function suggest(array $have, string $level, bool $fromPantry = false): array
    {
        $system = implode("\n", [
            'You suggest family dinners for working parents in South Africa who have little time.',
            'The meals must be ones children (about 3 to 12 years old) like: mild, familiar, no chilli heat.',
            'Use only ingredients that any Checkers, Pick n Pay or Woolworths sells; use South African names (mince, boerewors, hake, butternut, mielies, viennas, All Gold tomato sauce).',
            'Write everything in Afrikaans, as a South African parent would: metric measures (g, ml, koppie, e for eetlepel, t for teelepel), oven in °C.',
            'Servings: four (two adults, two children).',
            'Time: '.self::TIMES[$level].'.',
            $fromPantry
                ? 'The family recently bought the ingredients listed. Prefer meals that use several of them and need few other things.'
                : 'Every meal must be built around the ingredients listed (use at least one of them as the main ingredient, ideally all).',
            'Assume they have oil, salt, pepper, butter, flour, sugar and water; mark those as have=true.',
            'Mark each ingredient have=true if it is (or is made from) one of the listed ingredients, else have=false.',
            'Give 4 different meals. Keep method steps short and clear (at most 6).',
        ]);

        $input = Claude::tool($system, ($fromPantry ? 'Onlangs gekoop: ' : 'Ons het: ').implode(', ', array_slice($have, 0, 40)), 'meal_ideas', 'Dinner ideas for the family.', [
            'type' => 'object',
            'properties' => [
                'ideas' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string', 'description' => 'Name of the dish, in Afrikaans'],
                            'minutes' => ['type' => 'integer', 'description' => 'Total time, start to table'],
                            'kids' => ['type' => 'string', 'description' => 'One short sentence: why children like it'],
                            'ingredients' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'line' => ['type' => 'string', 'description' => 'Quantity and ingredient, e.g. "500 g maalvleis"'],
                                        'have' => ['type' => 'boolean'],
                                    ],
                                    'required' => ['line', 'have'],
                                ],
                            ],
                            'method' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['title', 'minutes', 'kids', 'ingredients', 'method'],
                    ],
                ],
            ],
            'required' => ['ideas'],
        ], 4000, 60);

        $ideas = [];
        foreach ((array) ($input['ideas'] ?? []) as $raw) {
            if (is_array($raw) && ($idea = MealIdea::fromArray($raw)) !== null) {
                $ideas[] = $idea;
            }
        }

        if ($ideas === []) {
            throw new \RuntimeException('Claude gave no usable meal ideas');
        }

        return $ideas;
    }
}
