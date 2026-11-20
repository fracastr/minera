<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="robots" content="noindex, nofollow" />
  <title>Sondek - CMP Balances</title>
  <link rel="icon" type="image/x-icon" href="{{ url('/favicon.ico') }}">
  <script>try { if (localStorage.getItem('vuexy-skin') === 'dark') document.documentElement.classList.add('loader-dark') } catch (e) {}</script>
  <style>
    #loading-bg { position: fixed; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1.5rem; background: #f8f7fa; }
    .loader-dark #loading-bg { background: #25293c; }
    #loading-bg img { width: 150px; }
    .loader-dark #loading-bg img { filter: brightness(0) invert(1); opacity: .9; }
    #loading-bg .bar { position: relative; width: 160px; height: 3px; border-radius: 3px; background: rgba(0,0,0,.08); overflow: hidden; }
    #loading-bg .bar::after { content: ''; position: absolute; inset: 0 auto 0 0; width: 40%; border-radius: 3px; background: #1d4f9c; animation: v3-slide 1.1s cubic-bezier(.65,0,.35,1) infinite; }
    @keyframes v3-slide { from { transform: translateX(-100%); } to { transform: translateX(250%); } }
  </style>
  {{-- Vue 3 / Vuexy v10 app (frontend-v3/), built by its own Vite into public/v3-build. --}}
  {{ \Illuminate\Support\Facades\Vite::useHotFile(public_path('v3.hot'))->useBuildDirectory('v3-build')->withEntryPoints(['resources/js/main.js']) }}
</head>
<body>
  <div id="app">
    <div id="loading-bg" role="status"><img src="{{ asset('logo.png') }}" alt="CMP"><div class="bar"></div></div>
  </div>
</body>
</html>
