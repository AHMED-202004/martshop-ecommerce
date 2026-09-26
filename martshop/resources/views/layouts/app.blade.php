<!doctype html>

<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8"/>
  <meta name="cart-count-url" content="{{ route('cart.count') }}">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>@yield('title', $siteSettings['name'] ?? 'Mart.ps')</title>

  {{-- Favicons (ضعِي الملفات في public/icons و public/favicon.ico) --}}
  <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('icons/favicon-32x32.png') }}">
  <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('icons/favicon-16x16.png') }}">
  <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
  <link rel="shortcut icon" href="{{ asset('favicon.ico') }}"> {{-- اختياري --}}
  <meta name="theme-color" content="#ff6a00"> {{-- اختياري --}}

  <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
  <link rel="stylesheet" href="{{ asset('assets/styles.css') }}">
  @stack('styles')
</head>

<body>
  @include('partials.cart')

  {{-- Topbar + Navbar --}}
  @include('partials.header')
  @if(!empty($siteSettings['emergency_notice']))
    <div role="alert" class="site-emergency-notice">{{ $siteSettings['emergency_notice'] }}</div>
  @endif
  @if(!($siteSettings['orders_enabled'] ?? true))
    <div role="status" class="site-orders-disabled">
      إنشاء الطلبات الجديدة متوقف مؤقتًا. يمكنك تصفح المنتجات ومراجعة سلتك، لكن لن تتمكن من تأكيد طلب الآن.
    </div>
  @endif

  {{-- Toolbar (بحث + سلة) لكل صفحة تحب تظهره --}}
  @hasSection('toolbar')
    <div class="container site-toolbar-wrap">
      @yield('toolbar')
    </div>
  @endif

  {{-- محتوى الصفحة --}}
  @yield('content')

  @if($siteSettings['chat_enabled'] ?? true)
    @include('partials.chat')
  @endif

  <script src="{{ asset('assets/app.js') }}" defer></script>
  @stack('scripts')
  @include('partials.footer')

</body>
</html>
