<?php

namespace App\Http\Controllers;

use App\Models\ListItem;
use App\Models\TodoList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * List items answer JSON to the offline-aware script on the list page (public/js/lists.js) and
 * redirect for plain form posts, so the lists also work without JavaScript.
 */
class ListItemController extends Controller
{
    public function store(Request $request, TodoList $list): JsonResponse|RedirectResponse
    {
        $this->ensureVisible($list);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'assigned_member_id' => ['nullable', Rule::exists('members', 'id')],
            'due_on' => ['nullable', 'date'],
        ]);

        $item = $list->items()->create($data + ['created_by' => $this->me()->id]);

        return $request->expectsJson()
            ? response()->json(['id' => $item->id, 'title' => $item->title], 201)
            : redirect()->route('lists.show', $list);
    }

    /** Sets done to the value sent (not a blind flip), so a replayed offline tap can't undo itself. */
    public function toggle(Request $request, ListItem $item): JsonResponse|RedirectResponse
    {
        $this->ensureVisible($item->list);

        $done = $request->has('done') ? $request->boolean('done') : ! $item->done;
        $item->update(['done' => $done, 'done_at' => $done ? now() : null]);

        return $request->expectsJson()
            ? response()->json(['id' => $item->id, 'done' => $item->done])
            : redirect()->route('lists.show', $item->list_id);
    }

    public function destroy(Request $request, ListItem $item): JsonResponse|RedirectResponse
    {
        $this->ensureVisible($item->list);
        $listId = $item->list_id;
        $item->delete();

        return $request->expectsJson()
            ? response()->json(['deleted' => true])
            : redirect()->route('lists.show', $listId);
    }
}
