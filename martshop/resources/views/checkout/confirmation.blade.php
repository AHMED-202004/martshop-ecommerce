@extends('layouts.app')
@section('title','تأكيد الطلب - Mart.ps')
@section('content')
<div class="rtl order-confirmation">
  <div class="order-confirmation-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></div>
  <h2>تم إنشاء طلبك بنجاح</h2>
  <p>تم إنشاء طلبك وحجز الكميات مؤقتًا. الطلبات التابعة للتجار بانتظار تأكيد السعر والمخزون.</p>
  <div class="order-confirmation-actions">
    @if($order->status === \App\Enums\OrderStatus::Confirmed)
      <a href="{{ route('payments.create', $order) }}" class="btn btn-primary">عرض تعليمات الدفع</a>
    @endif
    <a href="{{ route('orders.history') }}" class="btn btn-primary">مشاهدة تاريخ الطلبات</a>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/order-confirmation.css') }}">
@endpush
