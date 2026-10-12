<?php

namespace App\Http\Controllers;

use App\Models\TodoList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Adds ticked ingredients (from a recipe or the week's meals) to a shopping list in one go. */
class ShoppingController extends Controller
{
    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'list_id' => ['required', Rule::exists('lists', 'id')],
            'items' => ['required', 'array', 'max:200'],
            'items.*' => ['string', 'max:255'],
        ], ['items.required' => __('Merk ten minste een bestanddeel.')]);

        $list = TodoList::query()->findOrFail($data['list_id']);
        $this->ensureVisible($list);

        $already = $list->items()->where('done', false)->pluck('title')->map(fn ($t) => mb_strtolower(trim($t)))->all();
        $added = 0;

        foreach (array_unique(array_map('trim', $data['items'])) as $title) {
            if ($title === '' || in_array(mb_strtolower($title), $already, true)) {
                continue;
            }
            $list->items()->create(['title' => $title, 'created_by' => $this->me()->id]);
            $added++;
        }

        return redirect()->route('lists.show', $list)
            ->with('status', trans_choice(':count item bygevoeg.|:count items bygevoeg.', $added));
    }
}
