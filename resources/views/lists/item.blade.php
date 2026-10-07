<li @class(['item', 'is-done' => $item->done]) data-item="{{ $item->id }}" data-toggle-url="{{ route('items.toggle', $item) }}">
    <form method="post" action="{{ route('items.toggle', $item) }}" data-toggle-form>
        @csrf
        <input type="hidden" name="done" value="{{ $item->done ? 0 : 1 }}">
        <button type="submit" class="tick" aria-pressed="{{ $item->done ? 'true' : 'false' }}" aria-label="{{ $item->done ? __('Merk “:title” weer oop', ['title' => $item->title]) : __('Merk “:title” af', ['title' => $item->title]) }}">
            @include('partials.icon', ['name' => 'check'])
        </button>
    </form>
    <span class="title">
        <span>{{ $item->title }}</span>
        @if ($item->assignee || $item->due_on)
            <span class="meta">{{ implode(' · ', array_filter([$item->assignee?->firstName(), $item->due_on?->translatedFormat('j M')])) }}</span>
        @endif
    </span>
    <form method="post" action="{{ route('items.destroy', $item) }}">
        @csrf @method('delete')
        <button type="submit" class="remove" aria-label="{{ __('Verwyder “:title”', ['title' => $item->title]) }}">@include('partials.icon', ['name' => 'trash'])</button>
    </form>
</li>
