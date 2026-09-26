@extends('layouts.private-finance')
@section('title','إعدادات المنصة')
@section('privacy-notice', 'إعدادات تشغيلية حساسة. يلزم تأكيد كلمة مرور حساب المسؤول عند الحفظ. لا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP، ولا تغيّر هذه الصفحة لقطات الطلبات السابقة.')
@section('content')
<div class="settings-page rtl">
  <p>القيم التشغيلية اليومية. كل تعديل يُسجل في سجل التدقيق.</p>
  <form method="POST" action="{{ route('admin.settings.update') }}">@csrf @method('PUT')
    <section class="panel"><h2>معلومات الموقع</h2><div class="grid">
      <label>اسم الموقع<input name="site[name]" value="{{ old('site.name', $settings['site.name']) }}" required></label>
      <label>هاتف التواصل<input name="site[contact_phone]" value="{{ old('site.contact_phone', $settings['site.contact_phone']) }}"></label>
      <label>واتساب<input name="site[whatsapp]" value="{{ old('site.whatsapp', $settings['site.whatsapp']) }}"></label>
      <label>البريد<input type="email" name="site[email]" value="{{ old('site.email', $settings['site.email']) }}"></label>
      <label>ساعات الدعم<input name="site[support_hours]" value="{{ old('site.support_hours', $settings['site.support_hours']) }}"></label>
      <label class="wide">نص التوصيل الافتراضي<textarea name="site[default_delivery_text]">{{ old('site.default_delivery_text', $settings['site.default_delivery_text']) }}</textarea></label>
    </div></section>
    <section class="panel"><h2>التشغيل والطوارئ</h2><div class="switches">
      @foreach(['chat_enabled'=>'إظهار نافذة الدعم','registration_enabled'=>'تسجيل العملاء','merchant_registration_enabled'=>'تسجيل تجار جدد','orders_enabled'=>'إنشاء طلبات جديدة'] as $key=>$label)
        <label><input type="checkbox" name="site[{{ $key }}]" value="1" @checked(old('site.'.$key, $settings['site.'.$key]) == '1')> {{ $label }}</label>
      @endforeach
    </div><label>تنبيه عام<textarea name="site[emergency_notice]" maxlength="500">{{ old('site.emergency_notice', $settings['site.emergency_notice']) }}</textarea></label></section>
    <section class="panel"><h2>الطلب والتوصيل</h2><div class="grid">
      <label>رسوم التوصيل (₪)<input type="number" step="0.01" min="0" name="checkout[shipping][flat_fee]" value="{{ old('checkout.shipping.flat_fee', $settings['checkout.shipping.flat_fee']) }}" required></label>
      <label>حد التوصيل المجاني (₪)<input type="number" step="0.01" min="0" name="checkout[shipping][free_threshold]" value="{{ old('checkout.shipping.free_threshold', $settings['checkout.shipping.free_threshold']) }}" required></label>
      <label>مدة حجز المخزون بالدقائق<input type="number" min="5" max="1440" name="checkout[reservation_minutes]" value="{{ old('checkout.reservation_minutes', $settings['checkout.reservation_minutes']) }}" required></label>
    </div></section>
    <section class="panel"><h2>مهلة النزاع والتسوية</h2>
      <p>تُحفظ المهلة عند تأكيد التسليم؛ تعديلها لا يختصر مهل الشحنات السابقة. تفعيل التحرير لا يفعّل السحوبات.</p>
      <label>المهلة بالأيام (1–90)<input type="number" min="1" max="90" required name="settlement[dispute_days]" value="{{ old('settlement.dispute_days', $settings['settlement.dispute_days']) }}"></label>
      <label><input type="checkbox" name="settlement[auto_release_enabled]" value="1" @checked(old('settlement.auto_release_enabled', $settings['settlement.auto_release_enabled']) == '1')> تفعيل التحرير التلقائي للشحنات المؤهلة بعد انتهاء المهلة</label>
    </section>
    <section class="panel"><h2>اتفاقية مستوى خدمة الدعم</h2>
      <p>تُحفظ المهلة النهائية على كل تذكرة جديدة؛ تعديل القيمة لا يغير التذاكر السابقة.</p>
      <label>مهلة أول استجابة بالدقائق (5–10080)<input type="number" min="5" max="10080" required name="support[first_response_sla_minutes]" value="{{ old('support.first_response_sla_minutes', $settings['support.first_response_sla_minutes']) }}"></label>
    </section>
    <section class="panel"><label>سبب التعديل (إلزامي)<textarea name="reason" minlength="3" maxlength="500" required>{{ old('reason') }}</textarea></label><label>كلمة مرور حساب المسؤول<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-primary">حفظ الإعدادات</button></section>
  </form>
</div>
@endsection
@push('styles')
<style>
.settings-page{max-width:1050px;margin:0 auto}.settings-page .panel{background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:16px;margin:14px 0}.settings-page .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.settings-page label{display:grid;gap:5px}.settings-page input,.settings-page textarea{padding:9px;border:1px solid #a7b4c5;border-radius:7px}.settings-page textarea{min-height:80px}.settings-page .wide{grid-column:1/-1}.switches{display:flex;gap:18px;flex-wrap:wrap;margin-bottom:15px}.switches label{display:flex;align-items:center;gap:6px}.btn-primary{background:#173e73;color:#fff}@media(max-width:700px){.settings-page .grid{grid-template-columns:1fr}}
</style>
@endpush
