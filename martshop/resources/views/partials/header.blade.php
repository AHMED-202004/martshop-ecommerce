<div class="topbar">
  <div class="container">
    <div class="top-links">
     <a href="{{ route('contact.create') }}"><i class="fa-solid fa-phone"></i> اتصل بنا</a>
    </div>

    <div class="top-actions">
      @guest
        {{-- زر يفتح صفحة auth على تبويب تسجيل الدخول --}}
        <a class="btn btn-primary-outline" href="{{ route('login', ['tab' => 'login']) }}">تسجيل الدخول</a>

      @else
        <span class="welcome">مرحبًا {{ auth()->user()->first_name ?? auth()->user()->name }}</span>
        <a class="btn btn-primary-outline" href="{{ route('my-account') }}" @if(request()->routeIs('my-account')) aria-current="page" @endif>حسابي</a>
        @php
          $adminNavigation = app(\App\Services\AdminDashboardService::class)->navigation(auth()->user());
        @endphp
        @if($adminNavigation)
          <a class="btn btn-primary-outline" href="{{ route('admin.dashboard') }}">لوحة الإدارة</a>
        @endif
        @if(auth()->user()->merchant || ($siteSettings['merchant_registration_enabled'] ?? true))
          <a class="btn btn-primary-outline" href="{{ route('merchant.profile.edit') }}">حساب التاجر</a>
        @endif
        @if(auth()->user()->merchant)
          <a class="btn btn-primary-outline" href="{{ route('merchant.catalog.index') }}">منتجاتي وعروضي</a>
          <a class="btn btn-primary-outline" href="{{ route('merchant.orders.index') }}">طلبات التاجر</a>
          <a class="btn btn-primary-outline" href="{{ route('merchant.ledger.index') }}">رصيدي</a>
          <a class="btn btn-primary-outline" href="{{ route('merchant.withdrawals.index') }}">السحوبات</a>
        @endif
        @if($adminNavigation)
          @include('partials.admin-navigation', ['links' => $adminNavigation])
        @endif
        @if(auth()->user()->hasPermission('deliveries.view-own'))
          <a class="btn btn-primary-outline" href="{{ route('delivery.tasks.index') }}">مهام التوصيل</a>
        @endif
        <form action="{{ route('logout') }}" method="post" class="logout-inline">
          @csrf
          <button type="submit" class="btn btn-primary-outline">تسجيل الخروج</button>
        </form>
      @endguest
    </div>
  </div>
</div>

<header class="header">
  <div class="container header-inner">
    <a class="logo" href="{{ url('/') }}">
      <img src="{{ asset('assets/img/123.jpg') }}" alt="Mart.ps"/>
      <span></span>
    </a>

    @php
      $dealsUrl = \Illuminate\Support\Facades\Route::has('deals.index') ? route('deals.index') : url('/deals');
      $isHome  = request()->is('/'); // إن حبيتي: استخدمي routeIs('home') لو عندك اسم مسار للواجهة
      $isDeals = request()->is('deals') || request()->routeIs('deals.*');
    @endphp

    <nav class="main-nav">
      <a href="{{ url('/') }}" class="{{ $isHome ? 'active' : '' }}">الرئيسية</a>
      <a href="{{ $dealsUrl }}" class="{{ $isDeals ? 'active' : '' }}">Super Deals</a>
      <a href="{{ route('new.index') }}" class="{{ request()->routeIs('new.index') ? 'active' : '' }}">وصلنا حديثًا</a>
      <a href="{{ route('policies') }}">سياسات الشركة</a>
      <a href="{{ route('faq') }}">أسئلة شائعة</a>
    </nav>
  </div>
</header>
