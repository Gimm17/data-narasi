<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="description" content="DataNarasi adalah platform analisis data berbasis AI untuk membersihkan CSV/Excel, membuat visualisasi, dan menghasilkan insight bisnis otomatis dalam Bahasa Indonesia.">
        <meta name="robots" content="index, follow">
        <meta name="theme-color" content="#0d9488">
        <link rel="canonical" href="{{ url()->current() }}">

        <title inertia>{{ config('app.name', 'DataNarasi') }}</title>

        <!-- Open Graph / Social Sharing -->
        <meta property="og:site_name" content="DataNarasi">
        <meta property="og:title" content="DataNarasi — Analisis Data dengan AI">
        <meta property="og:description" content="Upload CSV/Excel, bersihkan data otomatis, buat chart, dan dapatkan narasi insight bisnis dari AI dalam Bahasa Indonesia.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:locale" content="id_ID">

        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="DataNarasi — Analisis Data dengan AI">
        <meta name="twitter:description" content="Platform analisis data AI untuk CSV/Excel, visualisasi otomatis, dan insight bisnis Bahasa Indonesia.">

        <!-- Structured Data -->
        <script type="application/ld+json">
        @verbatim
            {
                "@context": "https://schema.org",
                "@type": "SoftwareApplication",
                "name": "DataNarasi",
                "applicationCategory": "BusinessApplication",
                "operatingSystem": "Web",
                "description": "Platform analisis data berbasis AI untuk membersihkan CSV/Excel, membuat visualisasi, dan menghasilkan insight bisnis otomatis dalam Bahasa Indonesia.",
                "offers": {
                    "@type": "Offer",
                    "price": "0",
                    "priceCurrency": "IDR"
                }
            }
        @endverbatim
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', 'resources/js/Pages/'.$page['component'].'.vue'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
