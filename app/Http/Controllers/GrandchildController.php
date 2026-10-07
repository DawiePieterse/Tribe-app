<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Milestone;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GrandchildController extends Controller
{
    public function index(): View
    {
        return view('grandchildren.index', [
            'children' => Member::query()->children()->with('household')->orderBy('birthday')->get(),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function show(Member $member): View
    {
        $this->ensureChild($member);
        $member->load(['household', 'milestones']);

        return view('grandchildren.show', [
            'child' => $member,
            'canEdit' => $this->me()->canEditProfileOf($member),
            'today' => CarbonImmutable::today(),
        ]);
    }

    public function edit(Member $member): View
    {
        $this->ensureEditable($member);

        return view('grandchildren.edit', ['child' => $member]);
    }

    public function update(Request $request, Member $member): RedirectResponse
    {
        $this->ensureEditable($member);

        $member->update($request->validate([
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'clothes_size' => ['nullable', 'string', 'max:50'],
            'shoe_size' => ['nullable', 'string', 'max:50'],
            'favourites' => ['nullable', 'string', 'max:2000'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'wishlist' => ['nullable', 'string', 'max:2000'],
        ]));

        return redirect()->route('grandchildren.show', $member)->with('status', __('Gestoor.'));
    }

    public function addMilestone(Request $request, Member $member): RedirectResponse
    {
        $this->ensureEditable($member);

        $member->milestones()->create($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'happened_on' => ['required', 'date', 'before_or_equal:today'],
        ]));

        return redirect()->to(route('grandchildren.show', $member).'#mylpale');
    }

    public function removeMilestone(Milestone $milestone): RedirectResponse
    {
        $this->ensureEditable($milestone->member);
        $child = $milestone->member;
        $milestone->delete();

        return redirect()->to(route('grandchildren.show', $child).'#mylpale');
    }

    private function ensureChild(Member $member): void
    {
        abort_unless($member->isChild(), 404);
    }

    private function ensureEditable(Member $member): void
    {
        $this->ensureChild($member);
        abort_unless($this->me()->canEditProfileOf($member), 403);
    }
}
