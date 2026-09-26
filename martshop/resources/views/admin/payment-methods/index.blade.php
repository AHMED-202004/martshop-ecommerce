@extends('layouts.private-finance')
@section('title', 'إدارة وسائل الدفع اليدوي')
@section('privacy-notice', 'صفحة مالية خاصة تحتوي بيانات حسابات التحويل. يلزم تأكيد كلمة مرور حساب المسؤول عند كل إضافة أو تعديل. لا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP.')

@section('content')
<div class="container rtl methods-admin page-pad">
  <div class="page-head"><p>لا تفعّل أي وسيلة قبل إدخال بيانات الحساب الصحيحة. التفاصيل الظاهرة هنا ستُعرض للعميل وتحفظ Snapshot عند الدفع.</p><a class="btn" href="{{ route('admin.payments.index') }}">مراجعة الدفعات</a></div>

  <form method="POST" action="{{ route('admin.payment-methods.store') }}" class="panel method-form">
    @csrf
    <h2>إضافة وسيلة</h2>
    <label>الاسم<input name="name" value="{{ old('name') }}" required></label>
    <label>المعرّف البرمجي<input name="slug" value="{{ old('slug') }}" placeholder="jawwal-pay" required></label>
    <label>اسم الحساب<input name="account_name" value="{{ old('account_name') }}"></label>
    <label>رقم/معرّف الحساب<input name="account_identifier" value="{{ old('account_identifier') }}"></label>
    <label>تعليمات التحويل<textarea name="instructions" rows="3">{{ old('instructions') }}</textarea></label>
    <label>الترتيب<input type="number" name="sort_order" min="0" value="{{ old('sort_order', 0) }}"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active'))> مفعّلة</label>
    <label class="reauth">كلمة مرور حساب المسؤول<input type="password" name="current_password" autocomplete="current-password" required></label>
    <button class="btn btn-primary">إضافة</button>
  </form>

  <div class="method-list">
    @foreach($methods as $method)
      <form method="POST" action="{{ route('admin.payment-methods.update', $method) }}" class="panel method-form">
        @csrf @method('PUT')
        <h2>{{ $method->name }}</h2>
        <label>الاسم<input name="name" value="{{ $method->name }}" required></label>
        <label>المعرّف البرمجي<input name="slug" value="{{ $method->slug }}" required></label>
        <label>اسم الحساب<input name="account_name" value="{{ $method->account_name }}"></label>
        <label>رقم/معرّف الحساب<input name="account_identifier" value="{{ $method->account_identifier }}"></label>
        <label>تعليمات التحويل<textarea name="instructions" rows="3">{{ $method->instructions }}</textarea></label>
        <label>الترتيب<input type="number" name="sort_order" min="0" value="{{ $method->sort_order }}"></label>
        <label class="check"><input type="checkbox" name="is_active" value="1" @checked($method->is_active)> مفعّلة</label>
        <label class="reauth">كلمة مرور حساب المسؤول<input type="password" name="current_password" autocomplete="current-password" required></label>
        <button class="btn btn-primary">حفظ</button>
      </form>
    @endforeach
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin-payment-methods.css') }}">
@endpush
