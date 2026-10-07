@php($me = auth()->user())
<!DOCTYPE html>
<html lang="af">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#16324F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Tribe">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}Tribe</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="stylesheet" href="/css/app.css?v={{ filemtime(public_path('css/app.css')) }}">
    <script src="/js/app.js?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    @stack('scripts')
</head>
<body class="{{ $me?->large_text ? 'large' : '' }}">
    <a class="skip" href="#main">{{ __('Gaan na die inhoud') }}</a>

    <main id="main" class="page">
        @if (session('status'))
            <p class="flash" role="status">{{ session('status') }}</p>
        @endif

        {{ $slot }}
    </main>

    @auth
        <nav class="tabs" aria-label="{{ __('Hoofkeuses') }}">
            @foreach ([
                ['home', 'Tuis', 'home'],
                ['calendar', 'Kalender', 'calendar'],
                ['lists.index', 'Lyste', 'list'],
                ['grandchildren.index', 'Kleinkinders', 'heart'],
                ['more', 'Meer', 'more'],
            ] as [$route, $label, $icon])
                @php($active = request()->routeIs($route) || request()->routeIs(explode('.', $route)[0].'.*')
                    || ($route === 'calendar' && request()->routeIs('events.*'))
                    || ($route === 'more' && request()->routeIs('contacts.*', 'family.*', 'settings')))
                <a href="{{ route($route) }}" @class(['tab', 'is-active' => $active]) @if ($active) aria-current="page" @endif>
                    @include('partials.icon', ['name' => $icon])
                    <span>{{ __($label) }}</span>
                </a>
            @endforeach
        </nav>
    @endauth
</body>
</html>
