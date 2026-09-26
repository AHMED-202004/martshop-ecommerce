@extends('layouts.app')
@section('title','وصلنا حديثًا - Mart.ps')

@section('content')
  <div class="container new-products-page">

    {{-- شريط أدوات أعلى الصفحة (سلة + بحث + زر تصنيفات) --}}
    <div class="np-toolbar">
      <a href="/cart" class="header-cart">
        <span class="badge" id="cartCountTop">{{ $cartCount }}</span>
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="title">المشتريات</span>
      </a>

      <form class="search-bar" action="{{ route('search') }}" method="GET" role="search">
        <input type="search" name="q" maxlength="100" placeholder="ابحث في مارت..." aria-label="ابحث في مارت" required>
        <button type="submit" aria-label="بحث"><i class="fa-solid fa-magnifying-glass"></i></button>
      </form>

      <button type="button" class="btn cats-btn" id="catsBtn"
              aria-controls="catsDrawer" aria-expanded="false">
        <i class="fa-solid fa-bars"></i> التصنيفات
      </button>
    </div>

    <h1 class="page-title">وصلنا حديثًا</h1>

    <div id="catsDrawer" class="cats-drawer" aria-hidden="true">
      <div class="drawer-head">
        <strong>التصنيفات</strong>
        <button type="button" class="close" id="catsClose" aria-label="إغلاق التصنيفات">&times;</button>
      </div>

      <ul class="cat-list">
        <li class="cat-title">
          <a href="{{ route('deals.index') }}"><i class="fa-regular fa-gem"></i> <span>Super Deals</span></a>
        </li>
        <li><a href="/c/men"><i class="fa-solid fa-table-list"></i><span>قسم الرجال</span></a></li>
        <li><a href="/c/women"><i class="fa-solid fa-table-list"></i><span>قسم النساء</span></a></li>
        @include('partials.category-navigation-links', ['categories' => $navigationCategories])
      </ul>
    </div>

    {{-- شبكة المنتجات --}}
    <section class="np-products">
      <div class="product-grid slim">
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

            $img = $p->image ? asset($p->image) : asset('assets/img/placeholder.png');

          @endphp

          <article class="product-card">
            <div class="img-wrap">
              <a href="{{ url('/p/'.$p->slug) }}" class="img-link">
                <img src="{{ $img }}" alt="{{ $p->name }}">
                {{-- لو بدك علامة الخصم فعّل التالي --}}
                {{-- @if($hasSale) <span class="flash">٪</span> @endif --}}
              </a>

            </div>

            <div class="p-body">
              <div class="price">
                <strong>₪{{ number_format($priceNow,2) }}</strong>
                @if($hasSale)
                  <span class="price-old">₪{{ number_format($compareAtPrice,0) }}</span>
                @endif
              </div>

              <a class="p-name" href="{{ url('/p/'.$p->slug) }}">{{ $p->name }}</a>

              {{-- المقاسات (اختياري) --}}
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
          <div class="empty">لا توجد منتجات جديدة حاليًا.</div>
        @endforelse
      </div>

      <div class="pager">
        {{ $products->links() }}
      </div>
    </section>
  </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/new-products.css') }}">
@endpush
