{{-- "Stuur in Messages": opens the Messages app with the text filled in; the sender picks the family group. --}}
@props(['text', 'label' => null])
<a {{ $attributes->merge(['class' => 'button quiet']) }} href="sms:&amp;body={{ rawurlencode($text) }}">@include('partials.icon', ['name' => 'message']) {{ $label ?? __('Stuur in Messages') }}</a>
