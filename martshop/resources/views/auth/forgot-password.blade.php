@extends('auth.password-layout')
@section('title', 'نسيت كلمة المرور')
@section('form')
  <p>أدخل بريد حسابك المسجّل لإرسال رابط تختار من خلاله كلمة مرور جديدة. لا ترسل كلمة مرورك لأي شخص.</p>
  @unless($mailReady)<p class="notice" role="status">إرسال رسائل الاستعادة غير مفعّل حاليًا. تواصل مع مسؤول الموقع لاستعادة الوصول.</p>@endunless
  <form method="POST" action="{{ route('password.email') }}">@csrf
    <label for="email">البريد الإلكتروني</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" maxlength="191" required>
    <button type="submit" @disabled(!$mailReady)>إرسال رابط الاستعادة</button>
  </form>
@endsection
