<!DOCTYPE html>
<html lang="es-CL">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="Balances metalúrgicos reconciliados para las plantas de Compañía Minera del Pacífico.">
  <meta property="og:type" content="website">
  <meta property="og:title" content="Balances CMP | Compañía Minera del Pacífico">
  <meta property="og:image" content="{{ asset('brand/cmp-logo.svg') }}">
  <meta name="session-lifetime" content="{{ (int) config('session.lifetime') }}">
  <meta name="theme-color" content="#252a3f">
  <title>Balances CMP | Compañía Minera del Pacífico</title>
  <link rel="icon" type="image/x-icon" href="{{ url('/favicon.ico') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@125,600;125,700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <script src="{{ asset('app-theme.js') }}"></script>
  {{-- Balances CMP v2 (web/), built by its own Vite into public/app-build. --}}
  {{ \Illuminate\Support\Facades\Vite::useHotFile(public_path('app.hot'))->useBuildDirectory('app-build')->withEntryPoints(['src/main.ts']) }}
</head>
<body>
  <div id="app"></div>
</body>
</html>
