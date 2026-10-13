<?php

namespace App\Services\Food;

use App\Support\Claude;

/**
 * Reads what was bought from a photo of a till slip, a screenshot of a Sixty60 order or its VAT
 * invoice (PDF), with Claude. Pasted text is read with the built-in ingredient list instead. Only
 * the food comes back, as plain ingredient names; the picture itself is not kept.
 */
final class ReceiptReader
{
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** @return list<string> */
    public function fromFile(string $contents, string $mime): array
    {
        $source = ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($contents)];
        $block = $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => $source]
            : ['type' => 'image', 'source' => $source];

        $input = Claude::tool(
            implode("\n", [
                'You read a South African grocery till slip, a Checkers Sixty60 order screen, or a supermarket invoice.',
                'List the food items that can be cooked with or eaten at meals. Leave out toiletries, cleaning products, pet food, plastic bags, deposits, sweets, chips and cold drinks.',
                'Give each as a short, generic Afrikaans ingredient name a parent would use, without brand, size or price: "WW FR CHICKEN BREAST FILLETS 500G" is "hoenderborsies"; "PNP BEEF MINCE" is "maalvleis"; "ALL GOLD TOM SAUCE" is "tamatiesous".',
                'If the picture is not a slip, order or invoice, return an empty list.',
            ]),
            [$block, ['type' => 'text', 'text' => 'Wat is gekoop?']],
            'groceries',
            'The food bought.',
            [
                'type' => 'object',
                'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'string']]],
                'required' => ['items'],
            ],
            1500,
            60,
        );

        return self::tidy(array_map('strval', array_filter((array) ($input['items'] ?? []), 'is_string')));
    }

    /** @return list<string> */
    public function fromText(string $text): array
    {
        return Ingredients::fromText($text);
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private static function tidy(array $names): array
    {
        $out = [];
        foreach ($names as $name) {
            $name = Ingredients::tidy($name);
            if ($name !== '' && ! in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return array_slice($out, 0, 80);
    }
}
