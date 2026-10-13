<?php

namespace App\Http\Controllers;

use App\Models\Grocery;
use App\Models\Meal;
use App\Models\Recipe;
use App\Models\TodoList;
use App\Services\Food\Chef;
use App\Services\Food\Ingredients;
use App\Services\Food\MealIdea;
use App\Services\Food\ReceiptReader;
use App\Support\Claude;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * "Wat maak ons vanaand?": name two or three things in the fridge (or use what the household bought,
 * read off a till slip or Sixty60 order), choose how much time there is, and get child-friendly
 * meals to cook, save as recipes, put on the week menu, or shop for.
 */
class MealIdeaController extends Controller
{
    private const SESSION = 'meal_ideas';

    public function index(Request $request): View
    {
        $me = $this->me();
        $result = $request->session()->get(self::SESSION);
        $ideas = [];
        foreach (is_array($result) ? (array) ($result['ideas'] ?? []) : [] as $raw) {
            if (is_array($raw) && ($idea = MealIdea::fromArray($raw)) !== null) {
                $ideas[] = $idea;
            }
        }

        $bought = Grocery::query()->recentFor($me)->orderByDesc('bought_on')->orderBy('name')->pluck('name');

        return view('meals.ideas', [
            'ideas' => $ideas,
            'asked' => is_array($result) ? $result : [],
            'bought' => $bought,
            'known' => collect(Ingredients::known())->merge($bought)->unique()->sort()->values(),
            'claude' => Claude::available(),
            'lists' => TodoList::query()->visibleTo($me)->where('kind', TodoList::SHOPPING)
                ->orderByRaw('household_id IS NULL')->orderBy('name')->get(),
            'days' => collect(range(0, 6))->map(fn ($i) => CarbonImmutable::today()->addDays($i)),
        ]);
    }

    public function suggest(Request $request, Chef $chef): RedirectResponse
    {
        $data = $request->validate([
            'have' => ['nullable', 'array', 'max:3'],
            'have.*' => ['nullable', 'string', 'max:60'],
            'bought' => ['nullable', 'array', 'max:40'],
            'bought.*' => ['string', 'max:60'],
            'level' => ['required', Rule::in(MealIdea::LEVELS)],
            'from' => ['nullable', Rule::in(['typed', 'bought'])],
        ]);

        $fromPantry = ($data['from'] ?? 'typed') === 'bought';
        $have = $fromPantry
            ? Grocery::query()->recentFor($this->me())->pluck('name')->all()
            : array_merge(array_filter(array_map('trim', $data['have'] ?? [])), $data['bought'] ?? []);
        $have = array_values(array_unique(array_map(fn ($h) => mb_strtolower(trim((string) $h)), $have)));

        if ($have === []) {
            return back()->withInput()->withErrors(['have' => $fromPantry
                ? __('Tribe weet nog nie wat julle gekoop het nie. Laai ’n strokie op, of tik wat julle het.')
                : __('Tik ten minste een ding wat julle het.')]);
        }

        $result = $chef->suggest($have, $data['level'], $fromPantry);

        $request->session()->put(self::SESSION, [
            'have' => $have,
            'level' => $data['level'],
            'pantry' => $fromPantry,
            'source' => $result['source'],
            'ideas' => array_map(fn (MealIdea $i) => $i->toArray(), $result['ideas']),
        ]);

        return redirect()->to(route('meals.ideas').'#idees');
    }

    public function saveRecipe(Request $request, int $idea): RedirectResponse
    {
        $recipe = $this->recipeFrom($this->idea($request, $idea));

        return redirect()->route('recipes.show', $recipe)->with('status', __('Gestoor by Resepte.'));
    }

