<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#211d1b">
    <meta name="description" content="LooksMaxxing Fitness Academy in Tondo, Manila. Find your training plan and build a stronger routine.">
    <title>@yield('title', 'LooksMaxxing Fitness Academy')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <a class="brand" href="{{ route('home') }}" aria-label="LooksMaxxing Fitness Academy home">
        <span class="brand-symbol">LX<span></span></span>
        <span>LOOKSMAXXING<small>FITNESS ACADEMY</small></span>
    </a>
    <nav aria-label="Main navigation">
        <a href="{{ route('home') }}#plans">Memberships</a>
        <a href="{{ route('home') }}#location">Find us</a>
        <a href="{{ route('membership.create') }}">Join the academy</a>
        @auth
            <a class="button button-small" href="{{ route('dashboard') }}">Dashboard <span aria-hidden="true">↗</span></a>
        @else
            <a class="button button-small" href="{{ route('login') }}">Staff access <span aria-hidden="true">↗</span></a>
        @endauth
    </nav>
</header>
<main id="main">@yield('content')</main>
<footer class="site-footer"><a class="footer-name" href="{{ route('home') }}">LOOKSMAXXING<span>✳</span></a><p>Fitness Academy · Tondo, Manila</p><span>SHOW UP FOR YOURSELF.</span></footer>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
