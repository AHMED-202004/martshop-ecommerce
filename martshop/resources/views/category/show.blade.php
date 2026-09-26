@extends('layouts.app')
@section('title', $title.' - التصنيفات')

@section('content')
<div class="container py-4">

  {{-- مسار تنقّل --}}
  <nav class="mb-3 text-sm text-muted">
    @foreach($breadcrumbs as $i => $bc)
      <a href="{{ $bc['href'] }}">{{ $bc['title'] }}</a>
      @if(!$loop->last) <span class="mx-2">›</span> @endif
    @endforeach
  </nav>

  <h1 class="h4 mb-3">{{ $title }}</h1>

  {{-- لو في اختيارات فرعية --}}
  @if(!empty($children))
    <div class="mb-4">
      <div class="d-flex flex-wrap gap-2">
        @foreach($children as $slug => $name)
          <a class="btn btn-outline-secondary btn-sm"
             href="{{ url('/c/'.$path.'/'.$slug) }}">{{ $name }}</a>
        @endforeach
      </div>
    </div>
  @endif

  @php
    // بناء الفلاتر من المنتجات المعروضة
    $brands = []; $colors = []; $sizes = [];

    $inferBrand = function($name){
      $map = [
        'adidas'=>'Adidas','puma'=>'Puma','skechers'=>'Skechers','hush'=>'Hush Puppies',
        'vans'=>'Vans','diadora'=>'Diadora','hi-tec'=>'HI-TEC','reebok'=>'Reebok',
        'golf'=>'GOLF & HORSE','horse'=>'GOLF & HORSE','leather'=>'Leather','جلد'=>'Leather'
      ];
      $low = mb_strtolower($name ?? '');
      foreach($map as $k=>$v){ if(str_contains($low, $k)) return $v; }
      return null;
    };
    $inferColor = function($name){
      $pairs = [
        'أبيض'=>'أبيض','white'=>'White',
        'أسود'=>'أسود','black'=>'Black',
        'رمادي'=>'Grey','grey'=>'Grey','gray'=>'Grey',
        'بني'=>'Brown','brown'=>'Brown',
        'عسلي'=>'Beige','beige'=>'Beige','بيج'=>'Beige',
        'كحلي'=>'Navy','navy'=>'Navy',
        'أزرق'=>'Blue','blue'=>'Blue',
        'أحمر'=>'Red','red'=>'Red',
        'أخضر'=>'Green','green'=>'Green',
        'زيتي'=>'Olive','olive'=>'Olive',
      ];
      $low = mb_strtolower($name ?? '');
      foreach($pairs as $k=>$v){ if(str_contains($low, mb_strtolower($k))) return $v; }
      return null;
    };

    foreach($products as $pp){
      $b = $pp['brand'] ?? $inferBrand($pp['name'] ?? '');
      $c = $pp['color'] ?? $inferColor($pp['name'] ?? '');
      if($b){ $brands[$b] = ($brands[$b] ?? 0) + 1; }
      if($c){ $colors[$c] = ($colors[$c] ?? 0) + 1; }
      foreach(($pp['sizes'] ?? []) as $s){
        $sizes[(string)$s] = ($sizes[(string)$s] ?? 0) + 1;
      }
    }
    ksort($brands); ksort($colors); ksort($sizes, SORT_NATURAL);
  @endphp


