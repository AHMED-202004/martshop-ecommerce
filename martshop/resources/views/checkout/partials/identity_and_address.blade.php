@once
  @push('styles')
    <link rel="stylesheet" href="{{ asset('assets/checkout.css') }}">
  @endpush
@endonce

{{-- زائر؟ أظهر أزرار الدخول/التسجيل مثل الصورة 3 --}}
@guest
  <div class="steps-guest">
    <div class="step-box step-box-spaced">
      <strong>زبون قديم في مارت؟</strong>
      <div><a class="btn btn-warning" href="{{ route('login', ['tab' => 'login']) }}">تسجيل الدخول</a></div>
    </div>
    <div class="step-box">
      <strong>زبون جديد في مارت؟</strong>
      <div><a class="btn btn-warning" href="{{ route('login', ['tab' => 'register']) }}">ادخل معلوماتك</a></div>
    </div>
  </div>
@endguest

{{-- مسجّل دخول؟ أظهر العنوان + زر التحديث (بدون زر إضافة عنوان جديد) --}}
@auth
  <div class="address-summary">
    <h3 class="checkout-section-heading">عنوان الشحن</h3>
    <div>
      <div>{{ auth()->user()->first_name }} {{ auth()->user()->last_name }}</div>
      <div>{{ auth()->user()->city }}</div>
      <div>{{ auth()->user()->governorate }}</div>
      <div>{{ auth()->user()->address }}</div>
      <div>{{ auth()->user()->mobile }}</div>
    </div>
    <div class="checkout-action">
      <a href="{{ route('address.edit') }}" class="btn btn-success">تحديث</a>
    </div>
  </div>

  {{-- طرق الدفع مثل الصورة 2 --}}
  @include('checkout.partials.payment_methods')
@endauth
