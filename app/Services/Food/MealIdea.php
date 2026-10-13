<?php

namespace App\Services\Food;

/** One suggested meal, from the built-in cookbook or from Claude. */
final readonly class MealIdea
{
    public const QUICK = 'quick';

    public const MEDIUM = 'medium';

    public const LONG = 'long';

    public const LEVELS = [self::QUICK, self::MEDIUM, self::LONG];

    /**
     * @param  list<array{line: string, have: bool}>  $ingredients
     * @param  list<string>  $method
     */
    public function __construct(
        public string $title,
        public int $minutes,
        public string $kids,
        public array $ingredients,
        public array $method,
    ) {}

    public static function levelFor(int $minutes): string
    {
        return $minutes <= 20 ? self::QUICK : ($minutes <= 40 ? self::MEDIUM : self::LONG);
    }

    public function level(): string
    {
        return self::levelFor($this->minutes);
    }

    /** @return list<string> */
    public function missing(): array
    {
        return array_values(array_map(fn ($i) => $i['line'], array_filter($this->ingredients, fn ($i) => ! $i['have'])));
    }

    /** @return array{title: string, minutes: int, kids: string, ingredients: list<array{line: string, have: bool}>, method: list<string>} */
    public function toArray(): array
    {
        return ['title' => $this->title, 'minutes' => $this->minutes, 'kids' => $this->kids, 'ingredients' => $this->ingredients, 'method' => $this->method];
    }

    /** @param  array<string, mixed>  $a */
    public static function fromArray(array $a): ?self
    {
        $title = trim((string) ($a['title'] ?? ''));
        $ingredients = [];
        foreach ((array) ($a['ingredients'] ?? []) as $i) {
            $line = trim((string) (is_array($i) ? ($i['line'] ?? '') : $i));
            if ($line !== '') {
                $ingredients[] = ['line' => mb_substr($line, 0, 200), 'have' => is_array($i) && (bool) ($i['have'] ?? false)];
            }
        }
        $method = array_values(array_filter(array_map(fn ($s) => mb_substr(trim((string) $s), 0, 500), (array) ($a['method'] ?? [])), fn ($s) => $s !== ''));

        if ($title === '' || $ingredients === [] || $method === []) {
            return null;
        }

        return new self(mb_substr($title, 0, 120), max(5, min(240, (int) ($a['minutes'] ?? 30))), mb_substr(trim((string) ($a['kids'] ?? '')), 0, 300), array_slice($ingredients, 0, 25), array_slice($method, 0, 12));
    }
}
