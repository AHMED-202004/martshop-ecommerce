@extends('layouts.private-finance')
@section('title', 'تحويلات الاسترداد اليدوية')
@section('content')
<p>لا يرسل الموقع المال. جهّز التحويل أولًا لتثبيت المستلم، ثم سجّل مرجع الحوالة وإثباتها بعد تنفيذها خارج الموقع.</p>
<section aria-labelledby="filters-title">
  <h2 id="filters-title">البحث والمتابعة</h2>
  <form method="GET" action="{{ route('admin.refund-transfers.index') }}">
    <label for="refund-search">مرجع الاسترداد أو جزء منه</label>
    <input id="refund-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100" autocomplete="off">
    <label for="refund-status">الحالة</label>
    <select id="refund-status" name="status">
      <option value="">كل الحالات</option>
      @foreach([\App\Enums\RefundStatus::Approved, \App\Enums\RefundStatus::Processing, \App\Enums\RefundStatus::Paid] as $status)
        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
      @endforeach
    </select>
    <label for="refund-from">تاريخ إنشاء طلب الاسترداد — من ({{ config('app.timezone') }})</label>
    <input type="date" id="refund-from" name="from" value="{{ $filters['from'] ?? '' }}">
    <label for="refund-to">إلى تاريخ، شاملًا اليوم كله</label>
    <input type="date" id="refund-to" name="to" value="{{ $filters['to'] ?? '' }}">
    <label for="refund-sort">الترتيب حسب إنشاء الطلب</label>
    <select id="refund-sort" name="sort">
      <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>الأحدث أولًا</option>
      <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>الأقدم أولًا</option>
    </select>
    <label><input type="checkbox" name="cancelled_only" value="1" @checked(!empty($filters['cancelled_only']))> استردادات سبق إلغاء محاولة تجهيز لها فقط</label>
    <button>تطبيق البحث</button> <a href="{{ route('admin.refund-transfers.index') }}">مسح الفلاتر</a>
  </form>
</section>
<section aria-labelledby="summary-title">
  <h2 id="summary-title">ملخص النتائج المطابقة</h2>
  <p>عدد طلبات الاسترداد: {{ $refunds->total() }}. الملخص يشمل كل صفحات النتائج، وليس الصفحة الحالية فقط.</p>
  <p>المبالغ إجمالي الاسترداد للعميل وليست صافي التاجر. تُفصل حسب العملة والحالة؛ لا تعني المبالغ المعتمدة أو المجهّزة أن المال أُرسل.</p>
  @forelse($summary as $group)
    <p>{{ $group->status->label() }} — {{ (int) $group->refund_count }} طلب — {{ number_format($group->total_minor / 100, 2) }} {{ $group->currency }}</p>
  @empty<p>لا توجد مبالغ مطابقة للفلاتر.</p>@endforelse
</section>
@forelse($refunds as $refund)
  <section><h2>{{ $refund->reference }}</h2>
    <p>{{ number_format($refund->amount / 100, 2) }} {{ $refund->currency }} — {{ $refund->status->label() }}</p>
    <p>إنشاء الطلب: {{ $refund->created_at->format('Y-m-d H:i') }} ({{ config('app.timezone') }})</p>
    @if($refund->status === \App\Enums\RefundStatus::Approved)
      @if($refund->activeDestination?->status === \App\Enums\RefundDestinationStatus::Verified && $refund->activeDestination->reviewed_at)
        <p>وسيلة الاستلام موثّقة؛ بانتظار التجهيز وإعادة فحص المبلغ.</p>
      @else<p>بانتظار وسيلة استلام موثّقة؛ لا تنفّذ الحوالة.</p>@endif
    @endif
    @if($refund->transfer)
      <p>المحاولة الحالية #{{ $refund->transfer->id }} — جُهّزت في {{ $refund->transfer->prepared_at->format('Y-m-d H:i') }}.</p>
      @if($refund->status === \App\Enums\RefundStatus::Processing && !$refund->transfer->paid_at)
        <p>لم يُسجّل إثبات الحوالة بعد. افحص وضعها خارج الموقع قبل أي إجراء؛ لا تكرر إرسال المال.</p>
      @endif
    @elseif($refund->status !== \App\Enums\RefundStatus::Approved)
      <p>لا توجد محاولة حالية مرتبطة؛ تلزم مراجعة السجل قبل أي إجراء مالي.</p>
    @endif
    @if($refund->cancelled_attempts_count)
      <p>محاولات تجهيز ملغاة محفوظة: {{ $refund->cancelled_attempts_count }}. إلغاء التجهيز لا يعني إلغاء طلب الاسترداد.</p>
    @endif
    <a href="{{ route('admin.refund-transfers.show', $refund) }}">فتح التحويل</a>
    <a href="{{ route('admin.refund-transfers.check', $refund) }}">فحص اتساق السجل</a>
  </section>
@empty<p>لا توجد استردادات مطابقة حاليًا. جرّب مسح الفلاتر أو تعديل البحث.</p>@endforelse
{{ $refunds->links() }}
@endsection
