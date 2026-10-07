{{ __('Hallo :name', ['name' => $name]) }}

{{ __('Jou kode vir Tribe is:') }} {{ $code }}

{{ __('Die kode werk vir :minutes minute. As jy nie probeer aanmeld het nie, kan jy hierdie e-pos ignoreer.', ['minutes' => \App\Models\LoginCode::VALID_MINUTES]) }}
