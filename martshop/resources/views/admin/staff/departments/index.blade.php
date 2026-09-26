@extends('layouts.private-finance')
@section('title', 'أقسام الموظفين')
@section('privacy-notice', 'إدارة داخلية لأقسام الموظفين ومديريها. القسم ينظم العمل ولا يمنح أي صلاحية بحد ذاته.')
@section('content')
<div class="departments-page rtl">
  <p><a href="{{ route('admin.staff.index') }}">العودة إلى دليل الموظفين</a></p>
  <section>
    <h2>إنشاء قسم</h2>
    <form method="POST" action="{{ route('admin.staff.departments.store') }}">@csrf
      <div class="form-grid">
        <label>اسم القسم<input name="name" value="{{ old('name') }}" minlength="2" maxlength="100" required></label>
        <label>المعرّف التقني<input name="slug" value="{{ old('slug') }}" maxlength="100" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" dir="ltr" required></label>
        <label>مدير القسم<select name="manager_id"><option value="">بدون مدير</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected((string) old('manager_id') === (string) $manager->id)>{{ $manager->name }} — {{ $manager->employee_number }}</option>@endforeach</select></label>
        <label>سبب الإنشاء<textarea name="reason" minlength="5" maxlength="500" required>{{ old('reason') }}</textarea></label>
        <label>كلمة مرور حسابك<input type="password" name="current_password" autocomplete="current-password" required></label>
      </div>
      <button>إنشاء القسم</button>
    </form>
  </section>
  <h2>الأقسام الحالية</h2>
  @forelse($departments as $department)
    <section>
      <form method="POST" action="{{ route('admin.staff.departments.update', $department) }}">@csrf @method('PATCH')
        <div class="department-head"><strong>{{ $department->name }}</strong><span>{{ $department->staff_count }} موظف · {{ $department->is_active ? 'نشط' : 'معطّل' }}</span></div>
        <div class="form-grid">
          <label>اسم القسم<input name="name" value="{{ $department->name }}" minlength="2" maxlength="100" required></label>
          <label>المعرّف التقني<input name="slug" value="{{ $department->slug }}" maxlength="100" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" dir="ltr" required></label>
          <label>مدير القسم<select name="manager_id"><option value="">بدون مدير</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected($department->manager_id === $manager->id)>{{ $manager->name }} — {{ $manager->employee_number }}</option>@endforeach</select></label>
          <label>الحالة<select name="is_active"><option value="1" @selected($department->is_active)>نشط</option><option value="0" @selected(!$department->is_active)>معطّل</option></select></label>
          <label>سبب التغيير<textarea name="reason" minlength="5" maxlength="500" required></textarea></label>
          <label>كلمة مرور حسابك<input type="password" name="current_password" autocomplete="current-password" required></label>
        </div>
        <button>حفظ القسم</button>
      </form>
    </section>
  @empty
    <p>لم تُنشأ أقسام بعد.</p>
  @endforelse
</div>
@endsection
@push('styles')
<style>
main{max-width:1100px}.departments-page{max-width:1060px;margin:0 auto}.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.form-grid label{display:grid;gap:5px}.department-head{display:flex;justify-content:space-between;gap:12px;margin-bottom:12px}@media(max-width:700px){.form-grid{grid-template-columns:1fr}.department-head{flex-direction:column}}
</style>
@endpush
