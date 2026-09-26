@extends('layouts.app')
@section('title', 'طلبات التاجر - Mart.ps')

@section('content')
@php
  $labels = [
    'pending_confirmation' => 'بانتظار تأكيدك',
    'confirmed' => 'مؤكد',
    'rejected' => 'مرفوض',
    'expired' => 'انتهت مهلة التأكيد',
    'cancelled' => 'ملغي',
  ];
@endphp
<div class="container rtl page-pad">
  <div class="page-head">
    <div>
      <h1>طلبات التاجر</h1>
      <p>راجع السعر والمخزون قبل انتهاء مدة الحجز.</p>
    </div>
    <a class="btn" href="{{ route('merchant.catalog.index') }}">منتجاتي وعروضي</a>
  </div>

  @if(session('success')) <div class="notice success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="notice error">{{ $errors->first() }}</div> @endif

  <div class="panel table-wrap">
    <table>
      <thead><tr><th>الطلب الفرعي</th><th>الطلب الرئيسي</th><th>العميل</th><th>العناصر</th><th>الإجمالي</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
      @forelse($orders as $merchantOrder)
        <tr>
          <td>#{{ $merchantOrder->id }}</td>
          <td>#{{ $merchantOrder->order_id }}</td>
          <td>{{ $merchantOrder->order->user->first_name ?: $merchantOrder->order->user->name }}</td>
          <td>{{ $merchantOrder->items->sum('qty') }}</td>
          <td>₪{{ number_format((float) $merchantOrder->total, 2) }}</td>
          <td>{{ $labels[$merchantOrder->status->value] ?? $merchantOrder->status->value }}</td>
          <td><a class="btn btn-primary" href="{{ route('merchant.orders.show', $merchantOrder) }}">عرض</a></td>
        </tr>
      @empty
        <tr><td colspan="7">لا توجد طلبات للتاجر حتى الآن.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $orders->links() }}
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-orders.css') }}">
@endpush
