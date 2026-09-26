@extends('layouts.private-finance')
@section('title', 'الرصيد والسجل المحاسبي')
@section('privacy-notice', 'هذه صفحة مالية خاصة للعرض فقط. الرصيد المتاح وحده قابل لطلب السحب، ولا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP.')

@section('content')
@php
  $entryTypeLabels = [
    'sale' => 'بيع', 'settlement_release' => 'تحرير تسوية', 'dispute_hold' => 'حجز نزاع',
    'dispute_release' => 'تحرير نزاع', 'refund' => 'استرداد', 'withdrawal_hold' => 'حجز سحب',
    'withdrawal_release' => 'إلغاء حجز سحب', 'withdrawal' => 'سحب مدفوع',
    'adjustment' => 'تسوية يدوية', 'reversal' => 'قيد عكسي',
  ];
  $statusLabels = ['pending' => 'معلّق', 'available' => 'متاح', 'held' => 'محجوز', 'withdrawn' => 'مسحوب', 'refunded' => 'مسترد'];
@endphp
<div class="container rtl merchant-ledger page-pad">
  <div class="page-head"><p>الرصيد المعلّق لا يصبح قابلًا للسحب إلا بعد اكتمال التسليم وفترة التسوية.</p><div><a class="btn" href="{{ route('merchant.withdrawals.index') }}">طلبات السحب</a> <a class="btn" href="{{ route('merchant.orders.index') }}">طلبات التاجر</a></div></div>
  <div class="balances">
    @foreach($statusLabels as $key => $label)
      <div class="balance-card"><span>{{ $label }}</span>
        @forelse(($balances[$key] ?? []) as $currency => $minor)
          <strong>{{ $currency === 'ILS' ? '₪' : $currency.' ' }}{{ number_format($minor / 100, 2) }}</strong>
        @empty
          <strong>₪0.00</strong>
        @endforelse
      </div>
    @endforeach
  </div>
  <div class="panel table-wrap"><table><thead><tr><th>القيد</th><th>الطلب</th><th>النوع</th><th>الحالة</th><th>الإجمالي</th><th>العمولة</th><th>الصافي</th><th>التاريخ</th></tr></thead><tbody>
  @forelse($entries as $entry)
    <tr><td>#{{ $entry->id }}</td><td>{{ $entry->order_id ? '#'.$entry->order_id : '—' }}</td><td>{{ $entryTypeLabels[$entry->entry_type->value] ?? $entry->entry_type->value }}</td><td>{{ $statusLabels[$entry->status->value] ?? $entry->status->value }}</td><td>{{ $entry->currency === 'ILS' ? '₪' : $entry->currency.' ' }}{{ number_format($entry->gross_amount / 100, 2) }}</td><td>{{ $entry->currency === 'ILS' ? '₪' : $entry->currency.' ' }}{{ number_format($entry->commission_amount / 100, 2) }}</td><td>{{ $entry->currency === 'ILS' ? '₪' : $entry->currency.' ' }}{{ number_format($entry->net_amount / 100, 2) }}</td><td>{{ $entry->created_at->format('Y-m-d H:i') }}</td></tr>
  @empty<tr><td colspan="8">لا توجد قيود محاسبية حتى الآن.</td></tr>@endforelse
  </tbody></table></div>
  {{ $entries->links() }}
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-ledger.css') }}">
@endpush
