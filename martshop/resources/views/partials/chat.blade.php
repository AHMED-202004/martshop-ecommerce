<!-- Support panel -->
<div id="support-widget" class="chat-min">
  <!-- زر مصغّر -->
  <button class="chat-fab" id="chatFab" aria-label="فتح نافذة الدعم">
    <span>الدعم</span> <i class="fa-solid fa-headset"></i>
  </button>

  <!-- نافذة الدعم -->
  <div class="chat-box" id="chatBox" role="dialog" aria-modal="false" aria-labelledby="supportPanelTitle">
    <div class="chat-header">
      <div class="chat-title" id="supportPanelTitle">مساعدة ودعم</div>
      <div class="chat-actions">
        <button class="chat-minimize" id="chatMinBtn" type="button" title="تصغير" aria-label="تصغير نافذة الدعم">−</button>
      </div>
    </div>

    <div class="chat-body" id="chatBody">
      <p>أرسل استفسارك عبر نموذج التواصل ليُحفظ ويصل إلى فريق الدعم.</p>
      <a class="btn btn-primary" href="{{ route('contact.create') }}">فتح نموذج التواصل</a>
    </div>
  </div>
</div>
