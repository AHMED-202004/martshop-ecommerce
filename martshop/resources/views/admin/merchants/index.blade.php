@extends('layouts.private-finance')
@section('title', 'طلبات التحقق من التجار')
@section('privacy-notice', 'صفحة تحقق خاصة تحتوي بيانات شخصية للتجار. استخدمها فقط لغرض المراجعة المصرّح، ولا تشارك المعلومات أو تطلب كلمة مرور البنك أو المحفظة أو رمز OTP.')
@section('content')
<div class="merchant-review-list rtl">
  <div class="table-wrap">
    <table>
      <thead><tr><th>التاجر</th><th>المنطقة</th><th>الحالة</th><th>تاريخ الإرسال</th><th></th></tr></thead>
      <tbody>
      @forelse($merchants as $merchant)
        <tr>
          <td>{{ $merchant->legal_name }}</td>
          <td>{{ $merchant->location?->name ?? '—' }}</td>
          <td>{{ $merchant->verification_status->value }}</td>
          <td>{{ $merchant->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
          <td><a href="{{ route('admin.merchants.show', $merchant) }}">مراجعة</a></td>
        </tr>
      @empty
        <tr><td colspan="5">لا توجد طلبات.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $merchants->links() }}
</div>
@endsection
@push('styles')<style>main{max-width:1100px}.merchant-review-list{max-width:1050px;margin:0 auto}.table-wrap{overflow:auto;background:#fff;border:1px solid #dce2ea;border-radius:12px}table{width:100%;border-collapse:collapse;min-width:720px}th,td{padding:12px;text-align:right;border-bottom:1px solid #e7ebf0}</style>@endpush
