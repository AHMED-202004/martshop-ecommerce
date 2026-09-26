@extends('layouts.app')
@section('title', 'تعديل العنوان - Mart.ps')

@section('content')
<main class="address-page container rtl">
  <div class="address-card">
    <div class="address-heading">
      <div>
        <p>حسابي</p>
        <h1>العنوان وبيانات التواصل</h1>
      </div>
      <a href="{{ route('my-account') }}">العودة إلى حسابي</a>
    </div>

    @if($errors->any())
      <div class="address-error" role="alert">
        <strong>تعذّر حفظ البيانات:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <form method="POST" action="{{ route('address.update') }}" class="address-form">
      @csrf
      <div class="address-grid">
        <label>الاسم الأول *
          <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}" required maxlength="100" autocomplete="given-name">
        </label>
        <label>اسم العائلة *
          <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}" required maxlength="100" autocomplete="family-name">
        </label>
        <label>المحافظة *
          <input type="text" name="governorate" value="{{ old('governorate', $user->governorate) }}" required maxlength="100" autocomplete="address-level1">
        </label>
        <label>المدينة / القرية *
          <input type="text" name="city" value="{{ old('city', $user->city) }}" required maxlength="120" autocomplete="address-level2">
        </label>
        <label class="address-wide">العنوان (المنطقة / الشارع) *
          <input type="text" name="address" value="{{ old('address', $user->address) }}" required maxlength="255" autocomplete="street-address">
        </label>
        <label>جوال التواصل *
          <input type="tel" name="mobile" value="{{ old('mobile', $user->mobile) }}" required maxlength="30" inputmode="tel" autocomplete="tel">
        </label>
        <label>رقم إضافي (اختياري)
          <input type="tel" name="alt_mobile" value="{{ old('alt_mobile', $user->alt_mobile) }}" maxlength="30" inputmode="tel" autocomplete="tel">
        </label>
      </div>

      <label class="address-password">كلمة المرور الحالية في Mart.ps لتأكيد تغيير بيانات التوصيل *
        <input type="password" name="current_password" required autocomplete="current-password">
      </label>

      <div class="address-actions">
        <button type="submit">حفظ التعديلات</button>
        <a href="{{ route('my-account') }}">إلغاء</a>
      </div>
    </form>
  </div>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/address.css') }}">
@endpush
