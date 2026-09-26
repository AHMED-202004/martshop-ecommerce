@extends('layouts.app')
@section('title','سلة المشتريات - Mart')

@section('content')
<div class="container rtl cart-page">

  {{-- قالب محتوى التصنيفات (لن يظهر، فقط ننسخه للزر العائم) --}}
<!-- قالب زر التصنيفات ليُنقل إلى الهيدر -->
<template id="catBtnTpl">
  <div class="header-cat-btn" dir="rtl" aria-label="التصنيفات">
    <button class="cat-btn"><span class="dot"></span> التصنيفات</button>
    <div class="cat-panel">
      <ul class="cat-list">
        <li><a href="{{ route('deals.index') }}">Super Deals</a></li>
        <li><a href="{{ url('/c/men') }}">قسم الرجال</a></li>
        <li><a href="{{ url('/c/women') }}">قسم النساء</a></li>
        @include('partials.category-navigation-links', ['categories' => $navigationCategories])
      </ul>
    </div>
  </div>
</template>


  @if(session('success'))
    <div class="alert alert-success" role="alert">{{ session('success') }}</div>
  @endif

 @if($ordersEnabled ?? true)
  {{-- إجراءات تأكيد طلب الشراء --}}
  <h1 class="page-title">إجراءات تأكيد طلب الشراء</h1>

  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="qo-card">
        @include('checkout.partials.identity_and_address')
      </div>
    </div>
  </div>
 @else
  <div class="orders-paused" role="status">
    <strong>تأكيد الطلب غير متاح مؤقتًا.</strong>
    يمكنك مراجعة محتويات السلة وتعديلها، والعودة لاحقًا لإتمام الطلب بعد إعادة تفعيل الخدمة.
  </div>
 @endif


  <h2 class="section-title">ملخص سلة المشتريات</h2>

  @php
    $itemsArr = $items ?? [];
    $calcSubtotal = $subtotal
      ?? collect($itemsArr)->sum(fn($i) => (float)($i['price'] ?? 0) * (int)($i['qty'] ?? 0));
    $calcShipping = $shipping ?? 0;
    $calcGrand    = $grand ?? ($total ?? ($calcSubtotal + $calcShipping));
  @endphp

  @if(empty($itemsArr))
    <div class="card-empty">
      <div class="muted">سلة المشتريات فارغة</div>
      <a href="{{ url('/') }}" class="btn btn-outline">العودة للتسوق</a>
    </div>
  @else
    <div class="cart-summary">
      <div class="cart-table-scroll" tabindex="0" role="region" aria-label="تفاصيل سلة المشتريات">
       <table class="table cart-table">
        <thead>
          <tr>
            <th>المنتج</th>
            <th>التفاصيل</th>
            <th>التوفر</th>
            <th>سعر الوحدة</th>
            <th class="cart-qty-heading">الكمية</th>
            <th>المجموع</th>
            <th class="cart-actions-heading"></th>
          </tr>
        </thead>

        <tbody>
          @foreach($itemsArr as $item)
            <tr>
              <td class="prod">
                @if(!empty($item['image']))
                  <img src="{{ $item['image'] }}" alt="{{ $item['name'] }}">
                @endif
                <div class="name">
                  <strong>{{ $item['name'] }}</strong>
                  @if(!empty($item['slug']))
                    <div><a href="{{ url('/p/'.$item['slug']) }}" class="small text-muted">عرض المنتج</a></div>
                  @endif
                </div>
              </td>

              <td class="small text-muted">
                @if(!empty($item['options']))
                  @foreach($item['options'] as $k=>$v)
                    <div>{{ $k }}: {{ $v }}</div>
                  @endforeach
                @else
                  <div>—</div>
                @endif
                <div class="muted">SKU: {{ $item['id'] }}</div>
              </td>

              <td class="availability-note">يُعاد التحقق عند تأكيد الطلب</td>

              <td>₪{{ number_format((float)$item['price'],2) }}</td>

              <td>
                <form method="POST" action="{{ route('cart.update', $item['rowId']) }}" class="qty-form">
                  @csrf @method('PATCH')
                  <input type="number" name="qty" value="{{ (int)$item['qty'] }}" min="1">
                  <button type="submit" class="btn btn-sm btn-light">تحديث</button>
                </form>
              </td>

              <td><strong>₪{{ number_format((float)$item['price'] * (int)$item['qty'],2) }}</strong></td>

              <td>
                <form method="POST" action="{{ route('cart.remove', $item['rowId']) }}">
                  @csrf @method('DELETE')
                  <button class="icon-trash" title="حذف" aria-label="حذف {{ $item['name'] }} من السلة">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
       </table>
      </div>

      <div class="totals">
        <div><span>المجموع:</span> <strong>₪{{ number_format($calcSubtotal,2) }}</strong></div>
        <div><span>رسوم الشحن:</span> <strong>₪{{ number_format($calcShipping,2) }}</strong></div>
        <div class="grand"><span>المجموع الكلي:</span> <strong>₪{{ number_format($calcGrand,2) }}</strong></div>

        <div class="hint small text-muted">
          @if(($shippingFlatFee ?? 0) <= 0 || ($freeShippingThreshold ?? 0) <= 0)
            التوصيل مجاني وفق الإعدادات الحالية.
          @else
            التوصيل مجاني للطلبات من ₪{{ number_format($freeShippingThreshold, 2) }} فأكثر، ورسومه ₪{{ number_format($shippingFlatFee, 2) }} للطلبات الأقل.
          @endif
        </div>

        <div class="actions">
          <form method="POST" action="{{ route('cart.clear') }}" class="d-inline-block">
            @csrf @method('DELETE')
            <button class="btn btn-outline-secondary">تفريغ السلة</button>
          </form>

        </div>
      </div>
    </div>
  @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/cart.css') }}">
@endpush
