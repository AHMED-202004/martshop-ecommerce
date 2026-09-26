<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer"><title>@yield('title') — Mart.ps</title>
  <link rel="stylesheet" href="{{ asset('assets/private.css') }}">
  @stack('styles')
</head>
<body><main>
  <nav><a href="{{ route('orders.history') }}">سجل طلباتي</a>
    @if(auth()->user()->hasPermission('staff-tasks.view-own'))<a href="{{ route('staff-tasks.index') }}">مهامي</a>@endif
    @if(auth()->user()->hasPermission('staff-departments.view-managed'))<a href="{{ route('staff-departments.managed') }}">قسمي</a>@endif
    @if(app(\App\Services\AdminDashboardService::class)->canAccess(auth()->user()))<a href="{{ route('admin.dashboard') }}">لوحة الإدارة</a>@endif
    @if(auth()->user()->hasPermission('refund-destinations.review'))<a href="{{ route('admin.refund-destinations.index') }}">التحقق من وسائل الاسترداد</a>@endif
    @if(auth()->user()->hasPermission('refunds.review'))<a href="{{ route('admin.refunds.index') }}">قرارات الاسترداد</a>@endif
    @if(auth()->user()->hasPermission('refunds.pay'))<a href="{{ route('admin.refund-transfers.index') }}">تحويلات الاسترداد</a>@endif
  </nav>
  @php
    $adminNavigation = app(\App\Services\AdminDashboardService::class)->navigation(auth()->user());
  @endphp
  @if($adminNavigation)
    @include('partials.admin-navigation', ['links' => $adminNavigation])
  @endif
  <h1>@yield('title')</h1>
  <p class="notice">@yield('privacy-notice', 'صفحة مالية خاصة. لا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP. التحقق هنا يدوي ولا يُجري تحويلًا.')</p>
  @if(session('success'))<p role="status" class="notice">{{ session('success') }}</p>@endif
  @if($errors->any())<div role="alert" class="error">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
  @yield('content')
</main></body>
</html>
