<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <title>@yield('title') — Mart.ps</title>
  <link rel="stylesheet" href="{{ asset('assets/password-recovery.css') }}">
</head>
<body><main>
  <a href="{{ route('login') }}">Mart.ps — تسجيل الدخول</a>
  <h1>@yield('title')</h1>
  @if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
  @if($errors->any())<div class="error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
  @yield('form')
</main></body>
</html>
