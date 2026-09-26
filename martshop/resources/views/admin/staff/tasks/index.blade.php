@extends('layouts.private-finance')
@section('title', 'طابور مهام الموظفين')
@section('privacy-notice', 'قائمة مهام تشغيلية خاصة. هذه الشاشة تقلل البيانات ولا تحمل وصف المهمة أو الملاحظات الداخلية.')
@section('content')
<div class="tasks-page rtl">
  <p><a href="{{ route('admin.staff.index') }}">العودة إلى دليل الموظفين</a></p>
  <form method="GET" class="filters">
    <label>بحث بالعنوان<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100"></label>
    <label>الحالة<select name="status"><option value="">كل الحالات</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
    <label>الأولوية<select name="priority"><option value="">كل الأولويات</option>@foreach($priorities as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>@endforeach</select></label>
    <label>الموظف<select name="assigned_to"><option value="">كل الموظفين</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) ($filters['assigned_to'] ?? '') === (string) $assignee->id)>{{ $assignee->name }} — {{ $assignee->employee_number }}</option>@endforeach</select></label>
    <label class="check"><input type="checkbox" name="overdue" value="1" @checked(($filters['overdue'] ?? null) === '1')> المتأخرة المفتوحة فقط</label>
    <div><button>تصفية</button> <a href="{{ route('admin.staff.tasks.index') }}">مسح</a></div>
  </form>
  <p><strong>{{ $tasks->total() }} مهمة</strong></p>
  <div class="table-wrap"><table><thead><tr><th>المهمة</th><th>النوع</th><th>الموظف</th><th>الحالة</th><th>الأولوية</th><th>الاستحقاق (UTC)</th></tr></thead><tbody>
    @forelse($tasks as $task)<tr><td>#{{ $task->id }} — <a href="{{ route('admin.staff.show', $task->assigned_to) }}">{{ $task->title }}</a></td><td>{{ $task->task_type }}</td><td>{{ $task->assignee?->name ?? 'حساب سابق' }}<br><small dir="ltr">{{ $task->assignee?->employee_number }}</small></td><td>{{ $task->status->label() }}</td><td>{{ $task->priority->label() }}</td><td>{{ $task->due_at?->format('Y-m-d H:i') ?? 'بدون موعد' }}</td></tr>
    @empty<tr><td colspan="6">لا توجد مهام مطابقة.</td></tr>@endforelse
  </tbody></table></div>
  {{ $tasks->links() }}
</div>
@endsection
@push('styles')
<style>
main{max-width:1180px}.tasks-page{max-width:1140px;margin:0 auto}.filters{display:grid;grid-template-columns:2fr repeat(3,1fr) auto auto;gap:10px;align-items:end}.filters label:not(.check){display:grid;gap:5px}.check{padding-bottom:10px}.table-wrap{overflow:auto;border:1px solid #dce2ea;border-radius:10px;margin-top:12px}table{width:100%;border-collapse:collapse;min-width:850px}th,td{text-align:right;padding:10px;border-bottom:1px solid #e7ebf0}@media(max-width:900px){.filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.filters{grid-template-columns:1fr}}
</style>
@endpush
