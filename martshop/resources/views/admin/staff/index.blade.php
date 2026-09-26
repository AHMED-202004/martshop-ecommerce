@extends('layouts.private-finance')
@section('title', 'إدارة الموظفين')
@section('privacy-notice', 'دليل موظفين خاص. تظهر الحسابات ذات الأدوار التشغيلية فقط؛ لا تشارك أسماء الموظفين أو بريدهم خارج الإدارة المخوّلة.')
@section('content')
<div class="staff-page rtl">
  <div class="staff-head"><p>دليل الحسابات التشغيلية مع حالة الوصول والأدوار الفعلية.</p><div><strong>{{ $staff->total() }} موظف</strong> · <a href="{{ route('admin.staff.create') }}">دعوة موظف جديد</a> · <a href="{{ route('admin.staff.departments.index') }}">إدارة الأقسام</a> · <a href="{{ route('admin.staff.tasks.index') }}">طابور المهام</a></div></div>
  <form method="GET" class="filters">
    <label>بحث بالاسم أو البريد أو الهاتف أو الرقم<input name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100"></label>
    <label>الدور<select name="role"><option value="">كل الأدوار التشغيلية</option>@foreach($roles as $role)<option value="{{ $role->slug }}" @selected(($filters['role'] ?? null) === $role->slug)>{{ $role->name }}</option>@endforeach</select></label>
    <label>الحالة<select name="status"><option value="">كل الحالات</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>@endforeach</select></label>
    <label>القسم<select name="department"><option value="">كل الأقسام</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) ($filters['department'] ?? '') === (string) $department->id)>{{ $department->name }}{{ $department->is_active ? '' : ' — معطّل' }}</option>@endforeach</select></label>
    <label>الترتيب<select name="sort"><option value="name" @selected(($filters['sort'] ?? 'name') === 'name')>الاسم</option><option value="employee_number" @selected(($filters['sort'] ?? null) === 'employee_number')>رقم الموظف</option><option value="newest" @selected(($filters['sort'] ?? null) === 'newest')>الأحدث</option></select></label>
    <div class="filter-actions"><button class="btn btn-primary">تصفية</button><a class="btn" href="{{ route('admin.staff.index') }}">مسح</a></div>
  </form>
  @if($bulkRoles->isNotEmpty() && $staff->count() >= 2)
  <details><summary>تغيير أدوار جماعي آمن</summary><form method="POST" action="{{ route('admin.staff.roles.bulk-update') }}" class="filters">@csrf @method('PATCH')
    <fieldset><legend>الموظفون — اختر اثنين على الأقل</legend>@foreach($staff as $employee)<label><input type="checkbox" name="staff_ids[]" value="{{ $employee->id }}"> {{ $employee->name }} — {{ $employee->employee_number }}</label>@endforeach</fieldset>
    <label>الأدوار الروتينية<select name="role_ids[]" multiple required>@foreach($bulkRoles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></label>
    <label>السبب<textarea name="reason" minlength="10" maxlength="500" required></textarea></label><label>اكتب BULK ROLE CHANGE<input name="confirmation" autocomplete="off" required></label>
    <label>كلمة مرورك<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-primary">تطبيق على المحددين</button><small>الأدوار الإدارية والمالية والأمنية الحساسة تُعدّل فرديًا فقط.</small>
  </form></details>@endif
  <div class="table-wrap"><table><thead><tr><th>الموظف</th><th>الرقم</th><th>القسم والمسمى</th><th>البريد</th><th>الحالة</th><th>المهام المفتوحة</th><th>الأدوار</th><th>تاريخ الانضمام</th></tr></thead><tbody>
    @forelse($staff as $employee)
      <tr><td><a href="{{ route('admin.staff.show', $employee) }}">{{ $employee->name }}</a></td><td dir="ltr">{{ $employee->employee_number }}</td><td>{{ $employee->staffDepartment?->name ?? 'بدون قسم' }}<br><small>{{ $employee->job_title ?: 'بلا مسمى' }}</small></td><td dir="ltr">{{ $employee->email }}</td><td>{{ $employee->account_status->label() }}</td><td>{{ $employee->active_tasks_count }}</td><td>@foreach($employee->roles as $role)<span class="role">{{ $role->name }}</span>@endforeach</td><td>{{ $employee->created_at?->format('Y-m-d') }}</td></tr>
    @empty<tr><td colspan="8">لا توجد نتائج مطابقة.</td></tr>@endforelse
  </tbody></table></div>
  {{ $staff->links() }}
</div>
@endsection
@push('styles')
<style>
main{max-width:1180px}.staff-page{max-width:1140px;margin:0 auto}.staff-head{display:flex;justify-content:space-between;align-items:center;gap:16px}.staff-head p{margin:0}.filters{background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:14px;display:grid;grid-template-columns:2fr repeat(4,1fr) auto;gap:12px;margin:15px 0}.filters label{display:grid;gap:5px}.filter-actions{display:flex;gap:8px;align-items:end}.filter-actions .btn{display:inline-block;padding:9px 12px;border:1px solid #173e73;border-radius:8px;text-decoration:none}.table-wrap{overflow:auto;background:#fff;border:1px solid #dce2ea;border-radius:12px}table{width:100%;border-collapse:collapse;min-width:920px}th,td{text-align:right;padding:11px;border-bottom:1px solid #e7ebf0;vertical-align:top}.role{display:inline-block;background:#eaf1fb;border-radius:999px;padding:2px 9px;margin:2px}@media(max-width:900px){.filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.filters{grid-template-columns:1fr}.staff-head{align-items:flex-start;flex-direction:column}}
</style>
@endpush
