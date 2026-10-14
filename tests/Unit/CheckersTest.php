<?php

use App\Services\Food\Checkers;

it('turns a shopping-list line into a Checkers search', function () {
    expect(Checkers::term('500 g maalvleis'))->toBe('beef mince')
        ->and(Checkers::term('2 koppies gaar hoender (oorskiet of ’n rotisserie-hoender)'))->toBe('chicken')
        ->and(Checkers::term('1 pakkie taco-speserye'))->toBe('taco-speserye')
        ->and(Checkers::term('Blaarslaai of komkommer'))->toBe('Blaarslaai of komkommer')
        ->and(Checkers::link('1 l melk')['url'])->toBe('https://www.checkers.co.za/search/all?q=milk');
});
