<fieldset>
    <legend>{{ __('Kleur') }}</legend>
    <div class="swatches">
        @foreach (\App\Models\Household::COLOURS as $colour)
            <label>
                <input type="radio" name="colour" value="{{ $colour }}" @checked(strcasecmp($current, $colour) === 0) required>
                <span style="--c: {{ $colour }}"><span class="visually-hidden">{{ $colour }}</span></span>
            </label>
        @endforeach
    </div>
</fieldset>
