<footer class="site-footer">
  <div class="container footer-inner">
    <div class="copy">
       © جميع الحقوق محفوظة لدى شركة مارت للتسويق الإلكتروني لعام   {{ date('Y') }}
    </div>
    <div class="support-details" aria-label="قنوات الدعم">
      @if(!empty($siteSettings['contact_phone']))
        <span>هاتف الدعم: <bdi>{{ $siteSettings['contact_phone'] }}</bdi></span>
      @endif
      @if(!empty($siteSettings['whatsapp']))
        <span>واتساب: <bdi>{{ $siteSettings['whatsapp'] }}</bdi></span>
      @endif
      @if(!empty($siteSettings['email']))
        <a href="mailto:{{ $siteSettings['email'] }}">{{ $siteSettings['email'] }}</a>
      @endif
      @if(!empty($siteSettings['support_hours']))
        <span>ساعات الدعم: {{ $siteSettings['support_hours'] }}</span>
      @endif
      <span>الدفع عبر وسائل التحويل المفعّلة داخل المنصة.</span>
    </div>
  </div>
</footer>
