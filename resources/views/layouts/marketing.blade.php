<!doctype html>
<html lang="es" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MyTaxEU — Declaraciones fiscales sencillas')</title>
    <meta name="description" content="@yield('meta_description', 'Gestiona tus impuestos en España de forma rápida y segura con MyTaxEU.')">
    <meta property="og:title" content="@yield('og_title', 'MyTaxEU — Declaraciones fiscales sencillas')">
    <meta property="og:description" content="@yield('og_description', 'Gestiona tus impuestos en España de forma rápida y segura con MyTaxEU.')">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="@yield('og_image', asset('images/og-default.jpg'))">
    <meta name="twitter:image" content="@yield('twitter_image', asset('images/og-default.jpg'))">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('twitter_title', 'MyTaxEU — Declaraciones fiscales sencillas')">
    <meta name="twitter:description" content="@yield('twitter_description', 'Gestiona tus impuestos en España de forma rápida y segura con MyTaxEU.')">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkfG3j6S0Z6q+H1VUG9Cw8v1Q5V5N3lFQz6FQxF9mYbG6x1l9Q8c+T8Wg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-full bg-white text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
    @yield('body')
</body>
</html>


