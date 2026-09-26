@extends('layouts.private-finance')
@section('title', 'مراجعة الدفعات')
@section('privacy-notice', 'قائمة مالية خاصة. راجع الدفعة من تفاصيلها ولا تعتمد على رقم المرجع وحده. فتح هذه الصفحة لا يقبل أو يرفض أي دفعة.')

@section('content')
@php
  $labels = ['accepted'=>'مقبول','rejected'=>'مرفوض','short_amount'=>'ناقص','overpaid'=>'زائد','duplicate'=>'مكرر','suspicious'=>'مشبوه'];
@endphp

<nav class="page-actions" aria-label="إدارة الدفعات">
  <a href="{{ route('admin.payment-methods.index') }}">إدارة وسائل الدفع</a>
</nav>

<section>
  <h2>بانتظار المراجعة</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>الإثبات</th><th>الطلب</th><th>العميل</th><th>الوسيلة</th><th>المبلغ</th><th>المرجع</th><th></th></tr></thead>
      <tbody>
      @forelse($pending as $payment)
        <tr>
          <td>#{{ $payment->id }}</td><td>#{{ $payment->order_id }}</td>
          <td>{{ $payment->order->user->name }}</td><td>{{ $payment->method?->name ?? $payment->provider }}</td>
          <td>{{ number_format($payment->amount / 100, 2) }} {{ $payment->currency }}</td>
          <td>{{ $payment->provider_ref }}</td>
          <td><a href="{{ route('admin.payments.show', $payment) }}">مراجعة</a></td>
        </tr>
      @empty
        <tr><td colspan="7">لا توجد دفعات بانتظار المراجعة.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  @if($pending->hasPages())
    <nav class="pager" aria-label="صفحات الدفعات المعلّقة">
      @if($pending->onFirstPage())<span aria-disabled="true">السابق</span>@else<a href="{{ $pending->previousPageUrl() }}">السابق</a>@endif
      <strong>صفحة {{ $pending->currentPage() }} من {{ $pending->lastPage() }}</strong>
      @if($pending->hasMorePages())<a href="{{ $pending->nextPageUrl() }}">التالي</a>@else<span aria-disabled="true">التالي</span>@endif
    </nav>
  @endif
</section>

<section>
  <h2>آخر القرارات</h2>
  <div class="table-wrap">
    <table>
      <thead><tr><th>الإثبات</th><th>الطلب</th><th>الحالة</th><th>المراجع</th><th>التاريخ</th><th></th></tr></thead>
      <tbody>
      @forelse($recent as $payment)
        <tr>
          <td>#{{ $payment->id }}</td><td>#{{ $payment->order_id }}</td>
          <td>{{ $labels[$payment->status->value] ?? $payment->status->value }}</td>
          <td>{{ $payment->reviewer?->name ?? '—' }}</td><td>{{ $payment->reviewed_at?->format('Y-m-d H:i') }}</td>
          <td><a href="{{ route('admin.payments.show', $payment) }}">التفاصيل</a></td>
        </tr>
      @empty
        <tr><td colspan="6">لا توجد قرارات سابقة.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection

@push('styles')
<style>
.page-actions{margin-bottom:12px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{text-align:right;padding:10px;border-bottom:1px solid #e5e9ef;white-space:nowrap}.pager{align-items:center;justify-content:center;margin-top:16px}.pager a,.pager span{min-height:44px;display:inline-flex;align-items:center;border:1px solid #b8c3d2;border-radius:8px;padding:7px 12px;text-decoration:none}.pager span{color:#68768a;background:#f2f4f7}@media(max-width:520px){.pager{gap:8px}.pager a,.pager span{padding:7px 9px}}
</style>
@endpush
