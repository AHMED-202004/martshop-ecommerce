@extends('layouts.private-finance')
@section('title','تفاصيل سجل التدقيق')
@section('privacy-notice', 'تفاصيل إدارية خاصة قد تحتوي عنوان IP وبيانات الجهاز وأسباب العمليات. القيم ذات المفاتيح الحساسة تُحجب عند العرض، ولا تطلب المنصة هنا كلمة مرور البنك أو المحفظة أو رمز OTP.')
@section('content')
@php
  use App\Support\AuditLogView;
  $sections = ['قبل العملية' => AuditLogView::sanitize($log->before), 'بعد العملية' => AuditLogView::sanitize($log->after), 'بيانات إضافية' => AuditLogView::sanitize($log->metadata)];
@endphp
<div class="audit-detail rtl">
  <a href="{{ route('admin.audit-logs.index') }}">← العودة إلى سجل التدقيق</a>
  <div class="panel"><div class="head"><h2>{{ $log->action }}</h2><strong>#{{ $log->id }}</strong></div>
    <dl><dt>الوقت</dt><dd>{{ $log->created_at->format('Y-m-d H:i:s') }}</dd><dt>المنفّذ</dt><dd>{{ $log->actor?->name ?? 'النظام' }} {{ $log->actor?->email ? '— '.$log->actor->email : '' }}</dd><dt>الهدف</dt><dd>{{ AuditLogView::subjectName($log->subject_type) }}{{ $log->subject_id ? ' #'.$log->subject_id : '' }}</dd><dt>السبب</dt><dd>{{ $log->reason ?: '—' }}</dd><dt>IP</dt><dd>{{ $log->ip_address ?: '—' }}</dd><dt>الجهاز</dt><dd class="wrap">{{ $log->user_agent ?: '—' }}</dd></dl>
  </div>
  @foreach($sections as $title => $data)
    <section class="panel"><h2>{{ $title }}</h2>@if($data)<pre>{{ json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</pre>@else<p>لا توجد بيانات.</p>@endif</section>
  @endforeach
</div>
@endsection
@push('styles')
<style>
.audit-detail{max-width:1000px;margin:0 auto}.audit-detail .panel{background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:16px;margin:14px 0}.audit-detail .head{display:flex;justify-content:space-between;gap:16px}.audit-detail .head h2{margin-top:0}.audit-detail dl{display:grid;grid-template-columns:130px 1fr;gap:9px}.audit-detail dt{font-weight:bold}.audit-detail dd{margin:0}.audit-detail pre{direction:ltr;text-align:left;white-space:pre-wrap;overflow-wrap:anywhere;background:#161b22;color:#e6edf3;padding:14px;border-radius:9px}.wrap{overflow-wrap:anywhere}@media(max-width:600px){.audit-detail dl{grid-template-columns:1fr}.audit-detail dd{margin-bottom:8px}}
</style>
@endpush
