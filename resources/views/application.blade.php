<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">

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

  <!-- Splash Screen/Loader Styles -->
  <link rel="stylesheet" type="text/css" href="{{ asset(mix('css/loader.css')) }}" />

  <!-- Styles -->
  <link rel="stylesheet" href="{{ asset(mix('css/app.css')) }}">

  <!-- Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap"
    rel="stylesheet">
</head>

<body>
  <noscript>
    <strong>El Sistema Sondek CMP Balances requiere JavaScript para funcionar correctamente.
    Por favor, habilite JavaScript en su navegador para continuar.</strong>
  </noscript>

  <div id="loading-bg">
    <!-- Efectos de partículas adicionales -->
    <div class="particles">
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
      <div class="particle"></div>
    </div>

    <!-- Contenido principal -->
    <div class="loading-content">
      <div class="loading-logo">
        <img src="{{ asset('logo.png') }}" alt="CMP Logo" />
      </div>

      <div class="loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>

      <div class="loading-text">
        <span class="loading-title">Cargando Sistema CMP</span>
        <div class="loading-dots">
          <span class="dot"></span>
          <span class="dot"></span>
          <span class="dot"></span>
        </div>
      </div>

      <!-- Barra de progreso -->
      <div class="progress-container">
        <div class="progress-bar">
          <div class="progress-fill"></div>
        </div>
        <div class="progress-text">Inicializando...</div>
      </div>
    </div>
  </div>

  <div id="app">
  </div>

  <script src="{{ asset(mix('js/app.js')) }}"></script>

  <!-- Script para simular progreso de carga -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const progressFill = document.querySelector('.progress-fill');
      const progressText = document.querySelector('.progress-text');
      const loadingBg = document.getElementById('loading-bg');

      const messages = [
        'Inicializando...',
        'Cargando módulos...',
        'Conectando servicios...',
        'Preparando interfaz...',
        'Sistema listo!'
      ];

      let progress = 0;
      let messageIndex = 0;

      const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;

        progressFill.style.width = progress + '%';

        if (progress >= (messageIndex + 1) * 20 && messageIndex < messages.length - 1) {
          messageIndex++;
          progressText.textContent = messages[messageIndex];
        }

        if (progress >= 100) {
          clearInterval(interval);
          setTimeout(() => {
            loadingBg.classList.add('fade-out');
            setTimeout(() => {
              loadingBg.style.display = 'none';
            }, 500);
          }, 1000);
        }
      }, 200);
    });
  </script>

</body>

</html>
