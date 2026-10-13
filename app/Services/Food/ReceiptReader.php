<?php

namespace App\Services\Food;

use App\Support\Claude;

/**
 * Reads what was bought from a photo of a till slip, a screenshot of a Sixty60 order or its VAT
 * invoice (PDF), with Claude. (Pasted text goes through `Ingredients::fromText()` instead.) Only the
 * food comes back, as plain ingredient names; the picture itself is not kept.
 */
final class ReceiptReader
{
    public const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /** A slip reads fine at this many pixels on the long side; Claude takes images up to 5 MB. */
    private const MAX_SIDE = 1600;

    /** @return list<string> */
    public function fromFile(string $contents, string $mime): array
    {
        [$contents, $mime] = $this->shrink($contents, $mime);
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

        return Ingredients::uniqueTidy((array) ($input['items'] ?? []));
    }

    /**
     * Big phone photos are made smaller before they go to Claude (the browser usually does this
     * first; this covers the rest).
     *
     * @return array{0: string, 1: string}
     */
    private function shrink(string $contents, string $mime): array
    {
        if ($mime === 'application/pdf' || ! function_exists('imagecreatefromstring')) {
            return [$contents, $mime];
        }

        $size = @getimagesizefromstring($contents);
        if ($size === false || (strlen($contents) < 3_500_000 && max($size[0], $size[1]) <= self::MAX_SIDE)) {
            return [$contents, $mime];
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return [$contents, $mime];
        }

        $scale = min(1, self::MAX_SIDE / max($size[0], $size[1]));
        $small = imagescale($image, max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale)));
        if ($small === false) {
            return [$contents, $mime];
        }

        ob_start();
        imagejpeg($small, null, 85);

        return [(string) ob_get_clean(), 'image/jpeg'];
    }
}
