{{-- Ticked ingredients go to the chosen shopping list. $lines: list of strings; $lists: shopping lists. --}}
<form method="post" action="{{ route('shopping.add') }}" class="stack">
    @csrf
    <ul class="ingredients">
        @foreach ($lines as $line)
            <li>
                <label><input type="checkbox" name="items[]" value="{{ $line }}" checked> {{ $line }}</label>
                @php($shop = \App\Services\Food\Checkers::link($line))
                <a class="shop" href="{{ $shop['url'] }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('Soek “:item” by Checkers', ['item' => $shop['term']]) }}">{{ __('Checkers') }}</a>
            </li>
        @endforeach
    </ul>
    @error('items')<span class="error">{{ $message }}</span>@enderror
    @if ($lists->isEmpty())
        <p class="muted">{{ __('Maak eers ’n inkopielys onder Lyste.') }}</p>
    @else
        <div class="actions">
            <label class="visually-hidden" for="list_id">{{ __('Na watter lys?') }}</label>
            <select id="list_id" name="list_id" style="flex: 1">
                @foreach ($lists as $list)
                    <option value="{{ $list->id }}">{{ $list->name }} ({{ $list->isFamilyWide() ? __('hele familie') : __('ons huis') }})</option>
                @endforeach
            </select>
            <button type="submit">{{ __('Sit op die lys') }}</button>
        </div>
        <p class="muted small">{{ __('Haal die merkie af by wat julle reeds het.') }}</p>
    @endif
</form>
