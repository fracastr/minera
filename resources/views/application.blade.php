<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="session-lifetime" content="{{ (int) config('session.lifetime') }}">

  <!-- SEO Meta Tags -->
  <title>Sondek - Sistema de Gestión CMP Balances | Administración Minera</title>
  <meta name="description" content="Sistema integral de gestión para balances mineros CMP. Administre balances, reportes y análisis financieros de manera eficiente. Plataforma web especializada en minería.">
  <meta name="keywords" content="sistema minero, balances CMP, gestión minera, reportes financieros, administración minera, Sondek, CMP Balances">
  <meta name="author" content="Sondek">
  <meta name="robots" content="index, follow">

  <!-- Open Graph / Facebook -->
  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:title" content="Sondek - Sistema de Gestión CMP Balances">
  <meta property="og:description" content="Sistema integral de gestión para balances mineros CMP. Administre balances, reportes y análisis financieros de manera eficiente.">
  <meta property="og:image" content="{{ asset('images/logo/logo.png') }}">

  <!-- Twitter -->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="{{ url()->current() }}">
  <meta property="twitter:title" content="Sondek - Sistema de Gestión CMP Balances">
  <meta property="twitter:description" content="Sistema integral de gestión para balances mineros CMP. Administre balances, reportes y análisis financieros de manera eficiente.">
  <meta property="twitter:image" content="{{ asset('images/logo/logo.png') }}">

  <!-- Canonical URL -->
  <link rel="canonical" href="{{ url()->current() }}">

  <!-- Favicon para Google Search - Debe ser el primero y estar en formato ICO o PNG -->
  <!-- Google requiere que el favicon esté en la raíz y sea accesible públicamente -->
  <link rel="icon" type="image/x-icon" href="{{ url('/favicon.ico') }}">
  <link rel="icon" type="image/png" sizes="32x32" href="{{ url('/images/logo/favicon-32x32.png') }}">
  <link rel="icon" type="image/png" sizes="16x16" href="{{ url('/images/logo/favicon-16x16.png') }}">

  <!-- Favicon SVG para navegadores modernos (solo para uso en navegador, no para Google) -->
  <link rel="icon" type="image/svg+xml" href="{{ asset('images/logo/favicon-chart.svg') }}">

  <!-- Apple Touch Icon -->
  <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/apple-touch-icon.png') }}">

  <!-- Android Chrome Icons -->
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/logo/android-chrome-192x192.png') }}">
  <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/logo/android-chrome-512x512.png') }}">

  <!-- Manifest para PWA -->
  <link rel="manifest" href="{{ asset('images/logo/site.webmanifest') }}">

  <!-- Match the splash to the user's saved skin before anything paints. -->
  <script>try { if (localStorage.getItem('vuexy-skin') === 'dark') document.documentElement.classList.add('loader-dark') } catch (e) {}</script>

  <!-- Splash Screen/Loader Styles -->
  <link rel="stylesheet" type="text/css" href="{{ asset(mix('css/loader.css')) }}" />

  <!-- Styles -->
  <link rel="stylesheet" href="{{ asset(mix('css/app.css')) }}">

  <!-- Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Newsreader:opsz,wght@6..72,300;6..72,400&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap"
    rel="stylesheet">
</head>

<body>
  <noscript>
    <strong>El Sistema Sondek CMP Balances requiere JavaScript para funcionar correctamente.
    Por favor, habilite JavaScript en su navegador para continuar.</strong>
  </noscript>

  <div id="loading-bg" role="status" aria-live="polite">
    <img class="loading-logo" src="{{ asset('logo.png') }}" alt="CMP" />
    <div class="loading-bar"></div>
    <span class="loading-text">Cargando…</span>
  </div>

  <div id="app">
  </div>

  <script src="{{ asset(mix('js/app.js')) }}"></script>

</body>

</html>
