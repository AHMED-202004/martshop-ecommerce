@extends('layouts.app')
@section('title','
عروض سوبر - Mart.ps')

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
<div class="container deals-page">
  <div class="deals-breadcrumb">
    <a href="{{ url('/') }}">الرئيسية</a> <span>›</span> Super Deals
  </div>

  <div class="deals-layout">
    {{-- الفلاتر --}}
    <aside class="filters-panel">
      <div class="mini-cats">
        <button type="button" id="catsToggle">
          <i class="fa-solid fa-bars"></i>
          <span>التصنيفات</span>
        </button>

        <div class="mini-cats-panel" id="catsPanel">
          <ul>
            <li><a href="{{ route('deals.index') }}"><i class="fa-regular fa-gem"></i> Super Deals</a></li>
            <li><a href="/c/men"><i class="fa-solid fa-table-list"></i> قسم الرجال</a></li>
            <li><a href="/c/women"><i class="fa-solid fa-table-list"></i> قسم النساء</a></li>
            @include('partials.category-navigation-links', ['categories' => $navigationCategories])
          </ul>
        </div>
      </div>

      <form id="filtersForm" method="GET" action="{{ route('deals.index') }}">
        <div class="filters-head">
          <strong>خيارات</strong>
          <span class="filters-head-spacer" aria-hidden="true"></span>
        </div>

        <div class="filter-box">
          <div class="filter-title">الماركة</div>
          <div class="filter-grid">
            @foreach($brands as $b)
              <label class="filter-item">
                <span>{{ $b->name }}</span>
                <input type="checkbox" name="brand[]" value="{{ $b->id }}" {{ in_array($b->id,$brandIds) ? 'checked' : '' }}>
              </label>
            @endforeach
          </div>
        </div>

        <div class="filter-box">
          <div class="filter-title">اللون</div>
          <div class="filter-grid">
            @foreach($availableColors as $c)
              <label class="filter-item">
                <span>{{ $c }}</span>
                <input type="checkbox" name="color[]" value="{{ $c }}" {{ in_array($c,$colors) ? 'checked' : '' }}>
              </label>
            @endforeach
          </div>
        </div>

        <div class="filter-box">
          <div class="filter-title">الأحجام</div>
          <div class="filter-grid">
            @foreach($alphaList as $s)
              <label class="filter-item">
                <span>{{ $s }}</span>
                <input type="checkbox" name="alpha[]" value="{{ $s }}" {{ in_array($s,$alphaSizes) ? 'checked' : '' }}>
              </label>
            @endforeach
          </div>
        </div>

        <div class="filter-box">
          <div class="filter-title">القياس</div>
          <div class="filter-grid">
            @foreach($numList as $n)
              <label class="filter-item">
                <span>{{ rtrim(rtrim(number_format($n,1,'.',''), '0'),'.') }}</span>
                <input type="checkbox" name="num[]" value="{{ $n }}" {{ in_array((string)$n,$numSizes) ? 'checked' : '' }}>
              </label>
            @endforeach
          </div>
        </div>

        <div class="filter-actions">
          <button type="submit" class="btn btn-primary-outline">تطبيق</button>
          <a href="{{ route('deals.index') }}" class="btn filter-reset">إعادة ضبط</a>
        </div>
      </form>
    </aside>

    {{-- النتائج --}}
    <section class="products-area">
      <div class="results-bar">
        <div>عدد النتائج: {{ $products->total() }}</div>
      </div>

      <div class="product-grid">
        @forelse($products as $p)
         @php
  $offer = $p->offers->first();
  $priceNow = $offer ? (float) $offer->price : (float) $p->final_price;
  $compareAtPrice = $offer?->compare_at_price !== null
    ? (float) $offer->compare_at_price
    : ($p->sale_price !== null && (float) $p->price > $priceNow ? (float) $p->price : null);
  $hasSale = $compareAtPrice !== null && $compareAtPrice > $priceNow;
  $offerSizes = $offer?->variants?->map(fn ($variant) => $variant->attributes['size'] ?? null)->filter() ?? collect();
  $alpha = $offerSizes->isNotEmpty()
    ? $offerSizes->reject(fn ($size) => is_numeric($size))->unique()->values()
    : $p->variants->where('size_type','alpha')->pluck('size_value')->unique()->values();
  $nums = $offerSizes->isNotEmpty()
    ? $offerSizes->filter(fn ($size) => is_numeric($size))->unique()->values()
    : $p->variants->where('size_type','num')->pluck('size_value')->unique()->values();
  $img       = $p->image ? asset($p->image) : asset('assets/img/placeholder.png');

@endphp

          <article class="product-card">
            <div class="img-wrap">
              <a href="{{ url('/p/'.$p->slug) }}" class="img-link">
                <img src="{{ $img }}" alt="{{ $p->name }}">
              </a>

              @if($hasSale)
                <span class="flash">٪</span>
            @endif

            </div>

            <div class="p-body">
              <div class="price">
                <strong>₪{{ number_format($priceNow,2) }}</strong>
                @if($hasSale)
                  <span class="price-old">₪{{ number_format($compareAtPrice,0) }}</span>
                @endif
              </div>

              <a class="p-name" href="{{ url('/p/'.$p->slug) }}">{{ $p->name }}</a>

              @if($alpha->isNotEmpty())
                <div class="chips">
                  @foreach($alpha as $s) <span class="chip">{{ $s }}</span> @endforeach
                </div>
              @endif

              @if($nums->isNotEmpty())
                <div class="chips">
                  @foreach($nums as $n)
                    <span class="chip">{{ rtrim(rtrim(number_format($n,1,'.',''), '0'),'.') }}</span>
                  @endforeach
                </div>
              @endif
            </div>
          </article>
        @empty
          <div class="empty">لا توجد نتائج مطابقة للفلاتر الحالية.</div>
        @endforelse
      </div>

      <div class="pager">
        {{ $products->links() }}
      </div>
    </section>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/deals.css') }}">
@endpush
