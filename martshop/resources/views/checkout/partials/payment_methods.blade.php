@php
  $picked = 'manual_transfer';
@endphp

<form id="paymentForm" method="POST" action="{{ route('checkout.confirm') }}" class="rtl payment-form">
  @csrf
  @if(!empty($checkoutToken))
    <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
  @endif
  <h3 class="payment-title">اختر طريقة الدفع</h3>

  <div class="pay-grid">
    {{-- التحويل يتم بعد تأكيد التاجر --}}
    <label class="pay-card {{ $picked==='manual_transfer' ? 'is-active' : '' }}">
      <input type="radio" name="payment_method" value="manual_transfer" {{ $picked==='manual_transfer' ? 'checked' : '' }} hidden required>
      <div class="box">
        <div class="pay-card-icon" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></div>
        <div>تحويل يدوي بعد تأكيد توفر المنتجات</div>
      </div>
    </label>
  </div>

  {{-- تمرير الإجمالي (اختياري) لو كان متاحًا في الصفحة --}}
  @isset($total)
    <input type="hidden" name="amount" value="{{ $total }}">
  @endisset

  <div class="payment-submit">
    <button class="btn btn-success">تأكيد الطلب</button>
  </div>
</form>

