<x-layout :title="$child->name">
    <a class="back" href="{{ route('grandchildren.index') }}">@include('partials.icon', ['name' => 'back']) {{ __('Kleinkinders') }}</a>

    <div class="portrait" style="--c: {{ $child->displayColour() }}">
        <span class="monogram" aria-hidden="true">{{ mb_substr($child->name, 0, 1) }}</span>
        <div>
            <h1>{{ $child->name }}</h1>
            <p class="muted" style="margin: 2px 0 0">
                {{ $child->household->name }}
                @if ($child->birthday)
                    <br>{{ __('Gebore :date', ['date' => $child->birthday->translatedFormat('j F Y')]) }} ({{ trans_choice(':count jaar|:count jaar', $child->ageOn($today)) }})
                @endif
            </p>
        </div>
    </div>

    @if ($canEdit)
        <p><a class="button quiet small" href="{{ route('grandchildren.edit', $child) }}">{{ __('Wysig') }}</a></p>
    @endif

    <dl class="facts">
        <dt>{{ __('Klere') }}</dt><dd>{{ $child->clothes_size ?: '—' }}</dd>
        <dt>{{ __('Skoene') }}</dt><dd>{{ $child->shoe_size ?: '—' }}</dd>
        <dt>{{ __('Gunstelinge') }}</dt><dd class="notes">{{ $child->favourites ?: '—' }}</dd>
        <dt>{{ __('Allergieë') }}</dt><dd class="notes">{{ $child->allergies ?: __('Geen bekend') }}</dd>
    </dl>

    <h2>{{ __('Wenslys') }}</h2>
    <p style="white-space: pre-line">{{ $child->wishlist ?: __('Nog niks op die wenslys nie.') }}</p>

    <h2 id="mylpale">{{ __('Mylpale') }}</h2>
    @if ($child->milestones->isEmpty())
        <p class="empty">{{ __('Nog geen mylpale nie, soos eerste treetjies of die eerste skooldag.') }}</p>
    @else
        <ol class="timeline" style="--c: {{ $child->displayColour() }}">
            @foreach ($child->milestones as $milestone)
                <li>
                    <time datetime="{{ $milestone->happened_on->toDateString() }}">{{ $milestone->happened_on->translatedFormat('j F Y') }}</time>
                    {{ $milestone->title }}
                    @if ($canEdit)
                        <form method="post" action="{{ route('milestones.destroy', $milestone) }}" data-confirm="{{ __('Verwyder hierdie mylpaal?') }}" style="display: inline">
                            @csrf @method('delete')
                            <button type="submit" class="link small">{{ __('verwyder') }}</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($canEdit)
        <form method="post" action="{{ route('milestones.store', $child) }}" class="stack" style="margin-top: 16px">
            @csrf
            <div class="row">
                <label>{{ __('Wat het gebeur?') }}<input type="text" name="title" maxlength="255" required></label>
                <label>{{ __('Wanneer') }}<input type="date" name="happened_on" value="{{ $today->toDateString() }}" required></label>
            </div>
            @error('title')<span class="error">{{ $message }}</span>@enderror
            @error('happened_on')<span class="error">{{ $message }}</span>@enderror
            <div><button type="submit" class="quiet">{{ __('Voeg mylpaal by') }}</button></div>
        </form>
    @endif
</x-layout>
