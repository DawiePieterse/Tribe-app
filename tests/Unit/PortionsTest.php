<?php

use App\Services\Food\Portions;

it('scales quantities to the number of people', function (string $line, int $to, string $expected) {
    expect(Portions::scale($line, 4, $to))->toBe($expected);
})->with([
    ['500 g maalvleis', 6, '750 g maalvleis'],
    ['500 g maalvleis', 2, '250 g maalvleis'],
    ['250 ml room', 6, '375 ml room'],
    ['1½ koppies rys', 6, '2½ koppies rys'],
    ['1 koppie gerasperde kaas', 8, '2 koppies gerasperde kaas'],
    ['1 koppie gerasperde kaas', 2, '½ koppie gerasperde kaas'],
    ['2 blikkies tuna (170 g), gedreineer', 2, '1 blikkie tuna (170 g), gedreineer'],
    ['8 eiers', 3, '6 eiers'],
    ['3 eiers', 1, '1 eier'],
    ['4 wraps', 6, '6 wraps'],
    ['1 ui, fyngekap', 2, '½ ui, fyngekap'],
    ['3 e mayonnaise', 6, '4½ e mayonnaise'],
    ['½ koppie mayonnaise', 8, '1 koppie mayonnaise'],
    ['Olie, sout en peper', 6, 'Olie, sout en peper'],
    ['Blaarslaai of komkommer', 2, 'Blaarslaai of komkommer'],
]);

it('leaves a line alone when the numbers are the same', function () {
    expect(Portions::scale('500 g maalvleis', 4, 4))->toBe('500 g maalvleis');
});
