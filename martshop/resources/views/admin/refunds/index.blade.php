@extends('layouts.private-finance')
@section('title', 'مراجعة الاستردادات')
@section('privacy-notice', 'صفحة مالية خاصة للمراجعة فقط. كلمة المرور المطلوبة هي لحساب Mart.ps، ولا يتم من هذه الصفحة تنفيذ تحويل أو طلب كلمة مرور بنك أو محفظة أو رمز OTP.')
@section('content')
<div class="rtl page-narrow-1000 page-center-padded">
  <p>هذه الصفحة للمراجعة فقط. تسجيل الحوالة اليدوية وإثباتها متاح في صفحة تحويلات الاسترداد بصلاحية مستقلة.</p>
  <p>النطاق الحالي: استرداد كامل للطلب الفرعي، يشمل رسوم توصيله وخدمته. لا يدعم مبالغ جزئية أو طلبات حُرّرت مستحقاتها.</p>
  @forelse($refunds as $refund)
    <section class="record-card record-card-break">
      <h2>{{ $refund->reference }}</h2>
      <p>الطلب الفرعي #{{ $refund->dispute->delivery->merchant_order_id }} — {{ $refund->dispute->delivery->reference }}</p>
      <p>الحالة: {{ $refund->status->label() }}</p>
      <p>المبلغ المطلوب للعميل: {{ number_format($refund->amount / 100, 2) }} {{ $refund->currency }}</p>
      <p>المنتجات: {{ number_format($refund->amount_snapshot['product_minor'] / 100, 2) }}، التوصيل: {{ number_format($refund->amount_snapshot['delivery_minor'] / 100, 2) }}، الخدمة: {{ number_format($refund->amount_snapshot['service_minor'] / 100, 2) }}.</p>
      <p>صافي حصة التاجر في هذا الاسترداد: {{ number_format($refund->amount_snapshot['merchant_net_minor'] / 100, 2) }}. يختلف عن إجمالي استرداد العميل.</p>
      @if(auth()->user()->hasPermission('refunds.pay'))<a href="{{ route('admin.refund-transfers.show', $refund) }}">تجهيز التحويل وتسجيل إثباته</a>@endif
      <p>سبب النزاع: {{ $refund->dispute->reason }}</p>
      <p>سبب الاسترداد: {{ $refund->reason }}</p>
      <p>وسيلة الاستلام: {{ $refund->activeDestination?->status->label() ?? 'لا توجد وسيلة حالية' }}</p>
      @if($refund->activeDestination && auth()->user()->hasPermission('refund-destinations.review'))
        <a href="{{ route('refund-destinations.show', $refund->activeDestination) }}">تفاصيل وسيلة الاستلام الخاصة</a>
      @endif
      @if($refund->status === \App\Enums\RefundStatus::Requested)
        <form method="POST" action="{{ route('admin.refunds.review', $refund) }}">@csrf
          <input type="hidden" name="lock_version" value="{{ $refund->lock_version }}">
          <label for="refund-decision-{{ $refund->id }}">قرار المراجعة</label>
          <select id="refund-decision-{{ $refund->id }}" name="decision" required>
            <option value="">اختر القرار</option>
            <option value="approved">الموافقة على المبلغ الكامل — دون تحويل</option>
            <option value="rejected">رفض طلب الاسترداد — دون إغلاق النزاع</option>
          </select>
          <label for="refund-reason-{{ $refund->id }}">سبب القرار (يظهر للعميل؛ لا تكتب بيانات حساسة)</label>
          <textarea class="full-width-field" id="refund-reason-{{ $refund->id }}" name="reason" required minlength="5" maxlength="2000"></textarea>
          <label for="refund-password-{{ $refund->id }}">كلمة مرور حسابك لتأكيد المراجعة</label>
          <input id="refund-password-{{ $refund->id }}" type="password" name="current_password" autocomplete="current-password" required>
          <button class="btn btn-primary">تأكيد قرار المراجعة فقط</button>
        </form>
      @else
        <p>سبب القرار: {{ $refund->review_reason }}</p>
        <p>وقت المراجعة: {{ $refund->reviewed_at?->format('Y-m-d H:i') }}</p>
      @endif
    </section>
  @empty<p>لا توجد طلبات استرداد للمراجعة حاليًا.</p>@endforelse
  {{ $refunds->links() }}
</div>
@endsection

@push('styles')
<style>
main{max-width:1040px}.rtl form{display:grid;gap:8px}.rtl select,.rtl textarea,.rtl input{width:100%}.rtl button{justify-self:start}@media(max-width:520px){.rtl{padding:0!important}.rtl button{justify-self:stretch;width:100%}}
</style>
@endpush
