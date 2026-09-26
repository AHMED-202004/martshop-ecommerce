@extends('layouts.private-finance')
@section('title', 'الأقسام التي أديرها')
@section('privacy-notice', 'عرض محدود لموظفي الأقسام التي عُيّنت مديرًا لها فقط. لا تُحمّل الصفحة البريد أو الهاتف أو الصلاحيات أو الملاحظات الداخلية.')
@section('content')
<div class="managed-departments rtl">
  @forelse($departments as $department)
    <section><h2>{{ $department->name }}</h2><p>{{ $department->is_active ? 'قسم نشط' : 'قسم معطّل' }} · {{ $department->staff->count() }} موظف</p>
      <div class="table-wrap"><table><thead><tr><th>الموظف</th><th>الرقم</th><th>المسمى</th><th>الحالة</th><th>مهام نشطة</th><th>متأخرة</th><th>توصيلات الشهر</th><th>مسلّمة بالشهر</th><th>توصيلات نشطة</th></tr></thead><tbody>
        @forelse($department->staff as $employee)<tr><td>{{ $employee->name }}</td><td dir="ltr">{{ $employee->employee_number }}</td><td>{{ $employee->job_title ?: 'غير محدد' }}</td><td>{{ $employee->account_status->label() }}</td><td>{{ $employee->active_task_count }}</td><td>{{ $employee->overdue_task_count }}</td><td>{{ $employee->deliveries_assigned_month_count }}</td><td>{{ $employee->deliveries_delivered_month_count }}</td><td>{{ $employee->active_delivery_count }}</td></tr>
        @empty<tr><td colspan="9">لا يوجد موظفون في هذا القسم.</td></tr>@endforelse
      </tbody></table></div>
    </section>
  @empty
    <p>لا يوجد قسم مسند لإدارتك حاليًا.</p>
  @endforelse
  @if($departments->isNotEmpty())
    <section><h2>المهام المفتوحة في أقسامي</h2><p>تُعرض {{ $openTasks->count() }} من {{ $openTaskTotal }} مهمة حسب أقرب موعد استحقاق، بحد أقصى 100. لا تُحمّل الأوصاف أو ملاحظات الإدارة أو تفاصيل السجل المرتبط.</p><form method="GET"><label><input type="checkbox" name="overdue" value="1" @checked(($filters['overdue'] ?? null) === '1')> المتأخرة فقط</label><button>تصفية</button> <a href="{{ route('staff-departments.managed') }}">مسح</a></form><div class="table-wrap"><table><thead><tr><th>المهمة</th><th>الموظف</th><th>النوع</th><th>الأولوية</th><th>الحالة</th><th>الاستحقاق (UTC)</th></tr></thead><tbody>
      @forelse($openTasks as $task) @php($isOverdue = $task->due_at?->isPast())<tr class="{{ $isOverdue ? 'overdue' : '' }}"><td>#{{ $task->id }} — {{ $task->title }}</td><td>{{ $task->assignee?->name ?? 'حساب سابق' }}<br><small dir="ltr">{{ $task->assignee?->employee_number }}</small></td><td>{{ $task->task_type }}</td><td>{{ $task->priority->label() }}</td><td>{{ $task->status->label() }}</td><td>{{ $task->due_at?->format('Y-m-d H:i') ?? 'بدون موعد' }} @if($isOverdue)<strong>— متأخرة</strong>@endif</td></tr>
      @empty<tr><td colspan="6">لا توجد مهام مفتوحة في أقسامك.</td></tr>@endforelse
    </tbody></table></div></section>
  @endif
</div>
@endsection
@push('styles')
<style>
main{max-width:1100px}.managed-departments{max-width:1060px;margin:0 auto}.table-wrap{overflow:auto;border:1px solid #dce2ea;border-radius:10px}table{width:100%;border-collapse:collapse;min-width:760px}th,td{text-align:right;padding:10px;border-bottom:1px solid #e7ebf0}.overdue{background:#fff7ed}.overdue strong{color:#7c2d12}
</style>
@endpush