    public function addToWeek(Request $request, int $idea): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'slot' => ['nullable', Rule::in(Meal::SLOTS)],
        ]);
        $recipe = $this->recipeFrom($this->idea($request, $idea));
        $me = $this->me();

        Meal::query()->create([
            'household_id' => $me->household_id,
            'date' => $data['date'],
            'slot' => $data['slot'] ?? 'dinner',
            'recipe_id' => $recipe->id,
            'created_by' => $me->id,
        ]);

        $date = CarbonImmutable::parse($data['date']);

        return redirect()->to(route('meals.week', ['week' => $date->startOfWeek(CarbonInterface::MONDAY)->toDateString()]).'#d'.$date->toDateString())
            ->with('status', __('“:title” is op die weekmenu.', ['title' => $recipe->title]));
    }

    /** A photo of the till slip, a Sixty60 order screenshot or invoice, or pasted text. */
    public function readBought(Request $request, ReceiptReader $reader): RedirectResponse
    {
        $request->validate([
            'slip' => ['nullable', 'required_without:text', 'file', 'max:12288', 'mimetypes:'.implode(',', [...ReceiptReader::IMAGE_TYPES, 'application/pdf'])],
            'text' => ['nullable', 'required_without:slip', 'string', 'max:20000'],
        ], [
            'slip.mimetypes' => __('Stuur ’n foto (JPG of PNG), ’n skermskoot of ’n PDF.'),
            'slip.required_without' => __('Kies ’n foto van die strokie, of plak die lys.'),
            'text.required_without' => __('Kies ’n foto van die strokie, of plak die lys.'),
        ]);

        $file = $request->file('slip');
        if ($file !== null && ! is_array($file)) {
            if (! Claude::available()) {
                return back()->withErrors(['slip' => __('Om foto’s te lees, moet Claude opgestel wees. Plak eerder die lys.')]);
            }

            try {
                [$contents, $mime] = $this->shrink((string) file_get_contents($file->getRealPath()), (string) $file->getMimeType());
                $names = $reader->fromFile($contents, $mime);
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors(['slip' => __('Tribe kon nie die strokie lees nie. Probeer ’n helderder foto.')]);
            }
        } else {
            $names = $reader->fromText((string) $request->input('text'));
        }

        if ($names === []) {
            return back()->withInput()->withErrors(['slip' => __('Tribe het geen kos daarop herken nie.')]);
        }

        Grocery::record($this->me(), $names);

        return redirect()->route('meals.ideas')
            ->with('status', trans_choice('Tribe het :count ding gelees.|Tribe het :count dinge gelees.', count($names)));
    }

    public function forgetBought(): RedirectResponse
    {
        Grocery::query()->where('household_id', $this->me()->household_id)->delete();

        return redirect()->route('meals.ideas')->with('status', __('Die lys van wat julle gekoop het, is leeg.'));
    }

    private function idea(Request $request, int $index): MealIdea
    {
        $raw = $request->session()->get(self::SESSION.'.ideas.'.$index);
        $idea = is_array($raw) ? MealIdea::fromArray($raw) : null;
        abort_if($idea === null, 404);

        return $idea;
    }

    private function recipeFrom(MealIdea $idea): Recipe
    {
        $me = $this->me();

        // Saving the same idea twice (recipe, then week menu) gives one recipe.
        return Recipe::query()->firstOrCreate(
            ['household_id' => $me->household_id, 'title' => $idea->title],
            [
                'servings' => '4',
                'time' => $idea->minutes.' min',
                'ingredients' => implode("\n", array_map(fn ($i) => $i['line'], $idea->ingredients)),
                'method' => implode("\n", $idea->method),
                'notes' => $idea->kids !== '' ? $idea->kids : null,
                'created_by' => $me->id,
            ],
        );
    }

    /**
     * Big phone photos are made smaller (Claude takes up to 5 MB, and a slip reads fine at 1600 px).
     *
     * @return array{0: string, 1: string}
     */
    private function shrink(string $contents, string $mime): array
    {
        if ($mime === 'application/pdf' || ! function_exists('imagecreatefromstring')) {
            return [$contents, $mime];
        }

        $size = @getimagesizefromstring($contents);
        if ($size === false || (strlen($contents) < 3_500_000 && max($size[0], $size[1]) <= 2000)) {
            return [$contents, $mime];
        }

        $image = @imagecreatefromstring($contents);
        if ($image === false) {
            return [$contents, $mime];
        }

        $scale = min(1, 1600 / max($size[0], $size[1]));
        $small = imagescale($image, max(1, (int) round($size[0] * $scale)), max(1, (int) round($size[1] * $scale)));
        if ($small === false) {
            return [$contents, $mime];
        }

        ob_start();
        imagejpeg($small, null, 85);

        return [(string) ob_get_clean(), 'image/jpeg'];
    }
}
