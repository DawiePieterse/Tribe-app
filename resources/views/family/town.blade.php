{{-- The household's town, for its weather. $value: the current town, if any. --}}
<label>{{ __('Dorp') }} <span class="hint">{{ __('vir die weer op Tuis en in die aandopsomming') }}</span>
    <input type="text" name="town" maxlength="100" value="{{ old('town', $value ?? '') }}" placeholder="{{ __('bv. Langebaan') }}">
    @error('town')<span class="error">{{ $message }}</span>@enderror</label>
