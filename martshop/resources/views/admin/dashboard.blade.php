@extends('layouts.private-finance')
@section('title', 'لوحة إدارة Mart.ps')
@section('privacy-notice', 'ملخص إداري خاص للمتابعة فقط. تظهر الأقسام المتاحة لصلاحيات حسابك، ولا ينفّذ فتح اللوحة أي إجراء مالي.')
@push('styles')
<style>
  .dashboard-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,280px),1fr));gap:16px}
  .dashboard-grid section{margin:0;display:flex;flex-direction:column}.dashboard-grid h2{margin-top:0}
  .dashboard-metrics{margin:0 0 16px}.dashboard-metrics div{border-top:1px solid #e1e6ed;padding:10px 0}
  .dashboard-metrics dd{font-size:1.8rem;font-weight:bold;margin:0;color:#173e73}
  .dashboard-open{margin-top:auto;display:block;padding:8px 0;min-height:44px}.dashboard-switches{padding-inline-start:24px}
</style>
@endpush
@section('content')
<p>آخر قراءة: {{ $checkedAt->format('Y-m-d H:i:s') }} ({{ config('app.timezone') }}). <a href="{{ route('admin.dashboard') }}">تحديث الأعداد</a></p>
<p>الأعداد للفئات الموضّحة، وقد يتصل الطلب بأكثر من قسم. افتح القسم لمراجعة التفاصيل؛ لا تمثل هذه اللوحة رصيدًا ماليًا أو تأكيدًا على إرسال حوالة.</p>
@if($switches)
  <section aria-labelledby="operations-title">
    <h2 id="operations-title">حالة التشغيل</h2>
    <ul class="dashboard-switches">
      @foreach($switches as $switch)<li>{{ $switch['label'] }}: {{ $switch['enabled'] ? 'مفعّلة' : 'معطّلة' }}</li>@endforeach
    </ul>
    <p>هذه قراءة للإعدادات فقط؛ لم يتم تفعيل أو تعطيل أي ميزة.</p>
  </section>
@endif
<div class="dashboard-grid">
  @foreach($sections as $section)
    <section aria-labelledby="section-{{ $section['key'] }}">
      <h2 id="section-{{ $section['key'] }}">{{ $section['title'] }}</h2>
      @if($section['metrics'])
        <dl class="dashboard-metrics">
          @foreach($section['metrics'] as $label => $count)<div><dt>{{ $label }}</dt><dd>{{ $count }}</dd></div>@endforeach
        </dl>
        @if(array_sum($section['metrics']) === 0)<p>لا توجد أعمال ضمن الفئات أعلاه حاليًا.</p>@endif
      @else<p>إدارة ومراجعة هذا القسم حسب صلاحيات حسابك.</p>@endif
      <a class="dashboard-open" href="{{ route($section['route']) }}">فتح {{ $section['title'] }}</a>
    </section>
  @endforeach
</div>
@endsection
