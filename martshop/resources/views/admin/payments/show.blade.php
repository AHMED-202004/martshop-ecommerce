@extends('layouts.private-finance')
@section('title', 'مراجعة الدفعة #'.$payment->id)
@section('privacy-notice', 'قرار مالي خاص. طابق المبلغ والمرجع والإثبات مع المصدر الخارجي، ثم أدخل كلمة مرور حسابك. لا تطلب المنصة كلمة مرور البنك أو رمز OTP.')

@section('content')
@php
  $expected = (int) ($payment->meta['expected_amount_minor'] ?? round((float) $payment->order->total * 100));
  $labels = ['accepted'=>'مقبول','rejected'=>'مرفوض','short_amount'=>'المبلغ ناقص','overpaid'=>'المبلغ زائد','duplicate'=>'رقم مكرر','suspicious'=>'يحتاج تحققًا إضافيًا'];
@endphp

<nav class="page-actions" aria-label="التنقل في مراجعة الدفعات">
  <a href="{{ route('admin.payments.index') }}">كل الدفعات</a>
</nav>

<div class="review-grid">
  <section>
    <h2>الطلب والعميل</h2>
    <p>الطلب: #{{ $payment->order_id }}</p>
    <p>{{ $payment->order->user->name }} — {{ $payment->order->user->email }}</p>
    <p>المطلوب: {{ number_format($expected / 100, 2) }} {{ $payment->currency }}</p>
  </section>
  <section>
    <h2>بيانات التحويل</h2>
    <p>الوسيلة: {{ $payment->method?->name ?? $payment->provider }}</p>
    <p>المبلغ: <strong>{{ number_format($payment->amount / 100, 2) }} {{ $payment->currency }}</strong></p>
    <p>المرجع: {{ $payment->provider_ref }}</p>
    <p>المرسل: {{ $payment->sender_name }} — {{ $payment->sender_account }}</p>
    <p>وقت التحويل: {{ $payment->transferred_at?->format('Y-m-d H:i') }}</p>
  </section>
</div>

<section>
  <h2>الإثبات الخاص</h2>
  @if($payment->proof)
    <a href="{{ route('payment-proofs.show', $payment->proof) }}">تنزيل الإثبات بعد التحقق من سلامته</a>
  @else
    <p>لا يوجد ملف إثبات.</p>
  @endif
</section>

@if($payment->status === \App\Enums\PaymentStatus::Pending)
  <section>
    <h2>حفظ القرار</h2>
    <p class="warning">لا يمكنك مراجعة دفعة تخص حسابك. إدخال كلمة المرور هنا يؤكد هوية المراجع فقط ولا ينفذ تحويلًا خارجيًا.</p>
    <form method="POST" action="{{ route('admin.payments.update', $payment) }}" class="decision-form">
      @csrf
      @method('PATCH')
      <input type="hidden" name="lock_version" value="{{ $payment->lock_version }}">
      <label>القرار
        <select name="decision" required>
          <option value="accepted" @selected(old('decision') === 'accepted')>قبول</option>
          <option value="rejected" @selected(old('decision') === 'rejected')>رفض</option>
          <option value="short_amount" @selected(old('decision') === 'short_amount')>المبلغ ناقص</option>
          <option value="overpaid" @selected(old('decision') === 'overpaid')>المبلغ زائد</option>
          <option value="duplicate" @selected(old('decision') === 'duplicate')>رقم تحويل مكرر</option>
          <option value="suspicious" @selected(old('decision') === 'suspicious')>دفعة مشبوهة</option>
        </select>
      </label>
      <label>الملاحظات
        <textarea name="reason" rows="4" maxlength="2000" placeholder="إلزامية إلا عند القبول">{{ old('reason') }}</textarea>
      </label>
      <label>كلمة مرور حساب المراجع
        <input type="password" name="current_password" required autocomplete="current-password">
      </label>
      <button type="submit">حفظ القرار المالي</button>
    </form>
  </section>
@else
  <section>
    <h2>قرار محفوظ</h2>
    <p>{{ $labels[$payment->status->value] ?? $payment->status->value }}</p>
    @if($payment->review_notes)<p>{{ $payment->review_notes }}</p>@endif
    <p>المراجع: {{ $payment->reviewer?->name ?? '—' }}</p>
  </section>
@endif
@endsection

@push('styles')
<style>
.page-actions{margin-bottom:12px}.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.review-grid section{min-width:0;margin-top:0}.decision-form{display:grid;gap:12px}.warning{background:#fff6dc;border:1px solid #ead087;border-radius:8px;padding:10px}@media(max-width:700px){.review-grid{grid-template-columns:1fr}.decision-form button{width:100%;min-height:44px}}
</style>
@endpush
