<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\Member;
use App\Models\TodoList;
use App\Services\Login;
use App\Services\Weather;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public function storeHousehold(Request $request, Weather $weather): RedirectResponse
    {
        $this->ensureAdmin();
        $household = Household::query()->create($this->household($request, null, $weather));

        // Every household starts with its own shopping list.
        TodoList::query()->create(['household_id' => $household->id, 'name' => __('Inkopies'), 'kind' => TodoList::SHOPPING]);

        return redirect()->route('family.index')->with('status', __('Huis bygevoeg.'));
    }

    public function updateHousehold(Request $request, Household $household, Weather $weather): RedirectResponse
    {
        $this->ensureAdmin();
        $household->update($this->household($request, $household, $weather));

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

    /**
     * The household's details; a new town is looked up for its weather.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException when the town can't be found
     */
    private function household(Request $request, ?Household $household, Weather $weather): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'people' => ['nullable', 'integer', 'min:1', 'max:'.Household::MAX_PEOPLE],
            'town' => ['nullable', 'string', 'max:100'],
        ]);

        $town = trim((string) ($data['town'] ?? ''));
        $place = ['town' => null, 'lat' => null, 'lon' => null];
        if ($town !== '' && $household !== null && $town === (string) $household->town) {
            $place = ['town' => $household->town, 'lat' => $household->lat, 'lon' => $household->lon];
        } elseif ($town !== '') {
            $place = $weather->find($town) ?? throw ValidationException::withMessages(['town' => __('Tribe kon nie daardie dorp vind nie. Probeer ’n ander spelling, of die naaste groter dorp.')]);
        }

        return ['name' => $data['name'], 'colour' => $data['colour'], 'people' => $data['people'] ?? null] + $place;
    }

    /** @return array<string, mixed> */
    private function member(Request $request, ?Member $member = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'household_id' => ['required', Rule::exists('households', 'id')],
            'kind' => ['required', Rule::in(Member::KINDS)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('members', 'email')->ignore($member?->id)],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $logsIn = $data['kind'] !== Member::CHILD;

        return [
            'name' => $data['name'],
            'household_id' => (int) $data['household_id'],
            'kind' => $data['kind'],
            'email' => $logsIn ? ($data['email'] ?? null) : null,
            'birthday' => $data['birthday'] ?? null,
            'is_admin' => $data['kind'] === Member::ADULT && $request->boolean('is_admin'),
        ];
    }
}
