<?php

use App\Services\Food\Checkers;

it('turns a shopping-list line into a Checkers search', function () {
    expect(Checkers::term('500 g maalvleis'))->toBe('beef mince')
        ->and(Checkers::term('2 koppies gaar hoender (oorskiet of ’n rotisserie-hoender)'))->toBe('chicken')
        ->and(Checkers::term('1 pakkie taco-speserye'))->toBe('taco-speserye')
        ->and(Checkers::term('Blaarslaai of komkommer'))->toBe('Blaarslaai of komkommer')
        ->and(Checkers::link('1 l melk')['url'])->toBe('https://www.checkers.co.za/search?Search=milk');
});

it('links each line to every shop that has a search address', function () {
    $links = Checkers::links('1 l melk');

    expect(array_column($links, 'name'))->toBe(['Checkers', 'Woolworths'])
        ->and(array_column($links, 'letter'))->toBe(['C', 'W'])
        ->and($links[1]['url'])->toBe('https://www.woolworths.co.za/browse?searchterm=milk&fr=1');

    config(['services.shops.1.search' => '']);
    expect(array_column(Checkers::links('1 l melk'), 'name'))->toBe(['Checkers']);
});
