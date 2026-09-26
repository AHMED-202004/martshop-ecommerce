@extends('layouts.app')
@section('title', 'منتجات ماركة ' . $brand->name . ' - Mart.ps')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/brands.css') }}">
@endpush

@section('content')
<div class="container brand-page">

  {{-- مسار بسيط --}}
  <div class="brand-breadcrumbs">
    <a href="{{ url('/') }}">الرئيسية</a> <span>›</span>
    <a href="{{ route('brands.show', $brand->slug) }}">الماركات</a> <span>›</span>
    {{ $brand->name }}
  </div>

  <h1 class="brand-title">منتجات ماركة {{ $brand->name }}</h1>

  @php
    $quickViewProducts = [];
  @endphp
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

        $img = $p->image ? asset($p->image) : asset('assets/img/placeholder.png');

        $qv = [
          'name'        => $p->name,
          'brand'       => optional($p->brand)->name,
          'img'         => $img,
          'price'       => (float) ($compareAtPrice ?? $priceNow),
          'sale'        => (float) $priceNow,
          'hasSale'     => (bool) $hasSale,
          'sizes_alpha' => $alpha,
          'sizes_num'   => $nums,
          'url'         => url('/p/'.$p->slug),
        ];
        $quickViewProducts[$p->slug] = $qv;
      @endphp

      <article class="product-card">
        <div class="img-wrap">
          <a href="{{ url('/p/'.$p->slug) }}" class="img-link">
            <img src="{{ $img }}" alt="{{ $p->name }}">
          </a>

          <button type="button"
                  class="qv-btn"
                  aria-label="عرض سريع"
                  data-qv-key="{{ $p->slug }}">
            عرض سريع
          </button>
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
            <div class="chips chips-spaced">
              @foreach($alpha as $s) <span class="chip">{{ $s }}</span> @endforeach
            </div>
          @endif
          @if($nums->isNotEmpty())
            <div class="chips">
              @foreach($nums as $n)
                <span class="chip">
                  {{ rtrim(rtrim(number_format($n,1,'.',''), '0'),'.') }}
                </span>
              @endforeach
            </div>
          @endif
        </div>
      </article>
    @empty
      <div class="brand-empty">
        لا توجد منتجات لهذه الماركة حاليًا.
      </div>
    @endforelse
  </div>

  <div class="pager">
    {{ $products->links() }}
  </div>
  <div id="martQuickViewProductsData" data-products="{{ json_encode($quickViewProducts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}" hidden></div>
</div>

@endsection
