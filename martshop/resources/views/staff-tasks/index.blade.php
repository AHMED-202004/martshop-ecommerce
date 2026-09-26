@extends('layouts.private-finance')
@section('title', 'مهامي')
@section('privacy-notice', 'تظهر هنا مهامك أنت فقط. الملاحظات الإدارية الداخلية غير محملة في هذه الصفحة.')
@section('content')
<div class="my-tasks rtl">
  <section class="work-plan"><h2>نطاق عملي وجدول الدوام</h2><dl><dt>المواقع</dt><dd>@forelse($workLocations as $location)<span class="chip">{{ $location->name }}</span>@emptyكل المواقع — لم يحدد نطاق مقيد بعد@endforelse</dd><dt>المنطقة الزمنية</dt><dd>{{ $scheduleTimezone ?: 'غير محددة' }}</dd></dl>
    @php($dayLabels = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'])
    <div class="schedule">@foreach($dayLabels as $dayNumber => $dayLabel) @php($day = $workSchedule->get($dayNumber))<div><strong>{{ $dayLabel }}</strong><br>@if($day?->is_working){{ substr($day->starts_at, 0, 5) }}–{{ substr($day->ends_at, 0, 5) }}@elseif($day)إجازة@elseغير محدد@endif</div>@endforeach</div>
    <p><small>هذا عرض للجدول الحالي فقط. تعديل النطاق أو الدوام يتم من الإدارة المخوّلة.</small></p>
  </section>
  <section><h2>ملخص مهامي</h2><div class="summary"><div><strong>{{ $taskSummary['open'] }}</strong><span>مفتوحة</span></div><div class="{{ $taskSummary['overdue'] > 0 ? 'warning' : '' }}"><strong>{{ $taskSummary['overdue'] }}</strong><span>متأخرة</span></div><div><strong>{{ $taskSummary['completed_today'] }}</strong><span>مكتملة اليوم</span></div></div></section>
  <form method="GET"><label>الحالة<select name="status"><option value="">كل الحالات</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>@endforeach</select></label><label><input type="checkbox" name="overdue" value="1" @checked(($filters['overdue'] ?? null) === '1')> المتأخرة فقط</label><button>تصفية</button> <a href="{{ route('staff-tasks.index') }}">مسح</a></form>
  <p><strong>{{ $tasks->total() }} مهمة</strong></p>
  @forelse($tasks as $task)
    @php($isOverdue = $task->due_at?->isPast() && !in_array($task->status, [\App\Enums\StaffTaskStatus::Completed, \App\Enums\StaffTaskStatus::Cancelled], true))
    <article class="task-card {{ $isOverdue ? 'task-overdue' : '' }}"><h2>#{{ $task->id }} — {{ $task->title }} @if($isOverdue)<span class="overdue-label">متأخرة فعليًا</span>@endif</h2><dl><dt>النوع</dt><dd>{{ $task->task_type }}</dd>@if($task->related_id)<dt>السجل المرتبط</dt><dd>{{ $task->task_type }} #{{ $task->related_id }}</dd>@endif<dt>الحالة</dt><dd>{{ $task->status->label() }}</dd><dt>الأولوية</dt><dd>{{ $task->priority->label() }}</dd><dt>الاستحقاق (UTC)</dt><dd>{{ $task->due_at?->format('Y-m-d H:i') ?? 'بدون موعد' }}</dd><dt>أسندها</dt><dd>{{ $task->assigner?->name ?? 'حساب سابق' }}</dd></dl>@if($task->description)<p>{{ $task->description }}</p>@endif
    @if(!in_array($task->status, [\App\Enums\StaffTaskStatus::Completed, \App\Enums\StaffTaskStatus::Cancelled], true) && auth()->user()->hasPermission('staff-tasks.update-own'))
      <form method="POST" action="{{ route('staff-tasks.update', $task) }}">@csrf @method('PATCH')<input type="hidden" name="expected_version" value="{{ $task->version }}"><label>الحالة<select name="status" required><option value="in_progress">قيد التنفيذ</option><option value="waiting">بانتظار متابعة</option><option value="completed">مكتملة</option></select></label><label>ملاحظة التقدم<textarea name="progress_note" minlength="3" maxlength="500" required></textarea></label><button>تحديث حالتي للمهمة</button></form>
    @endif
    </article>
  @empty<p>لا توجد مهام مسندة لك.</p>@endforelse
  {{ $tasks->links() }}
</div>
@endsection
@push('styles')
<style>
.my-tasks{max-width:900px;margin:0 auto}.task-card{border-inline-start:5px solid #173e73}.task-card.task-overdue{border-inline-start-color:#b45309}.overdue-label{display:inline-block;padding:1px 8px;border-radius:999px;background:#fff7ed;color:#7c2d12;font-size:.8rem}.task-card dl,.work-plan dl{display:grid;grid-template-columns:120px 1fr;gap:6px 14px}.task-card dt,.work-plan dt{font-weight:700}.task-card dd,.work-plan dd{margin:0}.chip{display:inline-block;background:#eaf1fb;border-radius:999px;padding:2px 9px;margin:2px}.schedule,.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:14px}.schedule>div,.summary>div{padding:9px;border:1px solid #dce2ea;border-radius:8px;background:#f8fafc}.summary{grid-template-columns:repeat(3,1fr)}.summary strong,.summary span{display:block}.summary strong{font-size:1.4rem}.summary .warning{border-color:#b45309;background:#fff7ed;color:#7c2d12}@media(max-width:700px){.schedule{grid-template-columns:repeat(2,1fr)}}@media(max-width:520px){.task-card dl,.work-plan dl{grid-template-columns:1fr}.task-card dd,.work-plan dd{margin-bottom:6px}.schedule,.summary{grid-template-columns:1fr}}
</style>
@endpush
