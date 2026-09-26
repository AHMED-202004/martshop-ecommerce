@extends('layouts.private-finance')
@section('title', 'التسويات والنزاعات')
@section('privacy-notice', 'صفحة مالية خاصة. فتح النزاع أو إغلاقه يغيّر حالة حجز مستحقات التاجر ويتطلب كلمة مرور حساب الموظف، ولا ينفذ تحويلًا مصرفيًا.')
@section('content')
<div class="rtl page-narrow-1050 page-center-block">
  <p>التحرير التلقائي: {{ $autoRelease ? 'مفعّل بعد انتهاء المهلة' : 'معطّل' }}. السحب إعداد مستقل.</p>
  @forelse($deliveries as $delivery)
    <section class="record-card">
      <h2>{{ $delivery->reference }} — طلب #{{ $delivery->merchant_order_id }}</h2>
      <p>التسليم: {{ $delivery->delivered_at?->format('Y-m-d H:i') }}</p>
      <p>أقرب موعد للتحرير: {{ $delivery->settlement_due_at?->format('Y-m-d H:i') ?? 'غير محدد؛ يحتاج مراجعة' }}</p>
      <p>الحالة: {{ $delivery->dispute?->status === 'refunded' ? 'أُعيد المبلغ للعميل حسب الحوالة المسجّلة' : ($delivery->settled_at ? 'تم تحرير المستحقات' : ($delivery->dispute?->status === 'open' ? 'محجوزة بسبب نزاع' : 'معلّقة')) }}</p>
      @if($delivery->dispute)
        <p>سبب النزاع: {{ $delivery->dispute->reason }}</p>
        @if($delivery->dispute->status === 'refunded')
          <p>{{ $delivery->dispute->close_reason }}</p>
        @elseif($delivery->dispute->refundRequest && $delivery->dispute->refundRequest->status !== \App\Enums\RefundStatus::Rejected)
          <p>الاسترداد: {{ $delivery->dispute->refundRequest->status->label() }}. لا يمكن إغلاق النزاع ورفع الحجز الآن.</p>
          @if(auth()->user()->hasPermission('refunds.review'))<a href="{{ route('admin.refunds.index') }}">مراجعة الاستردادات</a>@endif
        @elseif($delivery->dispute->status === 'open')
          <form method="POST" action="{{ route('admin.settlements.close-dispute', $delivery) }}">@csrf
            <p>إغلاق النزاع يرفع الحجز فقط؛ ليس استردادًا للعميل ولا تحريرًا فوريًا للتاجر. أبقِ النزاع مفتوحًا إذا كانت هناك مطالبة استرداد غير معالجة.</p>
            <textarea name="reason" required minlength="5" maxlength="2000" placeholder="سبب إغلاق النزاع بعد المراجعة"></textarea>
            <label>كلمة مرور حساب الموظف<input type="password" name="current_password" autocomplete="current-password" required></label>
            <button class="btn btn-primary">إغلاق النزاع وإعادة المبلغ إلى المعلّق</button>
          </form>
        @else<p>نتيجة المراجعة: {{ $delivery->dispute->close_reason }}</p>@endif
      @elseif(!$delivery->settled_at && $delivery->settlement_due_at)
        <form method="POST" action="{{ route('delivery-disputes.store', $delivery) }}">@csrf
          <textarea name="reason" required minlength="5" maxlength="2000" placeholder="سبب الحجز الإداري"></textarea>
          <label>كلمة مرور حساب الموظف<input type="password" name="current_password" autocomplete="current-password" required></label>
          <button class="btn">فتح نزاع وحجز المستحقات</button>
        </form>
      @endif
    </section>
  @empty<p>لا توجد شحنات مسلّمة للمراجعة.</p>@endforelse
  {{ $deliveries->links() }}
</div>
@endsection

@push('styles')
<style>
main{max-width:1090px}.rtl form{display:grid;gap:8px}.rtl textarea,.rtl input{width:100%}.rtl button{justify-self:start}@media(max-width:520px){.rtl{margin:0!important}.rtl button{justify-self:stretch;width:100%}}
</style>
@endpush
