<?php

use App\Models\Grocery;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\TodoList;
use App\Services\Food\BuiltInChef;
use App\Services\Food\Ingredients;
use App\Services\Food\MealIdea;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.anthropic.key' => null]);
    $this->me = adult(household('Ons'));
    $this->actingAs($this->me);
});

it('knows ingredients by their Afrikaans, English and till-slip names', function () {
    expect(Ingredients::canonical('WW FREE RANGE CHICKEN BREAST FILLETS 500G'))->toBe('hoender')
        ->and(Ingredients::canonical('PnP Lean Beef Mince 1kg'))->toBe('maalvleis')
        ->and(Ingredients::canonical('Sweet Potatoes 1kg'))->toBe('patat')
        ->and(Ingredients::canonical('ALL GOLD TOMATO SAUCE 700ML'))->toBe('tamatiesous')
        ->and(Ingredients::canonical('Simba Chips Salt & Vinegar'))->toBeNull()
        ->and(Ingredients::canonical('Kruie'))->toBeNull()
        ->and(Ingredients::fromText("Clover Full Cream Milk 2L\nFree range eggs x18\nTastic rice 2kg\nSunlight dishwashing liquid"))->toBe(['melk', 'eiers', 'rys']);
});

it('suggests children’s meals built on what they have, at the chosen time', function () {
    $quick = (new BuiltInChef)->suggest(['hoender', 'kaas'], MealIdea::QUICK);
    expect($quick)->not->toBeEmpty()
        ->and(collect($quick)->every(fn (MealIdea $i) => $i->minutes <= 20))->toBeTrue()
        ->and($quick[0]->title)->toBe('Hoender-wraps met kaas en mielies');

    $long = (new BuiltInChef)->suggest(['maalvleis'], MealIdea::LONG);
    expect(collect($long)->pluck('title'))->toContain('Cottage pie')->toContain('Lasagne')
        ->and(collect($long)->every(fn (MealIdea $i) => $i->minutes > 40))->toBeTrue();

    // What they have isn't on the list to buy.
    expect($quick[0]->missing())->toContain('4 wraps')->not->toContain('1 koppie gerasperde kaas');

    $macaroni = collect((new BuiltInChef)->suggest(['pasta'], MealIdea::QUICK))->firstWhere('title', 'Kaas-macaroni (vinnige mac & cheese)');
    // Nor are butter and flour, which every kitchen has.
    expect($macaroni->missing())->not->toContain('2 e botter')->not->toContain('2 e koekmeel');
});

it('shows ideas from the page and saves one to the week menu as a recipe', function () {
    $this->get(route('ideas.index'))->assertOk()->assertSee(__('Wat maak ons vanaand?'));

    $this->post(route('ideas.suggest'), ['people' => 4, 'have' => ['Chicken', 'pasta', ''], 'level' => 'medium', 'from' => 'typed'])
        ->assertRedirect(route('ideas.index').'#idees');

    $page = $this->get(route('ideas.index'))->assertOk()->assertSee('Hoender à la king met rys');
    $ideas = session('meal_ideas.ideas');
    $index = collect($ideas)->search(fn ($i) => $i['title'] === 'Hoender à la king met rys');

    $this->post(route('ideas.week', $index), ['date' => today()->toDateString()])->assertRedirect();

    $recipe = Recipe::query()->sole();
    expect($recipe->title)->toBe('Hoender à la king met rys')
        ->and($recipe->household_id)->toBe($this->me->household_id)
        ->and($recipe->ingredientLines())->toContain('1½ koppies rys')
        ->and(Meal::query()->sole()->recipe_id)->toBe($recipe->id);

    // Saving it as a recipe too doesn't make a second one.
    $this->post(route('ideas.recipe', $index))->assertRedirect(route('recipes.show', $recipe));
    expect(Recipe::query()->count())->toBe(1);
});

it('asks for at least one ingredient', function () {
    $this->post(route('ideas.suggest'), ['people' => 4, 'have' => ['', ''], 'level' => 'quick', 'from' => 'typed'])->assertSessionHasErrors('have');
    $this->post(route('ideas.week', 0), ['date' => today()->toDateString()])->assertNotFound();
});

