@extends('layouts.private-finance')
@section('title', 'تجهيز وتسجيل حوالة استرداد')
@section('content')
<section>
  <h2>{{ $refund->reference }}</h2><p>{{ $refund->status->label() }}</p>
  <a href="{{ route('admin.refund-transfers.check', $refund) }}">فحص اتساق السجل والإثبات — للقراءة فقط</a>
  <p>إجمالي المبلغ للعميل: <strong>{{ number_format($refund->amount / 100, 2) }} {{ $refund->currency }}</strong></p>
  <p>صافي التاجر: {{ number_format($refund->amount_snapshot['merchant_net_minor'] / 100, 2) }}، العمولة: {{ number_format($refund->amount_snapshot['commission_minor'] / 100, 2) }}، التوصيل: {{ number_format($refund->amount_snapshot['delivery_minor'] / 100, 2) }}، الخدمة: {{ number_format($refund->amount_snapshot['service_minor'] / 100, 2) }}.</p>
  <p>هذا تفصيل لمكونات المبلغ المعاد؛ لا يلغي تلقائيًا أي مستحق مستقل لشركة التوصيل.</p>
  @if($recipient)
    <p>المستلم: {{ $recipient['account_name'] }}</p><p>البنك أو المحفظة: {{ $recipient['provider_name'] }}</p>
    <p>رقم الاستلام: <bdi>{{ $recipient['account_identifier'] }}</bdi></p>
  @else<p>لا توجد وسيلة استلام حالية موثّقة. لا تنفّذ الحوالة.</p>@endif
</section>
@if($transfer?->paid_at)
  <section><h2>الحوالة المسجّلة</h2>
    <p>المرجع: <bdi>{{ $transfer->transaction_reference }}</bdi></p>
    <p>وقت الحوالة: {{ $transfer->transferred_at->format('Y-m-d H:i:s') }} ({{ config('app.timezone') }})</p>
    <a href="{{ route('refund-transfers.proof', $transfer) }}">تنزيل إثبات الحوالة</a>
    <p>لا تُكرر الحوالة. السجل المالي لا يُعدّل أو يُحذف من هذه الصفحة.</p>
  </section>
@elseif(!$isOwn && $refund->status === \App\Enums\RefundStatus::Approved && $recipient && !$transfer)
  <section><h2>1. تثبيت المستلم والمبلغ</h2>
    <p>بعد التجهيز لا يستطيع العميل تغيير الوسيلة. ابدأ فقط عندما تكون جاهزًا للتحويل. إلغاء التجهيز متاح للمخوّل فقط بعد التأكد أن الحوالة لم تُرسل وليست قيد التنفيذ. أي التباس يحتاج مراجعة مالية.</p>
    <form method="POST" action="{{ route('admin.refund-transfers.prepare', $refund) }}">@csrf
      <input type="hidden" name="lock_version" value="{{ $refund->lock_version }}">
      <input type="hidden" name="destination_id" value="{{ $destination->id }}">
      <input type="hidden" name="destination_version" value="{{ $destination->lock_version }}">
      <label for="prepare-password">كلمة مرور حسابك في Mart.ps</label><input type="password" id="prepare-password" name="current_password" autocomplete="current-password" required>
      <button>تجهيز التحويل وتثبيت المستلم — دون إرسال مال</button>
    </form>
  </section>
