@extends('layouts.private-finance')
@section('title', 'السجل المحاسبي للتجار')
@section('privacy-notice', 'سجل مالي خاص للعرض والمطابقة فقط. القيود غير قابلة للتعديل أو الحذف؛ أي تصحيح مالي يجب أن يكون بقيد عكسي أو تسوية معتمدة.')

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
<div class="container rtl ledger-page page-pad">
  <div class="page-head"><p>اعرض القيود حسب التاجر أو حالة الرصيد دون تغيير البيانات المالية.</p><div><a class="btn" href="{{ route('admin.withdrawals.index') }}">السحوبات</a> <a class="btn" href="{{ route('admin.commissions.index') }}">قواعد العمولة</a></div></div>
  <form method="GET" class="panel filters">
    <select name="merchant_id"><option value="">كل التجار</option>@foreach($merchants as $merchant)<option value="{{ $merchant->id }}" @selected(request('merchant_id') == $merchant->id)>{{ $merchant->legal_name }}</option>@endforeach</select>
    <select name="status"><option value="">كل الأرصدة</option>@foreach(['pending'=>'معلّق','available'=>'متاح','held'=>'محجوز','withdrawn'=>'مسحوب','refunded'=>'مسترد'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select>
    <button class="btn btn-primary">تصفية</button>
  </form>

  @if($selectedMerchant && $balances)
    <section class="panel"><h2>أرصدة {{ $selectedMerchant->legal_name }}</h2><div class="balances">
      @foreach($statusLabels as $key => $label)
        <div class="balance-card"><span>{{ $label }}</span>
          @forelse(($balances[$key] ?? []) as $currency => $minor)
            <strong>{{ $currency === 'ILS' ? '₪' : $currency.' ' }}{{ number_format($minor / 100, 2) }}</strong>
          @empty
            <strong>₪0.00</strong>
          @endforelse
        </div>
      @endforeach
    </div></section>
  @endif

  <div class="panel table-wrap"><table><thead><tr><th>القيد</th><th>التاجر</th><th>النوع</th><th>الحالة</th><th>الإجمالي</th><th>العمولة</th><th>الصافي</th><th>الطلب</th><th>المرجع</th><th>التاريخ</th></tr></thead><tbody>
  @forelse($entries as $entry)
    <tr><td>#{{ $entry->id }}</td><td>{{ $entry->merchant->legal_name }}</td><td>{{ $entryTypeLabels[$entry->entry_type->value] ?? $entry->entry_type->value }}</td><td>{{ $statusLabels[$entry->status->value] ?? $entry->status->value }}</td><td>{{ $entry->currency === 'ILS' ? '₪' : $entry->currency.' ' }}{{ number_format($entry->gross_amount / 100, 2) }}</td><td>{{ $entry->currency === 'ILS' ? '₪' : $entry->currency.' ' }}{{ number_format($entry->commission_amount / 100, 2) }}</td><td>{{ $entry->currency === 'ILS' ? '₪' : $entry->currency.' ' }}{{ number_format($entry->net_amount / 100, 2) }}</td><td>{{ $entry->order_id ? '#'.$entry->order_id : '—' }}</td><td>{{ $entry->reference }}</td><td>{{ $entry->created_at->format('Y-m-d H:i') }}</td></tr>
  @empty<tr><td colspan="10">لا توجد قيود محاسبية.</td></tr>@endforelse
  </tbody></table></div>
  {{ $entries->links() }}
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin-ledger.css') }}">
@endpush
