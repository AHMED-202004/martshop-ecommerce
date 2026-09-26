@extends('layouts.app')
@section('title', 'إثبات الدفع - Mart.ps')

@section('content')
@php
  $paymentLabels = [
    'pending' => 'قيد المراجعة', 'accepted' => 'مقبول', 'rejected' => 'مرفوض',
    'short_amount' => 'المبلغ ناقص', 'overpaid' => 'المبلغ زائد',
    'duplicate' => 'رقم مكرر', 'suspicious' => 'يحتاج تحقق إضافي',
  ];
@endphp
<div class="container rtl payment-page page-pad page-narrow-920">
  <div class="page-head">
    <div><h1>إثبات دفع الطلب #{{ $order->id }}</h1><p>المبلغ المطلوب: <strong>{{ number_format((float) $order->total, 2) }} {{ $order->currency ?: 'ILS' }}</strong></p></div>
    <a class="btn" href="{{ route('orders.history') }}">تاريخ الطلبات</a>
  </div>

  @if(session('success')) <div class="notice success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="notice error">{{ $errors->first() }}</div> @endif

  @if($openPayment)
    <section class="panel">
      <h2>الإثبات قيد المراجعة</h2>
      <p>رقم الإثبات: #{{ $openPayment->id }}</p>
      <p>الحالة: {{ $paymentLabels[$openPayment->status->value] ?? $openPayment->status->value }}</p>
      <p>المبلغ المرسل: {{ number_format($openPayment->amount / 100, 2) }} {{ $openPayment->currency }}</p>
      <p>رقم التحويل: {{ $openPayment->provider_ref }}</p>
      @if($openPayment->proof)<a href="{{ route('payment-proofs.show', $openPayment->proof) }}">عرض الإثبات المرفوع</a>@endif
    </section>
  @elseif($methods->isEmpty())
    <section class="panel"><h2>الدفع غير متاح مؤقتًا</h2><p>لم تُفعّل الإدارة وسيلة تحويل بعد. لن نطلب منك التحويل قبل ظهور بيانات حساب معتمد هنا.</p></section>
  @else
    <form method="POST" action="{{ route('payments.store', $order) }}" enctype="multipart/form-data" class="panel payment-form">
      @csrf
      <input type="hidden" name="idempotency_key" value="{{ $submissionKey }}">

      <h2>1. اختر الحساب المعتمد</h2>
      <div class="methods">
        @foreach($methods as $method)
          <label class="method-card">
            <input type="radio" name="payment_method_id" value="{{ $method->id }}" @checked((string) old('payment_method_id', $methods->first()->id) === (string) $method->id) required>
            <strong>{{ $method->name }}</strong>
            <span>اسم الحساب: {{ $method->account_name }}</span>
            <span>رقم/معرّف الحساب: {{ $method->account_identifier }}</span>
            <small>{{ $method->instructions }}</small>
          </label>
        @endforeach
      </div>

      <h2>2. أدخل تفاصيل التحويل</h2>
      <div class="form-grid">
        <label>رقم العملية/المرجع<input name="reference_number" value="{{ old('reference_number') }}" required maxlength="100"></label>
        <label>المبلغ المحوّل<input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $order->total) }}" required></label>
        <label>تاريخ ووقت التحويل (UTC)<input type="datetime-local" name="transferred_at" value="{{ old('transferred_at', now()->format('Y-m-d\TH:i')) }}" required></label>
        <label>اسم المرسل<input name="sender_name" value="{{ old('sender_name', auth()->user()->name) }}" required maxlength="150"></label>
        <label>هاتف أو حساب المرسل<input name="sender_account" value="{{ old('sender_account', auth()->user()->mobile) }}" required maxlength="100"></label>
        <label>صورة أو PDF للإثبات (حتى 8 ميجابايت)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label>
      </div>
      <p class="privacy">يُحفظ الإثبات في تخزين خاص ولا يظهر للعامة.</p>
      <button class="btn btn-primary" type="submit">إرسال الإثبات للمراجعة</button>
    </form>
  @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/manual-payment.css') }}">
@endpush
