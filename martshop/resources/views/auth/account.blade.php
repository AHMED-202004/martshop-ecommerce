@extends('layouts.app')
@section('title', 'الحساب - Mart.ps')

@section('content')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/auth.css') }}">
@endpush

<div class="rtl container-narrow">
  <h2 class="muted">{{ ($tab ?? 'login') === 'register' ? 'إنشاء حساب' : 'تسجيل الدخول' }}</h2>
  @if(session('status'))<p role="status">{{ session('status') }}</p>@endif
  @if($registrationUnavailable ?? false)
    <p class="registration-paused" role="status">إنشاء الحسابات متوقف مؤقتًا. لا يزال بإمكان أصحاب الحسابات الحالية تسجيل الدخول.</p>
  @endif

  <div class="tabs">
    <a class="tab-btn {{ ($tab ?? 'login')==='login' ? 'active' : '' }}"
       href="{{ route('login', ['tab'=>'login']) }}">تسجيل الدخول</a>
    @if($registrationEnabled ?? true)
      <a class="tab-btn {{ ($tab ?? 'login')==='register' ? 'active' : '' }}"
         href="{{ route('login', ['tab'=>'register']) }}">إنشاء حساب</a>
    @endif
  </div>

  {{-- =============== Login Tab =============== --}}
  @if(($tab ?? 'login') === 'login')
    <div class="card">
      <form method="post" action="{{ route('login.post') }}" class="rtl">
        @csrf
        <div class="field">
          <label>رقم الهاتف أو البريد الإلكتروني</label>
          <input class="input" type="text" name="login" value="{{ old('login') }}" placeholder="أدخل الهاتف أو البريد" maxlength="191" autocomplete="username" required>
          @error('login')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field">
          <label>كلمة المرور</label>
          <input class="input" type="password" name="password" placeholder="أدخل كلمة المرور" autocomplete="current-password" required>
          @error('password')<div class="err">{{ $message }}</div>@enderror
        </div>
        <div class="field inline-check">
          <input type="checkbox" id="remember" name="remember" value="1" {{ old('remember')?'checked':'' }}>
          <label for="remember">احفظ معلوماتي</label>
        </div>
        <button class="btn btn-primary">تسجيل دخول</button>
        <p><a href="{{ route('password.request') }}">نسيت كلمة المرور؟</a></p>
      </form>
    </div>
  @endif

  {{-- =============== Register Tab =============== --}}
  @if(($tab ?? 'login') === 'register')
    <div class="card">
      @if(!($registrationEnabled ?? true))
        <p class="err">إنشاء الحسابات متوقف مؤقتًا.</p>
      @else
      <form method="post" action="{{ route('register.post') }}" class="rtl">
        @csrf
        <div class="field">
          <label>رقم الموبايل أو البريد الإلكتروني *</label>
          <input class="input" type="text" name="register_login" value="{{ old('register_login') }}" maxlength="191" autocomplete="username" required>
          @error('register_login')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="row">
          <div class="field">
            <label>الاسم الأول *</label>
            <input class="input" type="text" name="first_name" value="{{ old('first_name') }}" maxlength="100" autocomplete="given-name" required>
            @error('first_name')<div class="err">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>اسم العائلة *</label>
            <input class="input" type="text" name="last_name" value="{{ old('last_name') }}" maxlength="100" autocomplete="family-name" required>
            @error('last_name')<div class="err">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="field">
          <label>كلمة المرور * (12 حرفًا على الأقل، وتحتوي حروفًا وأرقامًا)</label>
          <input class="input" type="password" name="password" minlength="12" maxlength="72" autocomplete="new-password" required>
          @error('password')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="field">
          <label>الجنس *</label>
          <div class="gender-options">
            <label><input type="radio" name="gender" value="female" {{ old('gender')==='female'?'checked':'' }}> أنثى</label>
            <label><input type="radio" name="gender" value="male" {{ old('gender')==='male'?'checked':'' }}> ذكر</label>
          </div>
          @error('gender')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="row">
          <div class="field">
            <label>اليوم *</label>
            <input class="input" type="number" name="dob_day" min="1" max="31" value="{{ old('dob_day') }}" required>
            @error('dob_day')<div class="err">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>الشهر *</label>
            <input class="input" type="number" name="dob_month" min="1" max="12" value="{{ old('dob_month') }}" required>
            @error('dob_month')<div class="err">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>السنة *</label>
            <input class="input" type="number" name="dob_year" min="1900" max="{{ date('Y') }}" value="{{ old('dob_year') }}" required>
            @error('dob_year')<div class="err">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="row">
          <div class="field">
            <label>المحافظة *</label>
            <input class="input" type="text" name="governorate" value="{{ old('governorate') }}" placeholder="مثال: غزة / الخليل" maxlength="100" autocomplete="address-level1" required>
            @error('governorate')<div class="err">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>المدينة / القرية *</label>
            <input class="input" type="text" name="city" value="{{ old('city') }}" maxlength="120" autocomplete="address-level2" required>
            @error('city')<div class="err">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="field">
          <label>العنوان (المنطقة/الشارع) *</label>
          <input class="input" type="text" name="address" value="{{ old('address') }}" maxlength="255" autocomplete="street-address" required>
          @error('address')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div class="row">
          <div class="field">
            <label>رقم الجوال *</label>
            <input class="input" type="text" name="mobile" value="{{ old('mobile') }}" maxlength="30" autocomplete="tel" required>
            @error('mobile')<div class="err">{{ $message }}</div>@enderror
          </div>
          <div class="field">
            <label>رقم إضافي (اختياري)</label>
            <input class="input" type="text" name="alt_mobile" value="{{ old('alt_mobile') }}" maxlength="30" autocomplete="tel-national">
            @error('alt_mobile')<div class="err">{{ $message }}</div>@enderror
          </div>
        </div>

        <button class="btn btn-primary">إنشاء حساب</button>
      </form>
      @endif
    </div>
  @endif
</div>
@endsection
