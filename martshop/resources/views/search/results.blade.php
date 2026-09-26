@extends('layouts.app')

@section('content')
<div class="container py-4">
  <h1 class="h5 mb-3">نتائج البحث عن: "{{ $q }}"</h1>

  @if ($products->count())
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      @foreach ($products as $p)
        <div class="product-card">
          <a href="{{ route('product.show', $p->slug) }}">
            <div class="img-wrap">
              <img src="{{ asset($p->image ?? 'assets/img/placeholder.png') }}" alt="{{ $p->name }}">
            </div>
            <div class="p-body">
              <div class="brand">{{ $p->brand->name ?? '' }}</div>
              <div class="p-name">{{ $p->name }}</div>
              <div class="price">
                <div class="price-now">₪{{ number_format($p->sale_price ?? $p->price, 2) }}</div>
                @if(!is_null($p->sale_price))
                  <div class="price-old">₪{{ number_format($p->price, 2) }}</div>
                @endif
              </div>
            </div>
          </a>
        </div>
      @endforeach
    </div>

    <div class="mt-3">
      {{ $products->links() }}
    </div>
  @else
    <p>لا توجد نتائج مطابقة.</p>
  @endif
</div>
@endsection
