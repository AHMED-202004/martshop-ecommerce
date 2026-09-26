{{-- resources/views/product/show.blade.php --}}
@extends('layouts.app')
@section('title', $product->name.' - Mart.ps')

@section('content')
<div class="container product-page">
  {{-- مسار علوي --}}
  <div class="crumbs product-crumbs">
    <a href="{{ url('/') }}">الرئيسية</a> <span>›</span>
    <a href="{{ url('/c/shoes/men') }}">المنتجات</a> <span>›</span>
    <span>{{ $product->name }}</span>
  </div>

  <div class="pd-wrap">
    {{-- الصورة --}}
    <div class="pd-media">
      @if($hasSale)
        <span class="badge-off" aria-label="خصم">خصم</span>
      @endif
      <img src="{{ $imageUrl }}" alt="{{ $product->name }}">
    </div>

    {{-- التفاصيل --}}
    <div class="pd-info" data-size="">
      <h1 class="pd-title">{{ $product->name }}</h1>

      <div class="pd-meta">
        @if(!empty($product->sku))
          <div>رقم المنتج: <strong>{{ $product->sku }}</strong></div>
        @else
          <div>رقم المنتج: <strong>{{ $product->id }}</strong></div>
        @endif

        @if($product->brand)
          <div>الماركة: <strong>{{ $product->brand->name }}</strong></div>
        @endif
      </div>

      {{-- السعر --}}
      <div class="pd-price">
        <strong class="now">₪{{ number_format($priceNow, 2) }}</strong>
        @if($hasSale)
          <span class="old">₪{{ number_format($compareAtPrice ?? $product->price, 0) }}</span>
        @endif
      </div>

      @if($offerVariants->isNotEmpty())
        <div class="pd-block">
          <div class="lbl">الخيارات المتاحة</div>
          <div class="chips">
            @foreach($offerVariants as $variant)
              @php
                $labels = ['size' => 'المقاس', 'color' => 'اللون'];
                $choice = collect($variant->attributes ?? [])
                  ->filter(fn ($value) => $value !== null && $value !== '')
                  ->map(fn ($value, $key) => ($labels[$key] ?? $key).': '.$value)
                  ->implode(' — ');
              @endphp
              <button type="button" class="chip variant-chip"
                      data-variant-id="{{ $variant->id }}"
                      data-size="{{ $variant->attributes['size'] ?? '' }}"
                      data-color="{{ $variant->attributes['color'] ?? '' }}"
                      data-price="{{ $variant->price ?? $priceNow }}">
                {{ $choice !== '' ? $choice : 'الخيار المتاح' }}
              </button>
            @endforeach
          </div>
        </div>
      @else
      {{-- الألوان --}}
      @if($colors->isNotEmpty())
        <div class="pd-block">
          <div class="lbl">اللون</div>
          <div class="clr-swatches" role="list">
            @foreach($colors as $c)
              @php
                $title = is_string($c) ? $c : (string)$c;
                $map = [
                  'black'=>'#000','أسود'=>'#000',
                  'white'=>'#fff','أبيض'=>'#fff',
                  'grey'=>'#9aa0a6','gray'=>'#9aa0a6','رمادي'=>'#9aa0a6',
                  'brown'=>'#7a5230','بني'=>'#7a5230',
                  'navy'=>'#0b2545','كحلي'=>'#0b2545',
                  'blue'=>'#2f6fed','أزرق'=>'#2f6fed',
                  'red'=>'#e53935','أحمر'=>'#e53935',
                  'green'=>'#2e7d32','أخضر'=>'#2e7d32',
                  'beige'=>'#d9c7a3','بيج'=>'#d9c7a3','عسلي'=>'#c49a6c',
                  'olive'=>'#6b7d3a','زيتي'=>'#6b7d3a',
                ];
                $hex = $map[mb_strtolower($title)] ?? '#bbb';
              @endphp
              <button type="button" class="swatch color-chip" title="{{ $title }}"
                      aria-label="{{ $title }}" data-color="{{ $title }}"
                      role="listitem"><svg viewBox="0 0 20 20" aria-hidden="true" focusable="false"><rect width="20" height="20" rx="4" fill="{{ $hex }}"/></svg></button>
            @endforeach
          </div>
        </div>
      @endif

      {{-- مقاسات حروف --}}
      @if($alphaSizes->isNotEmpty())
        <div class="pd-block">
          <div class="lbl">المقاسات</div>
          <div class="chips">
            @foreach($alphaSizes as $s)
              <button type="button" class="chip size-chip" data-size="{{ $s }}">{{ $s }}</button>
            @endforeach
          </div>
        </div>
      @endif

      {{-- مقاسات أرقام --}}
      @if($numSizes->isNotEmpty())
        <div class="pd-block">
          <div class="lbl">المقاسات (أرقام)</div>
          <div class="chips">
            @foreach($numSizes as $n)
              @php $val = rtrim(rtrim(number_format($n,1,'.',''), '0'),'.'); @endphp
              <button type="button" class="chip size-chip" data-size="{{ $val }}">{{ $val }}</button>
            @endforeach
          </div>
        </div>
      @endif
      @endif
{{-- أزرار --}}
<div class="pd-actions">
  <form action="{{ route('cart.add') }}" method="POST" class="d-inline-block js-add-cart">
    @csrf
    <input type="hidden" name="id"    value="{{ $product->id ?? $product->slug }}">
    <input type="hidden" name="slug"  value="{{ $product->slug }}">
    <input type="hidden" name="offer_id" value="{{ $offer?->id }}">
    <input type="hidden" name="offer_variant_id" id="offerVariantHidden" value="">

    {{-- لو في مقاسات: سنملأه من السكربت عند النقر على المقاس --}}
    <input type="hidden" name="options[size]" id="prodSizeHidden" value="">
    <input type="hidden" name="options[color]" id="prodColorHidden" value="">

    <button type="submit" class="btn btn-primary" aria-label="أضف للسلة">
      أضف للسلة
    </button>
  </form>

  <button id="askBtn" class="btn btn-outline" data-contact-url="{{ route('contact.create') }}" aria-label="اسأل عن المنتج">
    اسأل عن المنتج
  </button>
</div>



      {{-- معلومات إضافية --}}
      <div class="pd-extra">
        <div class="row">
          <div class="th">طرق الدفع</div>
          <div class="td">تحويل يدوي عبر وسائل الدفع المفعلة، بعد تأكيد توفر المنتجات.</div>
        </div>

        <div class="row">
          <div class="th">التوصيل</div>
          <div class="td">{{ $deliveryText }}</div>
        </div>

        <div class="row">
          <div class="th">سياسة التبديل/الإرجاع</div>
          <div class="td">
            طلبات ما بعد البيع تخضع للمراجعة وحالة المنتج. راجع <a href="{{ route('policies') }}#returns">سياسة الاسترداد والنزاعات</a>.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/product.css') }}">
@endpush
