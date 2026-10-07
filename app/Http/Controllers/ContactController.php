<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $contacts = Contact::query()->visibleTo($this->me())->orderBy('name')->get();

        return view('contacts.index', [
            'groups' => collect(Contact::CATEGORIES)
                ->mapWithKeys(fn ($category) => [$category => $contacts->where('category', $category)->values()])
                ->filter(fn ($group) => $group->isNotEmpty()),
        ]);
    }

    public function create(): View
    {
        return view('contacts.form', ['contact' => new Contact(['category' => 'other'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Contact::query()->create($this->validated($request));

        return redirect()->route('contacts.index')->with('status', __('Bygevoeg.'));
    }

    public function edit(Contact $contact): View
    {
        $this->ensureVisible($contact);

        return view('contacts.form', ['contact' => $contact]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->ensureVisible($contact);
        $contact->update($this->validated($request));

        return redirect()->route('contacts.index')->with('status', __('Gestoor.'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $this->ensureVisible($contact);
        $contact->delete();

        return redirect()->route('contacts.index')->with('status', __('Verwyder.'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(Contact::CATEGORIES)],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'share' => ['required', Rule::in(['household', 'family'])],
        ]);

        $data['household_id'] = $data['share'] === 'family' ? null : $this->me()->household_id;
        unset($data['share']);

        return $data;
    }
}
