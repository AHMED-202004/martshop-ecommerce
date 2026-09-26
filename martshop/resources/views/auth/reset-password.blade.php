@extends('auth.password-layout')
@section('title', 'اختيار كلمة مرور جديدة')
@section('form')
  <p>اختر كلمة مرور جديدة، أو فعّل دعوة الموظف إن كانت هذه رسالة دعوة. يجب أن تتكون كلمة المرور من 12 حرفًا على الأقل وتحتوي حروفًا وأرقامًا. هذا الرابط خاص بك ولمرة واحدة.</p>
  <form method="POST" action="{{ route('password.update') }}">@csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <input type="hidden" name="email" value="{{ $email }}">
    <p>الحساب: <bdi>{{ $email }}</bdi></p>
    <label for="password">كلمة المرور الجديدة</label>
    <input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required>
    <label for="password_confirmation">تأكيد كلمة المرور الجديدة</label>
    <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required>
    <button type="submit">حفظ كلمة المرور الجديدة</button>
  </form>
@endsection
