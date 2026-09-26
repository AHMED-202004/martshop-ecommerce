@extends('layouts.private-finance')
@section('title', 'تفاصيل وسيلة استلام خاصة')
@section('content')
@php
  $refund = $destination->refund;
  $recipient = $destination->recipient_snapshot;
  $ownerId = $refund->dispute->delivery->order->user_id;
  $reviewable = $destination->status === \App\Enums\RefundDestinationStatus::Pending
    && in_array($refund->status, [\App\Enums\RefundStatus::Requested, \App\Enums\RefundStatus::Approved], true)
    && $refund->dispute->status === 'open' && !$refund->dispute->delivery->settled_at;
@endphp
<section>
  <h2>{{ $refund->reference }} — وسيلة #{{ $destination->id }}</h2>
  <p>الحالة: {{ $destination->status->label() }}</p>
  <p>النوع: {{ $recipient['type'] === 'bank_account' ? 'حساب بنكي' : 'محفظة إلكترونية' }}</p>
  <p>الجهة: {{ $recipient['provider_name'] }}</p>
  <p>صاحب الحساب: {{ $recipient['account_name'] }}</p>
  <p>رقم الحساب أو المحفظة: <bdi>{{ $recipient['account_identifier'] }}</bdi></p>
  @if($destination->review_notes)<p>نتيجة التحقق: {{ $destination->review_notes }}</p>@endif
  @if($destination->reviewed_at)<p>وقت التحقق: {{ $destination->reviewed_at->format('Y-m-d H:i') }}</p>@endif
  @if($destination->revoked_at)<p>وقت إلغاء الاستخدام: {{ $destination->revoked_at->format('Y-m-d H:i') }}</p>@endif
  @if(auth()->id() === $ownerId)<a href="{{ route('refund-destinations.index', $refund) }}">العودة إلى وسائل هذا الاسترداد</a>@endif
</section>
@if(auth()->user()->hasPermission('refund-destinations.review') && auth()->id() !== $ownerId && $reviewable)
  <section>
    <h2>تسجيل نتيجة التحقق اليدوي</h2>
    <p>يجب التحقق مستقلًا من أن رقم الحساب والاسم يخصّان عميل الطلب. إدخال العميل للبيانات أو إرفاقه صورة لا يكفي وحده. لا تطلب أسرار الدخول أو رمز OTP.</p>
    <form method="POST" action="{{ route('admin.refund-destinations.review', $destination) }}">@csrf
      <input type="hidden" name="lock_version" value="{{ $destination->lock_version }}">
      <label for="decision">القرار</label><select id="decision" name="decision" required><option value="">اختر القرار</option><option value="verified">توثيق ملكية الوسيلة</option><option value="rejected">رفض الوسيلة</option></select>
      <label><input name="ownership_confirmed" type="checkbox" value="1"> تحققت مستقلًا من ملكية العميل لهذه الوسيلة (إلزامي للتوثيق)</label>
      <label for="review-notes">سبب القرار وطريقة التحقق (يظهر للعميل؛ لا تكتب بيانات سرية)</label><textarea id="review-notes" name="review_notes" minlength="5" maxlength="2000" required></textarea>
      <label for="review-password">كلمة مرور حسابك في Mart.ps لتأكيد القرار</label><input id="review-password" name="current_password" type="password" autocomplete="current-password" required>
      <button>حفظ نتيجة التحقق فقط</button>
    </form>
  </section>
@endif
@endsection
