<!DOCTYPE html>
<html lang="es" id="lang">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no">
    <title>Carniceria Franco</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('images/logo.jpeg') }}" />
    <script defer src="{{ asset('plugins/cdn/alpinejs/alpine.min.js') }}"></script>
    <script src="{{ asset('assets/js/libs/jquery-3.1.1.min.js') }}"></script>
    <script src="{{ asset('plugins/cdn/bootstrap/bootstrap.bundle.min.js') }}"></script>
    <link href="{{ asset('plugins/cdn/toastr/toastr.min.css') }}" rel="stylesheet">

    @php
        $nonce = app()->bound('nonce') ? app('nonce') : '';
    @endphp
    @if (Request::is('aduanas/modulos-sistema/reportes*'))
        @php
            $nonce = app('nonce');
            header(
                "Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; font-src 'self' data: https:; connect-src 'self' wss: https:;",
                true,
            );
        @endphp
    @endif
    @livewireStyles
    @include('layouts.theme.styles')

</head>

<body class="dashboard-analytics" id="body">
   @livewireScripts(['nonce' => app('nonce')])
    <div id="load_screen">
        <div class="loader">
            <div class="loader-content">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="loader-logo">
                <div class="spinner-grow align-self-center"></div>
            </div>
        </div>
    </div>
    @include('layouts.theme.header')

    <div class="main-container" id="container">

        <div class="overlay"></div>

        <div class="search-overlay"></div>
        @include('layouts.theme.sidebar')
        <div id="content" class="main-content">
            <div class="layout-px-spacing">

                @yield('content')
            </div>
            @include('layouts.theme.footer')
        </div>
    </div>
    @include('layouts.theme.scripts')
    @stack('scripts')
</body>

</html>
