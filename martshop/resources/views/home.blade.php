@extends('layouts.app')
@section('title','الرئيسية - Mart.ps')

{{-- الشريط الذي يظهر تحت الهيدر مباشرة --}}
@section('toolbar')
  <div class="hero-toolbar" id="heroToolbar">
    <a href="/cart" class="header-cart">
  <span class="badge cart-count" id="cartCountTop">{{ $cartCount ?? 0 }}</span>
  <i class="fa-solid fa-cart-shopping"></i>
  <span class="title">المشتريات</span>
</a>


    <form class="search-bar" action="{{ route('search') }}" method="GET" role="search">
      <input type="search" name="q" maxlength="100" placeholder="ابحث في مارت..." aria-label="ابحث في مارت" required>
      <button type="submit" aria-label="بحث"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
  </div>
@endsection

@section('content')
  {{-- الشبكة: سلايدر + تصنيفات --}}
  <main class="container main-grid">
    {{-- السلايدر (يسار) --}}
    <section class="hero">
      <div class="slider" id="slider">
        <div class="slide active">
          <img src="{{ asset('assets/img/slide1.PNG') }}" alt="عرض 1">
          <div class="slide-caption">
            <h2>التشكيلة من ساعات </h2>
            <p>أسعار تبدأ من <span class="price">₪39.99</span></p>
          </div>
        </div>
        <div class="slide">
          <img src="{{ asset('assets/img/slide2.PNG') }}" alt="عرض 2">
          <div class="slide-caption">
            <h2>عروض إلكترونيات مدهشة</h2>
            <p>خصومات حتى <span class="price">50%</span></p>
          </div>
        </div>
        <div class="slide">
          <img src="{{ asset('assets/img/slide3.PNG') }}" alt="عرض 3">
          <div class="slide-caption">
            <h2>أزياء الموسم</h2>
            <p>تسوقي الآن</p>
          </div>
        </div>

        <button class="nav prev" id="prev" aria-label="السابق"><i class="fa-solid fa-angle-right"></i></button>
        <button class="nav next" id="next" aria-label="التالي"><i class="fa-solid fa-angle-left"></i></button>
      </div>
    </section>

    {{-- التصنيفات (يمين) --}}
    <aside class="categories">
      <div class="cat-header">
        <div class="left"><i class="fa-solid fa-location-dot"></i> <span>تصنيفات المنتجات</span></div>
        <div class="right"><i class="fa-solid fa-bars"></i></div>
      </div>

      @php
        $dealsUrl = \Illuminate\Support\Facades\Route::has('deals.index')
                    ? route('deals.index')
                    : url('/deals');
      @endphp

      <ul class="cat-list" id="catList">
        <li class="cat-title">
          <a href="{{ $dealsUrl }}">
            <i class="fa-regular fa-gem"></i> <span>Super Deals</span>
          </a>
        </li>

        <li><a href="/c/men"><i class="fa-solid fa-table-list"></i><span>قسم الرجال</span></a></li>
        <li><a href="/c/women"><i class="fa-solid fa-table-list"></i><span>قسم النساء</span></a></li>
        @foreach($homeCategories as $category)
          <li data-cat="{{ $category['slug'] }}">
            <a href="{{ url('/c/'.$category['slug']) }}">
              <i class="{{ $category['icon'] }}"></i><span>{{ $category['name'] }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    </aside>

    {{-- لوح الميجامنيو --}}
    <div id="megaMenu" class="mega">
      <div class="mega-inner" id="megaContent"></div>
    </div>
  </main>


  

{{-- شريط الماركات --}}
@if($featuredBrands !== [])
  <section class="container brands">
    <div class="brands-grid">
      @foreach($featuredBrands as $brand)
        <a class="brand-card" href="{{ route('brands.show', $brand['slug']) }}" title="{{ $brand['name'] }}">
          <img src="{{ asset($brand['image']) }}" alt="{{ $brand['name'] }}">
        </a>
      @endforeach
    </div>
  </section>
@endif




  {{-- تنبيه سفلي --}}
  @guest
  <div class="auth-hint">
    أنت تتصفح حاليًا دون تسجيل الدخول.
    للاستفادة من العروض قم
    <a href="{{ route('login', ['tab' => 'login']) }}">بتسجيل الدخول الآن</a>.
  </div>
@endguest

@if($megaMenu !== null)
  <div id="martCategoryMenuData" data-menu="{{ json_encode($megaMenu, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}" hidden></div>
@endif

@endsection
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/home.css') }}">
@endpush
