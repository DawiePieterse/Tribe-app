<?php

namespace App\Models;

use App\Models\Concerns\HouseholdScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * Ingredients and method are kept as plain text, one ingredient per line, so they are easy to type
 * on a phone and easy to send to a shopping list line by line.
 *
 * @property int $id
 * @property int|null $household_id
 * @property string $title
 * @property string|null $servings
 * @property string|null $time
 * @property string|null $ingredients
 * @property string|null $method
 * @property string|null $notes
 * @property string|null $source_url
 */
class Recipe extends Model
{
    use HouseholdScoped;

    protected $fillable = ['household_id', 'title', 'servings', 'time', 'ingredients', 'method', 'notes', 'source_url', 'created_by'];

    /** @return list<string> */
    public function ingredientLines(): array
    {
        return self::lines($this->ingredients);
    }

    /** @return list<string> */
    public function methodSteps(): array
    {
        return self::lines($this->method);
    }

    /** @return list<string> */
    public static function lines(?string $text): array
    {
        $lines = array_map(fn (string $line) => trim((string) preg_replace('/^\s*(?:[-*•]|\d+[.)])\s*/u', '', $line)), preg_split('/\R/u', (string) $text) ?: []);

        return array_values(array_filter($lines, fn (string $line) => $line !== ''));
    }
}
