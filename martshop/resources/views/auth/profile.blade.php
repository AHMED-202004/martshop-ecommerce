@extends('layouts.app')
@section('title', 'حسابي - Mart.ps')

@section('content')
@php
  $displayName = trim(implode(' ', array_filter([$user->first_name, $user->last_name]))) ?: $user->name;
  $displayEmail = str_ends_with($user->email, '@noemail.local') ? 'غير مضاف' : $user->email;
  $genderLabels = ['male' => 'ذكر', 'female' => 'أنثى'];
@endphp

<main class="account-page container rtl">
  <div class="account-heading">
    <div>
      <p class="account-kicker">حسابك في Mart.ps</p>
      <h1>مرحبًا، {{ $displayName }}</h1>
      <p>يمكنك مراجعة بياناتك والوصول إلى طلباتك وعنوان التوصيل من هنا.</p>
    </div>
    <a class="account-action account-action-primary" href="{{ route('orders.history') }}">عرض طلباتي</a>
  </div>

  @if(session('success'))<p class="account-notice" role="status">{{ session('success') }}</p>@endif
  @if(session('toast'))<p class="account-notice" role="status">{{ session('toast') }}</p>@endif

  <div class="account-grid">
    <section class="account-card" aria-labelledby="account-contact-title">
      <h2 id="account-contact-title">بيانات التواصل</h2>
      <dl class="account-details">
        <div><dt>الاسم</dt><dd>{{ $displayName ?: 'غير مضاف' }}</dd></div>
        <div><dt>البريد الإلكتروني</dt><dd dir="ltr">{{ $displayEmail }}</dd></div>
        <div><dt>رقم تسجيل الدخول</dt><dd dir="ltr">{{ $user->phone ?: 'غير مضاف' }}</dd></div>
        <div><dt>جوال التواصل</dt><dd dir="ltr">{{ $user->mobile ?: 'غير مضاف' }}</dd></div>
        <div><dt>رقم إضافي</dt><dd dir="ltr">{{ $user->alt_mobile ?: 'غير مضاف' }}</dd></div>
      </dl>
    </section>

    <section class="account-card" aria-labelledby="account-address-title">
      <h2 id="account-address-title">عنوان التوصيل</h2>
      <dl class="account-details">
        <div><dt>المحافظة</dt><dd>{{ $user->governorate ?: 'غير مضافة' }}</dd></div>
        <div><dt>المدينة / القرية</dt><dd>{{ $user->city ?: 'غير مضافة' }}</dd></div>
        <div class="account-detail-wide"><dt>العنوان</dt><dd>{{ $user->address ?: 'غير مضاف' }}</dd></div>
      </dl>
      <a class="account-action" href="{{ route('address.edit') }}">تعديل العنوان وبيانات التواصل</a>
    </section>

    <section class="account-card" aria-labelledby="account-personal-title">
      <h2 id="account-personal-title">معلومات شخصية</h2>
      <dl class="account-details">
        <div><dt>الجنس</dt><dd>{{ $genderLabels[$user->gender] ?? 'غير مضاف' }}</dd></div>
        <div><dt>تاريخ الميلاد</dt><dd dir="ltr">{{ $user->dob?->format('Y-m-d') ?? 'غير مضاف' }}</dd></div>
      </dl>
    </section>

    <section class="account-card account-shortcuts" aria-labelledby="account-shortcuts-title">
      <h2 id="account-shortcuts-title">اختصارات</h2>
      <a href="{{ route('orders.history') }}">تاريخ الطلبات</a>
      <a href="{{ route('address.edit') }}">العنوان وبيانات التوصيل</a>
      @if($user->merchant || $merchantRegistrationEnabled)
        <a href="{{ route('merchant.profile.edit') }}">حساب التاجر والتحقق</a>
      @endif
      @if(app(\App\Services\AdminDashboardService::class)->canAccess($user))
        <a href="{{ route('admin.dashboard') }}">لوحة الإدارة</a>
      @endif
    </section>

    <section class="account-card account-password" aria-labelledby="account-password-title">
      <h2 id="account-password-title">تغيير كلمة المرور</h2>
      <p>بعد الحفظ ستبقى هذه الجلسة مفتوحة، وسيتم إنهاء جلسات الحساب الأخرى.</p>
      @if($errors->has('current_password') || $errors->has('password'))
        <p class="account-error" role="alert">{{ $errors->first('current_password') ?: $errors->first('password') }}</p>
      @endif
      <form method="POST" action="{{ route('account.password.update') }}" class="account-password-form">
        @csrf
        @method('PUT')
        <label>كلمة المرور الحالية
          <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label>كلمة المرور الجديدة
          <input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password">
        </label>
        <label>تأكيد كلمة المرور الجديدة
          <input type="password" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password">
        </label>
        <button class="account-action account-action-primary" type="submit">حفظ كلمة المرور الجديدة</button>
      </form>
    </section>
  </div>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/account.css') }}">
@endpush
