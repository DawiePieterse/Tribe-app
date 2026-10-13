<?php

namespace App\Services\Food;

/** Picks meals from Tribe's own cookbook (cookbook.php) by what the family has. */
final class BuiltInChef
{
    private const COUNT = 5;

    /**
     * @param  list<string>  $have  ingredient names (see Ingredients)
     * @return list<MealIdea>
     */
    public function suggest(array $have, string $level, bool $fromPantry = false): array
    {
        /** @var list<array{title: string, minutes: int, kids: string, ingredients: list<array{0: string, 1: string|null}>, method: list<string>}> $cookbook */
        $cookbook = require __DIR__.'/cookbook.php';

        $scored = [];
        foreach ($cookbook as $recipe) {
            $lines = [];
            $matches = 0;
            $missing = 0;
            foreach ($recipe['ingredients'] as [$line, $key]) {
                $has = $key === 'staple' || ($key !== null && in_array($key, $have, true));
                $lines[] = ['line' => $line, 'have' => $has];
                if ($key !== null && $key !== 'staple') {
                    $has ? $matches++ : $missing++;
                }
            }

            // Typed ingredients: the meal must use at least one of them.
            if ($matches === 0 && $have !== []) {
                continue;
            }

            $idea = new MealIdea($recipe['title'], $recipe['minutes'], $recipe['kids'], $lines, $recipe['method']);
            $scored[] = [
                'idea' => $idea,
                'level' => $idea->level() === $level ? 1 : 0,
                'score' => $matches * 10 - $missing * ($fromPantry ? 3 : 1),
            ];
        }

        usort($scored, fn ($a, $b) => [$b['level'], $b['score'], $a['idea']->minutes] <=> [$a['level'], $a['score'], $b['idea']->minutes]);

        // The chosen time first; others only when there aren't enough at that time.
        $atLevel = array_values(array_filter($scored, fn ($s) => $s['level'] === 1));
        $pick = count($atLevel) >= 3 ? $atLevel : $scored;

        return array_map(fn ($s) => $s['idea'], array_slice($pick, 0, self::COUNT));
    }
}
