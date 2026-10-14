<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Recipe;
use App\Models\TodoList;
use App\Services\RecipeImporter;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class RecipeController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('soek', ''));

        $recipes = Recipe::query()
            ->visibleTo($this->me())
            ->withFavourite($this->me())
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', '%'.$search.'%')
                ->orWhere('ingredients', 'like', '%'.$search.'%')))
            ->orderBy('title')
            ->get();

        return view('recipes.index', ['recipes' => $recipes, 'search' => $search]);
    }

    public function create(): View
    {
        return view('recipes.form', ['recipe' => new Recipe]);
    }

    /** Reads the recipe from a link and opens the form filled in, to check before saving. */
    public function import(Request $request, RecipeImporter $importer): View|RedirectResponse
    {
        $data = $request->validate(['url' => ['required', 'url', 'max:1000']]);

        try {
            $recipe = new Recipe($importer->fromUrl($data['url']));
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }

        return view('recipes.form', ['recipe' => $recipe, 'imported' => true]);
    }

    public function store(Request $request): RedirectResponse
    {
        $recipe = Recipe::query()->create($this->validated($request) + ['created_by' => $this->me()->id]);

        return redirect()->route('recipes.show', $recipe)->with('status', __('Resep gestoor.'));
    }

    public function show(Request $request, Recipe $recipe): View
    {
        $this->ensureVisible($recipe);

        // Quantities for the number at the table: the household's usual number, or what was asked (?vir=8).
        $written = $recipe->servingsCount();
        $people = $written === null ? null : Household::clampPeople($request->integer('vir') ?: $this->me()->household->peopleEating());

        return view('recipes.show', [
            'recipe' => $recipe,
            'written' => $written,
            'people' => $people,
            'lines' => $people === null ? $recipe->ingredientLines() : $recipe->ingredientLinesFor($written, $people),
            'maxPeople' => Household::MAX_PEOPLE,
            'favourite' => $recipe->isFavouriteOf($this->me()),
            'lists' => $this->shoppingLists(),
        ]);
    }

    /** A heart on a recipe: mine, not the household's. */
    public function favourite(Recipe $recipe): RedirectResponse
    {
        $this->ensureVisible($recipe);
        $me = $this->me();

        $recipe->favouredBy()->toggle([$me->id => ['created_at' => now()]]);

        return back();
    }

    public function edit(Recipe $recipe): View
    {
        $this->ensureVisible($recipe);

        return view('recipes.form', ['recipe' => $recipe]);
    }

    public function update(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->ensureVisible($recipe);
        $recipe->update($this->validated($request));

        return redirect()->route('recipes.show', $recipe)->with('status', __('Gestoor.'));
    }

    public function destroy(Recipe $recipe): RedirectResponse
    {
        $this->ensureVisible($recipe);
        $recipe->delete();

        return redirect()->route('recipes.index')->with('status', __('Resep verwyder.'));
    }

    /** @return Collection<int, TodoList> */
    private function shoppingLists()
    {
        return TodoList::query()->shoppingFor($this->me())->get();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'servings' => ['nullable', 'string', 'max:50'],
            'time' => ['nullable', 'string', 'max:50'],
            'ingredients' => ['nullable', 'string', 'max:10000'],
            'method' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'source_url' => ['nullable', 'url', 'max:1000'],
            'share' => ['required', Rule::in(['household', 'family'])],
        ]);

        $data['household_id'] = $data['share'] === 'family' ? null : $this->me()->household_id;
        unset($data['share']);

        return $data;
    }
}
