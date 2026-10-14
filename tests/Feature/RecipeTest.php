<?php

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\TodoList;
use App\Services\RecipeImporter;
use App\Support\Dates;
use App\Support\SafeUrl;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.anthropic.key' => null]);
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

/** A shop's blog post with the recipe only as text (no JSON-LD), shown twice, as Shopify themes do. */
function blogRecipePage(): string
{
    $recipe = <<<'HTML'
        <div class="recipe">
          <p><strong>Serves 4</strong></p>
          <h3>You Will Need:</h3>
          <p><em>Sauce</em></p>
          <ul><li>2 T butter</li><li>1 onion, chopped</li></ul>
          <p><em>Pasta</em></p>
          <ul><li>500&nbsp;g penne</li><li>1 t <a href="/products/salt">Sea Salt</a></li></ul>
          <h3>Method:</h3>
          <ol><li>Fry the onion in the butter.</li><li>Cook the penne in salted water.</li></ol>
        </div>
        HTML;

    return '<html><head><title>Kitchen | Easy Penne</title><meta property="og:title" content="Easy Penne">'
        .'<script>var ingredients = "nope";</script><style>.x{}</style></head><body>'
        .'<header><nav><ul><li>Shop</li><li>Recipes</li><li>Ingredients</li></ul></nav></header>'
        .'<article><h1>Easy Penne</h1>'.$recipe.$recipe.'<p>Share</p><p>Tags: pasta</p></article>'
        .'<footer><p>Method of payment</p></footer></body></html>';
}

it('reads a recipe written as text under its headings when the page has no recipe data', function () {
    expect(app(RecipeImporter::class)->fromHtml(blogRecipePage()))->toBe([
        'title' => 'Easy Penne',
        'servings' => '4 porsies',
        'time' => null,
        'ingredients' => "Sauce\n2 T butter\n1 onion, chopped\nPasta\n500 g penne\n1 t Sea Salt",
        'method' => "Fry the onion in the butter.\nCook the penne in salted water.",
    ]);
});

it('has Claude read a recipe from the page text when the key is set', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'tool_use', 'name' => 'recipe', 'input' => [
        'found' => true, 'title' => 'Easy Penne', 'servings' => '4', 'time' => '',
        'ingredients' => ['Sauce:', '2 T butter', '1 onion, chopped'], 'method' => ['Fry the onion in the butter.'],
    ]]]])]);

    expect(app(RecipeImporter::class)->fromHtml(blogRecipePage()))->toBe([
        'title' => 'Easy Penne',
        'servings' => '4 porsies',
        'time' => null,
        'ingredients' => "Sauce:\n2 T butter\n1 onion, chopped",
        'method' => 'Fry the onion in the butter.',
    ]);
    Http::assertSent(fn ($r) => $r['tool_choice']['name'] === 'recipe'
        && str_contains($r['messages'][0]['content'], 'Fry the onion')
        && ! str_contains($r['messages'][0]['content'], 'nope'));
});

it('falls back to the headings when Claude does not answer', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response('error', 500)]);

    expect(app(RecipeImporter::class)->fromHtml(blogRecipePage())['method'])
        ->toBe("Fry the onion in the butter.\nCook the penne in salted water.");
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
    $weekStart = Dates::weekStart(today()->toImmutable());

    $this->actingAs($this->me);
    $this->post(route('meals.store'), ['date' => $weekStart->addDays(2)->toDateString(), 'slot' => 'dinner', 'recipe_id' => $recipe->id])->assertRedirect();
    $this->post(route('meals.store'), ['date' => $weekStart->toDateString(), 'slot' => 'dinner', 'title' => 'Braai'])->assertRedirect();

    $this->get(route('meals.week', ['week' => $weekStart->toDateString()]))->assertSee('Bobotie')->assertSee('Braai');
    $this->get(route('meals.shopping', ['week' => $weekStart->toDateString()]))->assertSee('500 g maalvleis')->assertSee('1 ui');

    // Another household doesn't see or remove our meals.
    $other = adult(household('Hulle'));
    $this->actingAs($other)->get(route('meals.week', ['week' => $weekStart->toDateString()]))->assertDontSee('Braai');
    $this->actingAs($other)->delete(route('meals.destroy', Meal::query()->first()))->assertNotFound();
});

it('needs a recipe or a few words for a meal', function () {
    $this->actingAs($this->me)->post(route('meals.store'), ['date' => today()->toDateString(), 'slot' => 'dinner'])
        ->assertSessionHasErrors('title');
});

it('shows the recipe and week pages', function (string $route) {
    $this->actingAs($this->me)->get(route($route))->assertOk();
})->with(['recipes.index', 'recipes.create', 'meals.week', 'meals.shopping']);

it('marks a recipe as a favourite, for that person only', function () {
    $recipe = Recipe::query()->create(['title' => 'Melktert', 'household_id' => $this->me->household_id]);
    $other = Recipe::query()->create(['title' => 'Bobotie', 'household_id' => $this->me->household_id]);
    $someoneElse = adult($this->me->household);

    $this->actingAs($this->me)->post(route('recipes.favourite', $recipe))->assertRedirect();
    expect($recipe->isFavouriteOf($this->me))->toBeTrue()
        ->and($recipe->isFavouriteOf($someoneElse))->toBeFalse();

    // Favourites come first, under their own heading, in the recipe list and the week menu's choices.
    $page = $this->actingAs($this->me)->get(route('recipes.index'))->assertSeeInOrder(['Gunstelinge', 'Melktert', 'Al die resepte', 'Bobotie']);
    $this->actingAs($this->me)->get(route('meals.week'))->assertSeeInOrder(['♥ Melktert', 'Bobotie']);
    $this->actingAs($someoneElse)->get(route('recipes.index'))->assertDontSee('Gunstelinge');

    // Tapping again takes it off.
    $this->actingAs($this->me)->post(route('recipes.favourite', $recipe));
    expect($recipe->isFavouriteOf($this->me))->toBeFalse();
});

it('keeps another household’s recipe out of reach of the heart', function () {
    $recipe = Recipe::query()->create(['title' => 'Geheim', 'household_id' => household('Ander')->id]);

    $this->actingAs($this->me)->post(route('recipes.favourite', $recipe))->assertNotFound();
});

it('scales a saved recipe’s ingredients for guests', function () {
    $recipe = Recipe::query()->create(['title' => 'Bobotie', 'servings' => '4 porsies', 'ingredients' => "500 g maalvleis\n2 eiers\nSout", 'household_id' => $this->me->household_id]);

    // The household's usual number by default (one member here), then whatever is asked.
    $this->actingAs($this->me)->get(route('recipes.show', $recipe))->assertSee('125 g maalvleis')->assertSee(__('Die resep is vir :written geskryf; die hoeveelhede is vir :people aangepas.', ['written' => 4, 'people' => 1]));
    $this->actingAs($this->me)->get(route('recipes.show', ['recipe' => $recipe, 'vir' => 8]))->assertSee('value="1000 g maalvleis"', false)->assertSee('4 eiers')->assertSee('Sout');

    // A recipe without a serving count isn't scaled.
    $recipe->update(['servings' => null]);
    $this->actingAs($this->me)->get(route('recipes.show', ['recipe' => $recipe, 'vir' => 8]))->assertSee('500 g maalvleis')->assertDontSee('name="vir"', false);
});