@elseif(!$isOwn && $transfer && $refund->status === \App\Enums\RefundStatus::Processing)
  <section><h2>2. تسجيل الحوالة المنفّذة خارج الموقع</h2>
    <p>المستلم أعلاه مثبت منذ {{ $transfer->prepared_at->format('Y-m-d H:i:s') }} ({{ config('app.timezone') }}). حوّل مرة واحدة فقط، ثم سجّل الإثبات. عند فشل الحفظ أو انقطاع الاتصال، افحص هذه الصفحة قبل أي محاولة جديدة؛ لا تُكرر الحوالة البنكية.</p>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.refund-transfers.paid', $refund) }}">@csrf
      <input type="hidden" name="transfer_id" value="{{ $transfer->id }}">
      <input type="hidden" name="lock_version" value="{{ $refund->lock_version }}">
      <label for="amount">المبلغ المحوّل ({{ $refund->currency }})</label><input id="amount" name="amount" inputmode="decimal" value="{{ number_format($transfer->amount / 100, 2, '.', '') }}" required readonly>
      <label for="transaction-reference">مرجع الحوالة من البنك أو المحفظة</label><input id="transaction-reference" name="transaction_reference" minlength="3" maxlength="150" required autocomplete="off" dir="ltr">
      <label for="transferred-at">وقت تنفيذ الحوالة بتوقيت {{ config('app.timezone') }}</label><input id="transferred-at" type="datetime-local" step="1" name="transferred_at" required>
      <label for="proof">إثبات هذه الحوالة فقط (صورة أو PDF، حتى 10 MB؛ أخفِ بيانات المعاملات الأخرى)</label><input id="proof" name="proof" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
      <label><input type="checkbox" name="transfer_confirmed" value="1" required> نفّذت هذه الحوالة للمستلم المثبت وبكامل المبلغ مرة واحدة، والإثبات يخصها</label>
      <label for="paid-password">كلمة مرور حسابك في Mart.ps لتأكيد التسجيل</label><input type="password" id="paid-password" name="current_password" autocomplete="current-password" required>
      <button>تسجيل الحوالة والإثبات وإتمام الاسترداد</button>
    </form>
  </section>
  @if(auth()->user()->hasPermission('refunds.cancel'))
    <section><h2>إلغاء تجهيز المحاولة #{{ $transfer->id }} — للحوالات غير المرسلة فقط</h2>
      <p>هذا الإجراء لا يلغي حوالة لدى البنك أو المحفظة. إذا أُرسلت الحوالة، أو كانت معلّقة أو حالتها غير مؤكدة، فلا تستخدمه ولا تُجهّز بديلًا؛ راجع البنك أولًا.</p>
      <form method="POST" action="{{ route('admin.refund-transfers.cancel', $transfer) }}">@csrf
        <input type="hidden" name="lock_version" value="{{ $refund->lock_version }}">
        <label for="cancellation-reason">سبب الإلغاء وكيف تأكدت من عدم الإرسال (دون أرقام حسابات أو أسرار)</label>
        <textarea id="cancellation-reason" name="cancellation_reason" minlength="5" maxlength="2000" required></textarea>
        <label><input type="checkbox" name="not_sent_confirmed" value="1" required> تحققت خارج الموقع أن هذه المحاولة لم تُرسل ولم تُنفّذ وليست معلّقة، ولا توجد حوالة قيد التنفيذ تخصها</label>
        <label for="cancel-password">كلمة مرور حسابك لتأكيد إلغاء التجهيز</label>
        <input type="password" id="cancel-password" name="current_password" autocomplete="current-password" required>
        <button>إلغاء التجهيز فقط وحفظ المحاولة في السجل</button>
      </form>
    </section>
  @endif
@elseif($isOwn)<p>لا يمكنك تجهيز أو تسجيل استرداد يخص طلبك الشخصي.</p>@endif
<section><h2>سجل محاولات التحويل</h2>
  @forelse($attempts as $attempt)
    <article>
      <h3>المحاولة #{{ $attempt->id }} — {{ $attempt->cancelled_at ? 'تجهيز ملغى — لا تستخدم هذه المحاولة' : ($attempt->paid_at ? 'مدفوعة ومسجّلة' : 'مجهّزة') }}</h3>
      <p>{{ number_format($attempt->amount / 100, 2) }} {{ $attempt->currency }} — التجهيز: {{ $attempt->prepared_at->format('Y-m-d H:i:s') }} ({{ config('app.timezone') }})</p>
      @if($attempt->cancelled_at)
        <p>الإلغاء: {{ $attempt->cancelled_at->format('Y-m-d H:i:s') }} بواسطة المستخدم #{{ $attempt->cancelled_by ?? 'محذوف' }}.</p>
        <p>السبب: {{ $attempt->cancellation_reason }}</p>
      @endif
    </article>
  @empty<p>لم تُجهّز أي محاولة بعد.</p>@endforelse
  {{ $attempts->links() }}
</section>
@endsection
