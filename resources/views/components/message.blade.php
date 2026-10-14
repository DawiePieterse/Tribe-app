{{--
    "Stuur in Messages": opens the Messages app with the text filled in; the sender picks the family group.
    Give `text`, or a `title` and `lines` for a bulleted list. `primary` makes it the main button.
--}}
@props(['text' => null, 'title' => null, 'lines' => [], 'link' => null, 'label' => null, 'primary' => false])
@php($body = $text ?? collect([$title !== null ? $title.':' : null, ...collect($lines)->map(fn ($l) => '• '.$l), $link])->filter()->implode("\n"))
<a {{ $attributes->class(['button', 'quiet' => ! $primary]) }} href="sms:&amp;body={{ rawurlencode($body) }}">@include('partials.icon', ['name' => 'message']) {{ $label ?? __('Stuur in Messages') }}</a>
