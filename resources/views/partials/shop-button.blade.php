{{-- One shop's search button: the shop's letter on its colour (C on teal, W on black). --}}
<a class="shop" style="--shop: {{ $shop['colour'] ?: 'var(--action)' }}" href="{{ $shop['url'] }}" target="_blank" rel="noopener noreferrer"
   title="{{ $shop['name'] }}" aria-label="{{ __('Soek “:item” by :shop', ['item' => $shop['term'], 'shop' => $shop['name']]) }}">{{ $shop['letter'] }}</a>
