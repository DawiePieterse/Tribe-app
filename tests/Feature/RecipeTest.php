<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\TodoList;
use App\Services\RecipeImporter;
use App\Support\SafeUrl;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->me = adult(household());
    $this->list = TodoList::query()->create(['household_id' => $this->me->household_id, 'name' => 'Inkopies']);
});

function recipePage(): string
{
    $json = json_encode(['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'WebPage', 'name' => 'Blog'],
        [
            '@type' => ['Recipe'],
            'name' => 'Melktert &amp; kaneel',
            'recipeYield' => ['6'],
            'totalTime' => 'PT1H30M',
            'recipeIngredient' => ['1 l melk', '<b>2</b> eiers'],
            'recipeInstructions' => [
                ['@type' => 'HowToSection', 'name' => 'Kors', 'itemListElement' => [['@type' => 'HowToStep', 'text' => 'Rol die deeg uit.']]],
                ['@type' => 'HowToStep', 'text' => 'Bak 20 minute.'],
            ],
        ],
    ]]);

    return '<html><head><script type="application/ld+json">'.$json.'</script></head><body>…</body></html>';
}

it('reads a schema.org recipe from a page', function () {
    $recipe = app(RecipeImporter::class)->fromHtml(recipePage());

    expect($recipe)->toBe([
        'title' => 'Melktert & kaneel',
        'servings' => '6 porsies',
        'time' => '1 uur 30 min',
        'ingredients' => "1 l melk\n2 eiers",
        'method' => "Kors:\nRol die deeg uit.\nBak 20 minute.",
    ]);
});

it('imports a recipe from a link into the form, unsaved', function () {
    Http::fake(['*' => Http::response(recipePage())]);

    $this->actingAs($this->me)
        ->post(route('recipes.import'), ['url' => 'https://93.184.215.14/melktert'])
        ->assertOk()
        ->assertSee('Melktert &amp; kaneel', false)
        ->assertSee('Rol die deeg uit.');

    expect(Recipe::query()->count())->toBe(0);
});

it('refuses links to the server’s own network', function (string $url) {
    expect(SafeUrl::isPublicHttp($url))->toBeFalse();
})->with(['http://127.0.0.1/', 'http://localhost/', 'http://10.0.0.5/x', 'http://192.168.1.1/', 'file:///etc/passwd', 'http://169.254.169.254/latest', 'http://[::1]/']);

it('says so when a page has no recipe', function () {
    Http::fake(['*' => Http::response('<html><body>Geen resep</body></html>')]);

    $this->actingAs($this->me)->from(route('recipes.create'))
        ->post(route('recipes.import'), ['url' => 'https://93.184.215.14/niks'])
        ->assertRedirect(route('recipes.create'))
        ->assertSessionHasErrors('url');
});

it('sends ticked ingredients to a shopping list once', function () {
    $this->list->items()->create(['title' => 'Melk']);

    $this->actingAs($this->me)->post(route('shopping.add'), [
        'list_id' => $this->list->id,
        'items' => ['melk', 'Eiers', 'Eiers', 'Suiker'],
    ])->assertRedirect(route('lists.show', $this->list));

    expect($this->list->items()->orderBy('id')->pluck('title')->all())->toBe(['Melk', 'Eiers', 'Suiker']);
});

it('will not add to another household’s list', function () {
    $theirs = TodoList::query()->create(['household_id' => household('Hulle')->id, 'name' => 'Hulle s’n']);

    $this->actingAs($this->me)->post(route('shopping.add'), ['list_id' => $theirs->id, 'items' => ['Melk']])->assertNotFound();
});

it('plans meals per household and gathers the week’s ingredients', function () {
    $recipe = Recipe::query()->create(['title' => 'Bobotie', 'ingredients' => "500 g maalvleis\n- 1 ui"]);
    $monday = today()->startOfWeek(CarbonInterface::MONDAY);

    $this->actingAs($this->me);
    $this->post(route('meals.store'), ['date' => $monday->addDays(2)->toDateString(), 'slot' => 'dinner', 'recipe_id' => $recipe->id])->assertRedirect();
    $this->post(route('meals.store'), ['date' => $monday->toDateString(), 'slot' => 'dinner', 'title' => 'Braai'])->assertRedirect();

    $this->get(route('meals.week', ['week' => $monday->toDateString()]))->assertSee('Bobotie')->assertSee('Braai');
    $this->get(route('meals.shopping', ['week' => $monday->toDateString()]))->assertSee('500 g maalvleis')->assertSee('1 ui');

    // Another household doesn't see or remove our meals.
    $other = adult(household('Hulle'));
    $this->actingAs($other)->get(route('meals.week', ['week' => $monday->toDateString()]))->assertDontSee('Braai');
    $this->actingAs($other)->delete(route('meals.destroy', Meal::query()->first()))->assertNotFound();
});

it('needs a recipe or a few words for a meal', function () {
    $this->actingAs($this->me)->post(route('meals.store'), ['date' => today()->toDateString(), 'slot' => 'dinner'])
        ->assertSessionHasErrors('title');
});

it('shows the recipe and week pages', function (string $route) {
    $this->actingAs($this->me)->get(route($route))->assertOk();
})->with(['recipes.index', 'recipes.create', 'meals.week', 'meals.shopping']);
