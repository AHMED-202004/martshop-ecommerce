@extends('layouts.private-finance')
@section('title','سجل التدقيق')
@section('privacy-notice', 'سجل إداري خاص قد يحتوي عناوين IP وبيانات الجهاز وأسباب العمليات. لا تشارك محتواه خارج فريق العمل المخوّل، ولا تطلب المنصة هنا كلمة مرور البنك أو المحفظة أو رمز OTP.')
@section('content')
@php use App\Support\AuditLogView; @endphp
<div class="audit-page rtl">
  <div class="audit-head"><p>سجل زمني غير قابل للتعديل للعمليات الحساسة في المنصة.</p><strong>{{ $logs->total() }} عملية</strong></div>

  <form method="GET" class="filters">
    <label>العملية<select name="action"><option value="">الكل</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>@endforeach</select></label>
    <label>الموظف<input name="actor" value="{{ $filters['actor'] ?? '' }}" maxlength="255" placeholder="الاسم أو البريد"></label>
    <label>نوع السجل<select name="subject_type"><option value="">الكل</option>@foreach($subjectTypes as $type)<option value="{{ $type }}" @selected(($filters['subject_type'] ?? null) === $type)>{{ AuditLogView::subjectName($type) }}</option>@endforeach</select></label>
    <label>رقم السجل<input type="number" min="1" name="subject_id" value="{{ $filters['subject_id'] ?? '' }}"></label>
    <label>من<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
    <label>إلى<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
    <div class="filter-actions"><button class="btn btn-primary">تصفية</button><a class="btn" href="{{ route('admin.audit-logs.index') }}">مسح</a></div>
  </form>

  <div class="table-wrap"><table><thead><tr><th>الوقت</th><th>العملية</th><th>المنفّذ</th><th>الهدف</th><th>السبب</th><th></th></tr></thead><tbody>
    @forelse($logs as $log)
      <tr><td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td><td><code>{{ $log->action }}</code></td><td>{{ $log->actor?->name ?? 'النظام' }}<small>{{ $log->actor?->email }}</small></td><td>{{ AuditLogView::subjectName($log->subject_type) }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}</td><td>{{ \Illuminate\Support\Str::limit($log->reason, 70) ?: '—' }}</td><td><a href="{{ route('admin.audit-logs.show', $log) }}">التفاصيل</a></td></tr>
    @empty<tr><td colspan="6">لا توجد نتائج مطابقة.</td></tr>@endforelse
  </tbody></table></div>
  {{ $logs->links() }}
</div>
@endsection
@push('styles')
<style>
main{max-width:1290px}.audit-page{max-width:1250px;margin:0 auto}.audit-head{display:flex;justify-content:space-between;align-items:center;gap:16px}.audit-head p{margin:0}.filters{background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:14px;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin:15px 0}.filters label{display:grid;gap:5px}.filters input,.filters select{padding:9px;border:1px solid #a7b4c5;border-radius:7px}.filter-actions{display:flex;gap:8px;align-items:end}.filter-actions .btn{display:inline-block;padding:9px 12px;border:1px solid #173e73;border-radius:8px;text-decoration:none}.table-wrap{overflow:auto;background:#fff;border:1px solid #dce2ea;border-radius:12px}table{width:100%;border-collapse:collapse;min-width:900px}th,td{text-align:right;padding:11px;border-bottom:1px solid #e7ebf0;vertical-align:top}td small{display:block;color:#566274}@media(max-width:800px){.filters{grid-template-columns:1fr}.audit-head{align-items:flex-start;flex-direction:column}}
</style>
@endpush
