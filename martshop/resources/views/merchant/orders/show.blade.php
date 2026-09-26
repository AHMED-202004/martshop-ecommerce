@extends('layouts.app')
@section('title', 'تفاصيل طلب التاجر - Mart.ps')

@section('content')
@php
  $labels = [
    'pending_confirmation' => 'بانتظار تأكيدك',
    'confirmed' => 'مؤكد',
    'rejected' => 'مرفوض',
    'expired' => 'انتهت مهلة التأكيد',
    'cancelled' => 'ملغي',
  ];
  $address = $merchantOrder->order->delivery_address_snapshot ?? [];
@endphp
<div class="container rtl order-page page-pad">
  <div class="page-head">
    <div>
      <h1>طلب التاجر #{{ $merchantOrder->id }}</h1>
      <p>من الطلب الرئيسي #{{ $merchantOrder->order_id }} — {{ $labels[$merchantOrder->status->value] ?? $merchantOrder->status->value }}</p>
    </div>
    <a class="btn" href="{{ route('merchant.orders.index') }}">كل الطلبات</a>
  </div>

  @if(session('success')) <div class="notice success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="notice error">{{ $errors->first() }}</div> @endif

  <div class="grid">
    <section class="panel">
      <h2>بيانات الاستلام</h2>
      <p>{{ $address['recipient_name'] ?? '—' }}</p>
      <p>{{ $address['governorate'] ?? '—' }} — {{ $address['city'] ?? '—' }}</p>
      <p>{{ $address['address'] ?? '—' }}</p>
      <p>{{ $address['mobile'] ?? '—' }}</p>
    </section>
    <section class="panel">
      <h2>ملخص المبلغ</h2>
      <p>المنتجات: ₪{{ number_format((float) $merchantOrder->product_subtotal, 2) }}</p>
      <p>التوصيل: ₪{{ number_format((float) $merchantOrder->delivery_fee, 2) }}</p>
      <p>العمولة المحفوظة: ₪{{ number_format((float) $merchantOrder->commission_amount, 2) }}</p>
      <strong>الإجمالي: ₪{{ number_format((float) $merchantOrder->total, 2) }}</strong>
    </section>
  </div>

  <section class="panel table-wrap">
    <table>
      <thead><tr><th>المنتج</th><th>الخيار</th><th>سعر الوحدة</th><th>الكمية</th><th>المجموع</th></tr></thead>
      <tbody>
      @foreach($merchantOrder->items as $item)
        <tr>
          <td>{{ $item->product_name }}</td>
          <td>
            @foreach(($item->variant_snapshot['attributes'] ?? []) as $key => $value)
              <span>{{ $key }}: {{ $value }}</span>@if(!$loop->last)، @endif
            @endforeach
            @if(empty($item->variant_snapshot['attributes'])) — @endif
          </td>
          <td>₪{{ number_format((float) $item->price, 2) }}</td>
          <td>{{ $item->qty }}</td>
          <td>₪{{ number_format((float) $item->price * $item->qty, 2) }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </section>

  @if($merchantOrder->delivery)
    <section class="panel"><h2>حالة التوصيل</h2><p>{{ ['assigned'=>'تم إسناد المهمة','accepted'=>'قبل عامل التوصيل المهمة'][$merchantOrder->delivery->status->value] ?? $merchantOrder->delivery->status->value }}</p></section>
  @endif

  @can('confirm', $merchantOrder)
    <section class="panel actions">
      <form method="POST" action="{{ route('merchant.orders.confirm', $merchantOrder) }}">
        @csrf
        <label for="confirm-current-password">كلمة المرور الحالية</label>
        <input id="confirm-current-password" name="current_password" type="password" required autocomplete="current-password">
        <button class="btn btn-primary" type="submit">تأكيد السعر والمخزون</button>
      </form>
      <form method="POST" action="{{ route('merchant.orders.reject', $merchantOrder) }}">
        @csrf
        <label for="reject-current-password">كلمة المرور الحالية</label>
        <input id="reject-current-password" name="current_password" type="password" required autocomplete="current-password">
        <label for="reason">سبب الرفض</label>
        <textarea id="reason" name="reason" rows="3" required minlength="5" maxlength="1000">{{ old('reason') }}</textarea>
        <button class="btn btn-danger" type="submit">رفض وإرجاع المخزون</button>
      </form>
    </section>
  @endcan
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-orders.css') }}">
@endpush
