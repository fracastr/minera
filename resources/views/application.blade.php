<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="icon" href="{{ URL::asset('favicon.ico') }}" type="image/x-icon"/>
  <!-- <link rel="icon" href="<%= BASE_URL %>favicon.ico"> -->

  <title>Sondek - CMP Balances</title>

  <!-- Splash Screen/Loader Styles -->
  <link rel="stylesheet" type="text/css" href="{{ asset(mix('css/loader.css')) }}" />

  <!-- Styles -->
  <link rel="stylesheet" href="{{ asset(mix('css/app.css')) }}">

  <!-- Favicon -->
  <link rel="shortcut icon" href="{{ asset('images/logo/favicon.png') }}">

  <!-- Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap"
    rel="stylesheet">
</head>

<body>
  <noscript>
    <strong>We're sorry but Vuexy - Vuejs, HTML & Laravel Admin Dashboard Template doesn't work properly without
      JavaScript enabled. Please enable it to continue.</strong>
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
