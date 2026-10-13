<?php

namespace App\Http\Controllers;

use App\Models\Meal;
use App\Models\Recipe;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Each household's own week of meals (Monday to Sunday). */
class MealController extends Controller
{
    public function week(Request $request): View
    {
        $monday = $this->monday((string) $request->query('week', ''));
        $me = $this->me();

        $meals = Meal::query()
            ->with('recipe')
            ->where('household_id', $me->household_id)
            ->whereBetween('date', [$monday->toDateString(), $monday->addDays(6)->toDateString()])
            ->orderByRaw("FIELD(slot, 'breakfast', 'lunch', 'dinner')")
            ->get()
            ->groupBy(fn (Meal $m) => $m->date->toDateString());

        return view('meals.week', [
            'monday' => $monday,
            'meals' => $meals,
            'recipes' => Recipe::query()->visibleTo($me)->withFavourite($me)->orderBy('title')->get(),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $me = $this->me();

        $data = $request->validate([
            'date' => ['required', 'date'],
            'slot' => ['required', Rule::in(Meal::SLOTS)],
            'recipe_id' => ['nullable', Rule::exists('recipes', 'id')],
            'title' => ['nullable', 'string', 'max:255', 'required_without:recipe_id'],
        ], ['title.required_without' => __('Kies ’n resep of tik wat julle eet.')]);

        if (! empty($data['recipe_id'])) {
            $this->ensureVisible(Recipe::query()->findOrFail($data['recipe_id']));
        }

        Meal::query()->create([
            'household_id' => $me->household_id,
            'date' => $data['date'],
            'slot' => $data['slot'],
            'recipe_id' => $data['recipe_id'] ?? null,
            'title' => empty($data['recipe_id']) ? $data['title'] : null,
            'created_by' => $me->id,
        ]);

        return redirect()->to(Meal::weekUrl(CarbonImmutable::parse($data['date'])));
    }

    public function destroy(Meal $meal): RedirectResponse
    {
        abort_unless($meal->household_id === $this->me()->household_id, 404);
        $monday = $meal->date->startOfWeek(CarbonInterface::MONDAY);
        $meal->delete();

        return redirect()->route('meals.week', ['week' => $monday->toDateString()]);
    }

    /** Everything the week's recipes need, to tick off before it goes to a shopping list. */
    public function shopping(Request $request): View
    {
        $monday = $this->monday((string) $request->query('week', ''));
        $me = $this->me();

        $meals = Meal::query()
            ->with('recipe')
            ->where('household_id', $me->household_id)
            ->whereNotNull('recipe_id')
            ->whereBetween('date', [$monday->toDateString(), $monday->addDays(6)->toDateString()])
            ->orderBy('date')
            ->get();

        return view('meals.shopping', [
            'monday' => $monday,
            'recipes' => $meals->pluck('recipe')->filter()->unique('id')->values(),
            'lists' => TodoList::query()->shoppingFor($me)->get(),
        ]);
    }

    private function monday(string $value): CarbonImmutable
    {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? CarbonImmutable::parse($value) : CarbonImmutable::today();

        return $date->startOfWeek(CarbonInterface::MONDAY);
    }
}