it('reads what was bought from pasted text and suggests from it', function () {
    $this->post(route('ideas.bought'), ['text' => "Beef Mince 500g\nPotatoes 2kg\nFrozen Peas\nCheddar 400g"])
        ->assertRedirect(route('ideas.index'));

    expect(Grocery::query()->recentFor($this->me)->pluck('name')->sort()->values()->all())->toBe(['aartappels', 'ertjies', 'kaas', 'maalvleis']);

    $this->post(route('ideas.suggest'), ['people' => 4, 'level' => 'long', 'from' => 'bought'])->assertRedirect();
    $this->get(route('ideas.index'))->assertSee('Cottage pie')->assertSee(__('Uit wat julle gekoop het'));

    $this->delete(route('ideas.forget'));
    expect(Grocery::query()->count())->toBe(0);
});

it('keeps each household’s groceries to itself', function () {
    Grocery::record(adult(household('Ander')), ['hoender']);

    $this->post(route('ideas.suggest'), ['people' => 4, 'level' => 'quick', 'from' => 'bought'])->assertSessionHasErrors('have');
});

it('reads a till slip photo with Claude', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [
        ['type' => 'tool_use', 'name' => 'groceries', 'input' => ['items' => ['hoenderborsies', 'pasta', 'mozzarella', 'tamatiesous']]],
    ]])]);

    $this->post(route('ideas.bought'), ['slip' => UploadedFile::fake()->image('strokie.jpg', 800, 1600)])
        ->assertRedirect(route('ideas.index'));

    expect(Grocery::query()->pluck('name')->sort()->values()->all())->toBe(['hoender', 'kaas', 'pasta', 'tamatiesous']);
    Http::assertSent(fn (Request $r) => $r['messages'][0]['content'][0]['type'] === 'image'
        && $r['messages'][0]['content'][0]['source']['media_type'] === 'image/jpeg'
        && $r['tool_choice']['name'] === 'groceries');
});

it('asks Claude for ideas when it is set up, and falls back to the cookbook when it fails', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fakeSequence('api.anthropic.com/*')
        ->push(['content' => [['type' => 'tool_use', 'name' => 'meal_ideas', 'input' => ['ideas' => [[
            'title' => 'Hoender-en-kaas-jaffels', 'minutes' => 15, 'kids' => 'Bros en kaasagtig.',
            'ingredients' => [['line' => '8 snye brood', 'have' => false], ['line' => '2 koppies gaar hoender', 'have' => true]],
            'method' => ['Smeer botter aan die buitekant.', 'Rooster in die jaffelyster.'],
        ]]]]]])
        ->push('error', 500);

    $this->post(route('ideas.suggest'), ['people' => 4, 'have' => ['hoender'], 'level' => 'quick', 'from' => 'typed']);
    $this->get(route('ideas.index'))->assertSee('Hoender-en-kaas-jaffels')->assertSee('8 snye brood');

    $this->post(route('ideas.suggest'), ['people' => 4, 'have' => ['hoender'], 'level' => 'quick', 'from' => 'typed']);
    $this->get(route('ideas.index'))->assertSee('Hoender-wraps met kaas en mielies')->assertSee(__('Uit Tribe se eie resepteboek.'));
});

it('offers the missing ingredients for a shopping list and to share', function () {
    TodoList::query()->create(['name' => 'Inkopies', 'kind' => TodoList::SHOPPING, 'household_id' => $this->me->household_id]);

    $this->post(route('ideas.suggest'), ['people' => 4, 'have' => ['hoender', 'wraps', 'kaas'], 'level' => 'quick', 'from' => 'typed']);

    $this->get(route('ideas.index'))
        ->assertSee(__('Koop wat kort'))
        ->assertSee(__('Deel vir Sixty60'))
        ->assertSee('value="1 blikkie mielies (410 g), gedreineer"', false);
});

it('scales the meals to how many the household cooks for, and remembers it', function () {
    child($this->me->household);
    expect($this->me->household->peopleEating())->toBe(2);

    $this->post(route('ideas.suggest'), ['people' => 6, 'have' => ['maalvleis', 'pasta'], 'level' => 'medium', 'from' => 'typed']);

    expect($this->me->household->fresh()->people)->toBe(6);
    $this->get(route('ideas.index'))->assertSee('750 g maalvleis')->assertSee('vir 6 mense')->assertSee('value="6"', false);

    $index = collect(session('meal_ideas.ideas'))->search(fn ($i) => $i['title'] === 'Spaghetti bolognaise');
    $this->post(route('ideas.recipe', $index));
    expect(Recipe::query()->sole()->servings)->toBe('6 porsies');

    $this->post(route('ideas.suggest'), ['people' => 0, 'have' => ['maalvleis'], 'level' => 'quick', 'from' => 'typed'])->assertSessionHasErrors('people');
});
