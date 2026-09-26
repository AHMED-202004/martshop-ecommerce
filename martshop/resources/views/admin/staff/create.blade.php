@extends('layouts.private-finance')
@section('title', 'دعوة موظف جديد')
@section('privacy-notice', 'أنشئ حساب موظف بأقل صلاحيات لازمة. رابط الدعوة أحادي الاستخدام ولا يظهر داخل لوحة الإدارة أو السجلات.')
@section('content')
<p><a href="{{ route('admin.staff.index') }}">العودة إلى دليل الموظفين</a></p>
@if(!$mailReady)
  <section><h2>البريد غير جاهز</h2><p>لن يُنشأ حساب جديد قبل تفعيل نقل بريد حقيقي وآمن لاستعادة كلمة المرور والدعوات.</p></section>
@else
  <form method="POST" action="{{ route('admin.staff.store') }}">@csrf
    <section><h2>بيانات الحساب</h2><label>الاسم الكامل<input name="name" value="{{ old('name') }}" minlength="2" maxlength="120" autocomplete="name" required></label><label>البريد الإلكتروني<input type="email" name="email" value="{{ old('email') }}" maxlength="191" autocomplete="off" required></label><label>المسمى الوظيفي<input name="job_title" value="{{ old('job_title') }}" maxlength="120"></label><label>القسم<select name="staff_department_id"><option value="">بدون قسم حاليًا</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected((string) old('staff_department_id') === (string) $department->id)>{{ $department->name }}</option>@endforeach</select></label></section>
    <section><h2>الدور التشغيلي</h2><fieldset><legend>اختر دورًا واحدًا على الأقل</legend>@foreach($roles as $role)<label><input type="checkbox" name="role_ids[]" value="{{ $role->id }}" @checked(in_array($role->id, old('role_ids', [])))> {{ $role->name }} <code>{{ $role->slug }}</code></label>@endforeach</fieldset></section>
    <section><h2>صلاحيات مباشرة اختيارية</h2><p>تُضاف فوق صلاحيات الدور.</p><fieldset><legend>الصلاحيات</legend>@foreach($permissions as $permission)<label><input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked(in_array($permission->id, old('permission_ids', [])))> {{ $permission->name }} <code>{{ $permission->slug }}</code></label>@endforeach</fieldset></section>
    <section><h2>تأكيد إداري</h2><label>سبب إنشاء الحساب<textarea name="reason" minlength="5" maxlength="500" required>{{ old('reason') }}</textarea></label><label>كلمة مرور حسابك<input type="password" name="current_password" autocomplete="current-password" required></label><button>إنشاء الحساب وإرسال الدعوة</button></section>
  </form>
@endif
@endsection