@if($products->count())
  <div class="catalog-wrap">

    {{-- الفلاتر يسار (Sticky) --}}
    <aside>
      <div class="filter-card">
        <div class="f-head d-flex align-items-center justify-content-between mb-2">
          <strong>خيارات</strong>
          <button class="btn btn-sm btn-light px-2 py-1" id="clearFilters">مسح الفلاتر</button>
        </div>

        @if(count($brands))
        <div class="f-section">
          <div class="f-title">الماركة</div>
          <div class="f-sep"></div>
          <div class="f-list">
            @foreach($brands as $b => $cnt)
              <label class="f-item">
                <span class="f-count">{{ $cnt }}</span>
                <span class="f-text">{{ $b }}</span>
                <input type="checkbox" class="f-check f-brand" value="{{ $b }}">
              </label>
            @endforeach
          </div>
        </div>
        @endif

        @if(count($colors))
        <div class="f-section">
          <div class="f-title">اللون</div>
          <div class="f-sep"></div>
          <div class="f-list">
            @foreach($colors as $c => $cnt)
              <label class="f-item">
                <span class="f-count">{{ $cnt }}</span>
                <span class="f-text">{{ $c }}</span>
                <input type="checkbox" class="f-check f-color" value="{{ $c }}">
              </label>
            @endforeach
          </div>
        </div>
        @endif

        @if(count($sizes))
        <div class="f-section">
          <div class="f-title">القياس</div>
          <div class="f-sep"></div>
          <div class="f-list f-list--sizes">
            @foreach($sizes as $s => $cnt)
              <label class="f-item f-chip">
                <span class="f-count">{{ $cnt }}</span>
                <span class="f-text">{{ $s }}</span>
                <input type="checkbox" class="f-check f-size" value="{{ $s }}">
              </label>
            @endforeach
          </div>
        </div>
        @endif
      </div>
    </aside>

    {{-- شبكة المنتجات يمين --}}
    <section>
      <div id="gridCount" class="mb-2 small text-muted"></div>
      <div class="product-grid" id="productGrid">
        @foreach($products as $p)
          @php
            $img = Str::startsWith($p['image'] ?? '', ['http','/'])
              ? $p['image']
              : asset($p['image'] ?? 'assets/img/placeholder.png');

            // سمات الفلترة
            $brand = $p['brand'] ?? ($inferBrand($p['name'] ?? '') ?? '');
            $color = $p['color'] ?? ($inferColor($p['name'] ?? '') ?? '');
            $sizesCsv = implode(',', array_map(fn($x)=>(string)$x, $p['sizes'] ?? []));
          @endphp

          <div class="p-card"
               data-product-key="{{ $p['slug'] }}"
               data-brand="{{ $brand }}"
               data-color="{{ $color }}"
               data-sizes="{{ $sizesCsv }}">
            <a href="{{ route('product.show', $p['slug']) ?? '#' }}" class="p-thumb">
              <img src="{{ $img }}" alt="{{ $p['name'] }}">
              @if(!empty($p['old_price']) && $p['old_price'] > $p['price'])
                <span class="p-badge">خصم</span>
              @endif
            </a>

            <div class="p-body">
              <a class="p-title" href="{{ route('product.show', $p['slug']) ?? '#' }}">{{ $p['name'] }}</a>
              <div class="p-price">
                <span class="new">₪{{ number_format($p['price'], 2) }}</span>
                @if(!empty($p['old_price']) && $p['old_price'] > $p['price'])
                  <span class="old">₪{{ number_format($p['old_price'], 0) }}</span>
                @endif
              </div>

              @if(!empty($p['sizes']))
                <div class="p-sizes">
                  @foreach($p['sizes'] as $s)
                    <span class="size">{{ $s }}</span>
                  @endforeach
                </div>
              @endif

              <div class="p-actions">
  <a class="btn-view" href="{{ route('product.show', $p['slug']) ?? '#' }}">عرض المنتج</a>
  <button class="btn-quick js-quick" type="button"><i class="fa-solid fa-eye" aria-hidden="true"></i> عرض سريع</button>
</div>


            </div>
          </div>
        @endforeach
      </div>
    </section>

  </div>
@else
  <div class="alert alert-light">
    لا توجد خيارات إضافية في هذا المستوى.<br>
    لاحقًا سنعرض المنتجات المطابقة لهذا التصنيف هنا.
  </div>
@endif


{{-- مودال العرض السريع --}}
<div id="quickModal" class="q-modal" role="dialog" aria-modal="true" aria-labelledby="qTitle" hidden
     data-products="{{ json_encode($products->keyBy('slug'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) }}"
     data-asset-base="{{ asset('') }}"
     data-product-url-template="{{ route('product.show', '__slug__') }}">
  <div class="q-dialog">
    <button class="q-close" type="button" aria-label="إغلاق العرض السريع">×</button>
    <div class="q-content">
      <div class="q-left">
        <img id="qImg" src="{{ asset('assets/img/placeholder.png') }}" alt="">
      </div>
      <div class="q-right">
        <h3 id="qTitle" class="mb-2"></h3>
        <div class="q-price mb-3">
          <span id="qNew" class="new"></span>
          <span id="qOld" class="old"></span>
        </div>
        <div id="qSizes" class="q-sizes mb-3"></div>
        <div class="d-flex gap-2">
      <a id="qView" href="#" class="btn btn-primary">عرض المنتج</a>

<form id="qAddForm" action="{{ route('cart.add') }}" method="POST" class="d-inline-block">
  @csrf
  <input type="hidden" name="id"    id="qId">
  <input type="hidden" name="slug"  id="qSlug">
  <input type="hidden" name="offer_id" id="qOfferId">
  <input type="hidden" name="offer_variant_id" id="qOfferVariantId">
  <input type="hidden" name="options[size]" id="qSize">
  <input type="hidden" name="options[color]" id="qColor">
  <button type="submit" class="btn btn-outline-dark">إضافة للسلة</button>
</form>


        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/category.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('assets/category.js') }}" defer></script>
@endpush
