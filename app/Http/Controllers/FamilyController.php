<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Member;
use App\Models\TodoList;
use App\Services\Login;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admins (the grandparents and Dewan) set up households and people and send invites. Admins manage
 * people, not content: another household's private lists and events stay private to them too.
 */
class FamilyController extends Controller
{
    public function index(): View
    {
        $this->ensureAdmin();

        return view('family.index', [
            'households' => Household::query()->with('members')->orderBy('id')->get(),
            'colours' => Household::COLOURS,
        ]);
    }

    public function storeHousehold(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        $household = Household::query()->create($this->household($request));

        // Every household starts with its own shopping list.
        TodoList::query()->create(['household_id' => $household->id, 'name' => __('Inkopies'), 'kind' => TodoList::SHOPPING]);

        return redirect()->route('family.index')->with('status', __('Huis bygevoeg.'));
    }

    public function updateHousehold(Request $request, Household $household): RedirectResponse
    {
        $this->ensureAdmin();
        $household->update($this->household($request));

        return redirect()->route('family.index')->with('status', __('Gestoor.'));
    }

    public function createMember(Request $request): View
    {
        $this->ensureAdmin();

        return view('family.member', [
            'member' => new Member([
                'kind' => $request->query('soort') === Member::CHILD ? Member::CHILD : Member::ADULT,
                'household_id' => $request->integer('huis') ?: null,
            ]),
            'households' => Household::query()->orderBy('id')->get(),
        ]);
    }

    public function storeMember(Request $request): RedirectResponse
    {
        $this->ensureAdmin();
        Member::query()->create($this->member($request));

        return redirect()->route('family.index')->with('status', __('Bygevoeg.'));
    }

    public function editMember(Member $member): View
    {
        $this->ensureAdmin();

        return view('family.member', [
            'member' => $member,
            'households' => Household::query()->orderBy('id')->get(),
        ]);
    }

    public function updateMember(Request $request, Member $member): RedirectResponse
    {
        $this->ensureAdmin();
        $data = $this->member($request, $member);

        // Keep at least one admin, so nobody locks the family out.
        if ($member->is_admin && ! $data['is_admin'] && Member::query()->where('is_admin', true)->count() === 1) {
            return back()->withErrors(['is_admin' => __('Daar moet ten minste een beheerder wees.')]);
        }

        $member->update($data);

        return redirect()->route('family.index')->with('status', __('Gestoor.'));
    }

    public function destroyMember(Member $member): RedirectResponse
    {
        $this->ensureAdmin();
        abort_if($member->is($this->me()), 422, __('Jy kan jouself nie verwyder nie.'));
        $member->delete();

        return redirect()->route('family.index')->with('status', __('Verwyder.'));
    }

    public function invite(Member $member, Login $login): View
    {
        $this->ensureAdmin();
        abort_unless($member->canLogIn(), 422, __('Net grootmense met ’n e-posadres kan aanmeld.'));

        return view('family.invite', [
            'member' => $member,
            'link' => $login->createInvite($member, $this->me()),
        ]);
    }

    /** @return array{name: string, colour: string} */
    private function household(Request $request): array
    {
        /** @var array{name: string, colour: string} */
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }

    /** @return array<string, mixed> */
    private function member(Request $request, ?Member $member = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'household_id' => ['required', Rule::exists('households', 'id')],
            'kind' => ['required', Rule::in([Member::ADULT, Member::CHILD])],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member?->id)],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $adult = $data['kind'] === Member::ADULT;

        return [
            'name' => $data['name'],
            'household_id' => (int) $data['household_id'],
            'kind' => $data['kind'],
            'email' => $adult ? ($data['email'] ?? null) : null,
            'birthday' => $data['birthday'] ?? null,
            'is_admin' => $adult && $request->boolean('is_admin'),
        ];
    }
}
