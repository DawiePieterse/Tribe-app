<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\TodoList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListController extends Controller
{
    public function index(): View
    {
        $me = $this->me();

        $lists = TodoList::query()
            ->visibleTo($me)
            ->withCount(['items as open_count' => fn ($q) => $q->where('done', false)])
            ->orderByRaw('household_id IS NULL')
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        return view('lists.index', [
            'ours' => $lists->whereNotNull('household_id'),
            'family' => $lists->whereNull('household_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $list = TodoList::query()->create($data + ['created_by' => $this->me()->id]);

        return redirect()->route('lists.show', $list);
    }

    public function show(TodoList $list): View
    {
        $this->ensureVisible($list);
        $me = $this->me();

        $items = $list->items()->with('assignee')->orderBy('done')->orderByDesc('done_at')->orderBy('id')->get();

        return view('lists.show', [
            'list' => $list,
            'open' => $items->where('done', false)->values(),
            'done' => $items->where('done', true)->values(),
            'people' => $list->household_id === null
                ? Member::query()->where('kind', Member::ADULT)->orderBy('name')->get()
                : Member::query()->where('household_id', $me->household_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, TodoList $list): RedirectResponse
    {
        $this->ensureVisible($list);
        $list->update($this->validated($request));

        return redirect()->route('lists.show', $list)->with('status', __('Gestoor.'));
    }

    public function destroy(TodoList $list): RedirectResponse
    {
        $this->ensureVisible($list);
        $list->delete();

        return redirect()->route('lists.index')->with('status', __('Lys verwyder.'));
    }

    /** Clears the ticked-off items. */
    public function clear(TodoList $list): RedirectResponse
    {
        $this->ensureVisible($list);
        $list->items()->where('done', true)->delete();

        return redirect()->route('lists.show', $list);
    }

    /** @return array{name: string, kind: string, household_id: int|null} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in([TodoList::SHOPPING, TodoList::TASKS])],
            'share' => ['required', Rule::in(['household', 'family'])],
        ]);

        return [
            'name' => $data['name'],
            'kind' => $data['kind'],
            'household_id' => $data['share'] === 'family' ? null : $this->me()->household_id,
        ];
    }
}
