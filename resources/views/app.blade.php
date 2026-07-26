<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $themeName ?? 'green' }}" data-app-name="{{ $systemBranding['app_name'] }}" @class(['dark'=> ($appearance ?? 'system') == 'dark'])>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Inline script to detect system dark mode preference and apply it immediately --}}
    <script>
        (function() {
            const appearance = '{{ $appearance ?? "system" }}';

            if (appearance === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (prefersDark) {
                    document.documentElement.classList.add('dark');
                }
            }
        })();
    </script>

    {{-- Deploy-time brand theme tokens from APP_THEME --}}
    <style>
        :root {
            @foreach (($themeVariables['light'] ?? []) as $name => $value)
            {{ $name }}: {{ $value }};
            @endforeach
        }

        html {
            background-color: var(--background);
        }

        .dark {
            @foreach (($themeVariables['dark'] ?? []) as $name => $value)
            {{ $name }}: {{ $value }};
            @endforeach
        }
    </style>

    <title inertia>{{ $systemBranding['app_name'] }}</title>

    @if ($systemBranding['favicon_url'])
    <link rel="icon" href="{{ $systemBranding['favicon_url'] }}">
    @endif
    @if ($systemBranding['apple_touch_icon_url'])
    <link rel="apple-touch-icon" href="{{ $systemBranding['apple_touch_icon_url'] }}">
    @endif

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    @routes
    @vite(['resources/js/app.ts'])

    {{-- Re-apply after Vite CSS so APP_THEME wins the cascade --}}
    <style>
        :root {
            @foreach (($themeVariables['light'] ?? []) as $name => $value)
            {{ $name }}: {{ $value }};
            @endforeach
        }

        .dark {
            @foreach (($themeVariables['dark'] ?? []) as $name => $value)
            {{ $name }}: {{ $value }};
            @endforeach
        }
    </style>

    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

</html>
