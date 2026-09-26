/* Shared support widget. */
(function () {
  const widget = document.getElementById('support-widget');
  if (!widget) return;

  const chatBox = document.getElementById('chatBox');
  const chatFab = document.getElementById('chatFab');
  const chatMin = document.getElementById('chatMinBtn');
  const chatBody = document.getElementById('chatBody');
  const contactLink = chatBody?.querySelector('a');

  function openChat() {
    widget.classList.remove('chat-min');
    widget.classList.add('chat-open');
    chatBox?.classList.add('is-open');
    setTimeout(() => chatBody?.scrollTo({ top: chatBody.scrollHeight, behavior: 'smooth' }), 50);
    contactLink?.focus();
  }

  function minimizeChat() {
    widget.classList.add('chat-min');
    widget.classList.remove('chat-open');
    chatBox?.classList.remove('is-open');
    chatFab?.focus();
  }

  chatFab?.addEventListener('click', openChat);
  chatMin?.addEventListener('click', minimizeChat);
  widget.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && widget.classList.contains('chat-open')) minimizeChat();
  });
  window.openSupportChat = openChat;
})();

/* Merchant catalogue create-mode fields. */
(function () {
  const modeInputs = document.querySelectorAll('input[name="product_mode"]');
  const existing = document.getElementById('existingFields');
  const fresh = document.getElementById('newFields');
  if (!modeInputs.length || !existing || !fresh) return;

  function toggleMode() {
    const mode = document.querySelector('input[name="product_mode"]:checked')?.value || 'existing';
    existing.hidden = mode !== 'existing';
    fresh.hidden = mode !== 'new';
    existing.querySelectorAll('input,select,textarea').forEach(el => { el.disabled = mode !== 'existing'; });
    fresh.querySelectorAll('input,select,textarea').forEach(el => { el.disabled = mode !== 'new'; });
  }

  modeInputs.forEach(input => input.addEventListener('change', toggleMode));
  toggleMode();
})();

/* Checkout payment-method highlight. */
(function () {
  const cards = document.querySelectorAll('.pay-card');
  cards.forEach(card => {
    card.querySelector('input[type="radio"]')?.addEventListener('change', () => {
      cards.forEach(other => other.classList.remove('is-active'));
      card.classList.add('is-active');
    });
  });
})();

/* Product option selection, support shortcut and asynchronous add-to-cart. */
(function () {
  document.querySelectorAll('.size-chip').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.size-chip').forEach(other => other.classList.remove('active'));
      btn.classList.add('active');
      const wrap = btn.closest('.pd-info');
      if (wrap) wrap.dataset.size = btn.dataset.size;
      const hidden = document.getElementById('prodSizeHidden');
      if (hidden) hidden.value = String(btn.dataset.size || '');
    });
  });

  document.querySelectorAll('.color-chip').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.color-chip').forEach(other => other.classList.remove('active'));
      btn.classList.add('active');
      const hidden = document.getElementById('prodColorHidden');
      if (hidden) hidden.value = String(btn.dataset.color || '');
    });
  });

  document.querySelectorAll('.variant-chip').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.variant-chip').forEach(other => other.classList.remove('active'));
      btn.classList.add('active');
      const variant = document.getElementById('offerVariantHidden');
      const size = document.getElementById('prodSizeHidden');
      const color = document.getElementById('prodColorHidden');
      if (variant) variant.value = String(btn.dataset.variantId || '');
      if (size) size.value = String(btn.dataset.size || '');
      if (color) color.value = String(btn.dataset.color || '');
      const price = Number.parseFloat(btn.dataset.price || '');
      const priceLabel = document.querySelector('.pd-price .now');
      if (priceLabel && Number.isFinite(price)) priceLabel.textContent = '₪' + price.toFixed(2);
    });
  });

  const askBtn = document.getElementById('askBtn');
  askBtn?.addEventListener('click', event => {
    event.preventDefault();
    if (window.openSupportChat) window.openSupportChat();
    else if (askBtn.dataset.contactUrl) window.location.assign(askBtn.dataset.contactUrl);
  });

  const cartBadge = document.getElementById('cartCountTop');
  const productForm = document.querySelector('form.js-add-cart');
  function toast(message) {
    let box = document.getElementById('toastBox');
    if (!box) {
      box = document.createElement('div');
      box.id = 'toastBox';
      box.className = 'mart-toast';
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      document.body.appendChild(box);
    }
    box.textContent = message || 'تمت الإضافة للسلة';
    box.hidden = false;
    setTimeout(() => { box.hidden = true; }, 1600);
  }
  productForm?.addEventListener('submit', async function (event) {
    event.preventDefault();
    try {
      const response = await fetch(this.getAttribute('action'), {
        method: 'POST', body: new FormData(this),
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      });
      if (!response.ok) { toast('تعذّر الإضافة'); return; }
      const data = await response.json();
      if (cartBadge && typeof data.count !== 'undefined') cartBadge.textContent = data.count;
      toast(data.msg || 'تمت الإضافة للسلة');
    } catch (_) {
      toast('تعذّر الإضافة');
    }
  });

  const firstVariant = document.querySelector('.variant-chip');
  if (firstVariant) firstVariant.click();
  else {
    document.querySelector('.size-chip')?.click();
    document.querySelector('.color-chip')?.click();
  }
})();

/* New-products category drawer. */
(function () {
  const btn = document.getElementById('catsBtn');
  const drawer = document.getElementById('catsDrawer');
  const closeBtn = document.getElementById('catsClose');
  if (!btn || !drawer) return;
  function setOpen(open) {
    drawer.classList.toggle('show', open);
    drawer.setAttribute('aria-hidden', String(!open));
    btn.setAttribute('aria-expanded', String(open));
    if (open) closeBtn?.focus();
  }
  function close(restoreFocus = false) {
    setOpen(false);
    if (restoreFocus) btn.focus();
  }
  btn.addEventListener('click', () => setOpen(!drawer.classList.contains('show')));
  closeBtn?.addEventListener('click', () => close(true));
  document.addEventListener('click', event => {
    if (drawer.classList.contains('show') && !event.target.closest('#catsDrawer') && !event.target.closest('#catsBtn')) close();
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') close(true); });
})();

/* Cart quantity autosave and category shortcut. */
(function () {
  document.querySelectorAll('.qty-form').forEach(form => {
    const input = form.querySelector('input[name="qty"]');
    const submit = form.querySelector('button[type="submit"]');
    if (!input || !submit) return;
    const original = String(input.value);
    submit.disabled = true;
    let timer = null;
    function changed() {
      const isChanged = String(input.value) !== original;
      submit.disabled = !isChanged;
      clearTimeout(timer);
      if (isChanged) timer = setTimeout(() => { submit.disabled = true; form.submit(); }, 700);
    }
    input.addEventListener('input', changed);
    input.addEventListener('change', changed);
    input.addEventListener('keydown', event => {
      if (event.key === 'Enter') {
        event.preventDefault();
        if (!submit.disabled) { submit.disabled = true; form.submit(); }
      }
    });
  });

  const template = document.getElementById('catBtnTpl');
  if (!template) return;
  const node = template.content.firstElementChild.cloneNode(true);
  const header = document.querySelector('header, .site-header, .app-header, #app header');
  if (header) {
    header.classList.add('cart-shortcut-host');
    header.appendChild(node);
  } else {
    node.classList.add('header-cat-btn--fixed');
    document.body.appendChild(node);
  }
  const button = node.querySelector('.cat-btn');
  if (window.matchMedia('(hover: none)').matches && button) {
    button.addEventListener('click', event => { event.preventDefault(); node.classList.toggle('touch-open'); });
    document.addEventListener('click', event => { if (!node.contains(event.target)) node.classList.remove('touch-open'); }, true);
  }
})();

/* =======================
   1) سلايدر تلقائي + أسهم + مؤشرات + لمس/كيبورد
   ======================= */
(function () {
  const slider = document.getElementById('slider');
  if (!slider) return;

  const slides = Array.from(slider.querySelectorAll('.slide'));
  const prev = document.getElementById('prev');
  const next = document.getElementById('next');
  const hasMany = slides.length > 1;
  if (!slides.length) return;

  // مؤشرات (dots)
  let dotsWrap = slider.querySelector('.dots');
  if (!dotsWrap && hasMany) {
    dotsWrap = document.createElement('div');
    dotsWrap.className = 'dots slider-dots';
    dotsWrap.innerHTML = slides.map((_,k)=>(
      `<button class="slider-dot" data-dot="${k}" aria-label="${k + 1}"></button>`
    )).join('');
    slider.appendChild(dotsWrap);
  }
  const dots = dotsWrap ? Array.from(dotsWrap.querySelectorAll('[data-dot]')) : [];

  let i = 0, timer = null, paused = false;

  function setDot(idx){
    dots.forEach((d,k)=>{
      const active = k === idx;
      d.classList.toggle('is-active', active);
      d.setAttribute('aria-current', String(active));
    });
  }

  function show(idx) {
    slides.forEach((s, k) => s.classList.toggle('active', k === idx));
    setDot(idx);
    i = idx;
  }
  function nextSlide() { show((i + 1) % slides.length); }
  function prevSlide() { show((i - 1 + slides.length) % slides.length); }

  function autoplay() {
    if (!hasMany) return;
    clearInterval(timer);
    if (!paused) timer = setInterval(nextSlide, 5000);
  }
  function restart() {
    clearInterval(timer);
    autoplay();
  }

  if (next) next.addEventListener('click', () => { nextSlide(); restart(); });
  if (prev) prev.addEventListener('click', () => { prevSlide(); restart(); });

  if (dots.length){
    dots.forEach(d=> d.addEventListener('click', ()=>{
      const idx = +d.dataset.dot;
      show(idx); restart();
    }));
  }

  // إيقاف أثناء المرور بالماوس
  slider.addEventListener('mouseenter', ()=>{ paused = true; clearInterval(timer); });
  slider.addEventListener('mouseleave', ()=>{ paused = false; autoplay(); });

  // إيقاف عند إخفاء التبويب
  document.addEventListener('visibilitychange', ()=>{
    paused = document.hidden;
    paused ? clearInterval(timer) : autoplay();
  });

  // لمس (سحب)
  let startX = null;
  slider.addEventListener('touchstart', (e)=>{ startX = e.touches[0].clientX; }, {passive:true});
  slider.addEventListener('touchend', (e)=>{
    if (startX == null) return;
    const dx = e.changedTouches[0].clientX - startX;
    if (Math.abs(dx) > 40){
      dx < 0 ? nextSlide() : prevSlide();
      restart();
    }
    startX = null;
  });

  // أسهم الكيبورد
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'ArrowLeft'){ e.preventDefault(); nextSlide(); restart(); }
    if (e.key === 'ArrowRight'){ e.preventDefault(); prevSlide(); restart(); }
  });

  show(0);
  autoplay();
})();

/* =====================================
   3) Mega Menu للـ Categories (Hover/Focus)
   ===================================== */
(function(){
  const list = document.getElementById('catList');
  const mega = document.getElementById('megaMenu');
  const content = document.getElementById('megaContent');
  if(!list || !mega || !content) return;

  // Navigation comes only from the server-filtered public category tree.
  // The static object above is retained temporarily as reference data, but is
  // deliberately not used when the database tree is unavailable.
  const dataCarrier = document.getElementById('martCategoryMenuData');
  let DATA = {};
  try { DATA = JSON.parse(dataCarrier?.dataset.menu || '{}'); } catch (_) { DATA = {}; }

  function render(cat){
    const d = DATA[cat];
    if(!d){ mega.classList.remove('show'); return; }
    const fragment = document.createDocumentFragment();
    d.cols.forEach(col => {
      const column = document.createElement('div');
      column.className = 'col';
      if (col.title) {
        const title = document.createElement('div');
        title.className = 'col-title';
        title.textContent = col.title;
        column.appendChild(title);
      }
      const list = document.createElement('ul');
      col.items.forEach(([text, href]) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        link.textContent = text;
        link.href = href;
        item.appendChild(link);
        list.appendChild(item);
      });
      column.appendChild(list);
      fragment.appendChild(column);
    });
    content.replaceChildren(fragment);
    mega.classList.add('show');
  }

  let hideTimer=null;
  list.querySelectorAll('li[data-cat]').forEach(li=>{
    li.addEventListener('mouseenter', ()=>{ clearTimeout(hideTimer); render(li.dataset.cat); });
    li.addEventListener('focusin',   ()=>{ clearTimeout(hideTimer); render(li.dataset.cat); });
    // On wide touch screens open the mega menu. On phones, keep the native
    // anchor navigation because the mega menu is intentionally hidden there.
    li.addEventListener('touchstart', (e)=>{
      if (window.matchMedia('(min-width: 993px)').matches) {
        e.preventDefault();
        render(li.dataset.cat);
      }
    });
  });

  function scheduleHide(){ hideTimer = setTimeout(()=> mega.classList.remove('show'), 220); }
  list.addEventListener('mouseleave', scheduleHide);
  mega.addEventListener('mouseenter', ()=> clearTimeout(hideTimer));
  mega.addEventListener('mouseleave', scheduleHide);

  // إغلاق عند الضغط خارجها أو زر ESC
  document.addEventListener('click', (e)=>{
    if (!mega.classList.contains('show')) return;
    const within = e.target.closest('#megaMenu') || e.target.closest('#catList [data-cat]');
    if (!within) mega.classList.remove('show');
  });
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape') mega.classList.remove('show');
  });
})();

/* ===========================================
   5) فلاتر صفحة Super Deals - إرسال تلقائي
   يشتغل فقط إذا وجد الفورم #filtersForm
   =========================================== */
(function () {
  const form = document.getElementById('filtersForm');
  if (!form) return; // لو مش بصفحة الديلز، اطلع

  // أي تغيير (checkbox/radio/select) يرسل الفورم
  const inputs = form.querySelectorAll('input[type="checkbox"], input[type="radio"], select');
  const submit = () => (form.requestSubmit ? form.requestSubmit() : form.submit());
  inputs.forEach(el => el.addEventListener('change', submit));

  // (اختياري) لو عندك زر Reset داخل الفورم خليه يعمل تنظيف سريع بدل ريفرش كامل
  const resetBtn = form.querySelector('a.btn, button[type="reset"]');
  if (resetBtn && resetBtn.tagName === 'BUTTON') {
    resetBtn.addEventListener('click', () => {
      form.reset();
      submit();
    });
  }

  // (اختياري) دعم زر "التصنيفات" الصغير لو ضفته بالصفحة
  const toggle = document.getElementById('catsToggle');
  const panel  = document.getElementById('catsPanel');
  if (toggle && panel) {
    toggle.addEventListener('click', () => panel.classList.toggle('show'));
    document.addEventListener('click', (e) => {
      if (!panel.contains(e.target) && !toggle.contains(e.target)) panel.classList.remove('show');
    });
  }
})();



(function(){
  if (!document.querySelector('.qv-btn')) return;
  const dataCarrier = document.getElementById('martQuickViewProductsData');
  let products = {};
  try { products = JSON.parse(dataCarrier?.dataset.products || '{}'); } catch (_) { products = {}; }

  let mask = document.createElement('div');
  mask.className = 'qv-mask';
  mask.hidden = true;
  mask.innerHTML = `
    <div class="qv-panel" role="dialog" aria-modal="true" aria-labelledby="brandQuickViewTitle">
      <div class="qv-header">
        <div class="qv-heading">عرض سريع</div>
        <button class="qv-close" type="button" aria-label="إغلاق العرض السريع">إغلاق</button>
      </div>
      <div class="qv-grid">
        <img class="qv-img" src="" alt="">
        <div>
          <h3 class="qv-title" id="brandQuickViewTitle"></h3>
          <div class="qv-price">
            <strong class="qv-now"></strong>
            <span class="qv-old"></span>
          </div>
          <div class="qv-brand"></div>

          <div class="qv-chunk">
            <div class="qv-chunk-title">المقاسات</div>
            <div class="qv-chips"></div>
          </div>

          <div class="qv-actions">
            <a class="btn btn-primary-outline" target="_self">فتح صفحة المنتج</a>
          </div>
        </div>
      </div>
    </div>`;
  document.body.appendChild(mask);

function money(n){ return '₪' + Number(n||0).toFixed(2); }
let returnFocus = null;
function show(data, trigger){
  returnFocus = trigger;
  // تعبئة العناصر الأساسية
  mask.querySelector('.qv-img').src = data.img;
  mask.querySelector('.qv-img').alt = data.name;
  mask.querySelector('.qv-title').textContent = data.name;
  mask.querySelector('.qv-brand').textContent = data.brand ? ('الماركة: ' + data.brand) : '';

  const now = data.hasSale ? data.sale : data.price;
  const old = data.price;

  mask.querySelector('.qv-now').textContent = money(now);
  const oldEl = mask.querySelector('.qv-old');
  oldEl.textContent = data.hasSale ? money(old) : '';
  oldEl.hidden = !data.hasSale;

  // المقاسات (عرض فقط)
  const chips = mask.querySelector('.qv-chips');
  chips.innerHTML = '';
  (data.sizes_alpha||[]).forEach(s=>{
    const b=document.createElement('span'); b.className='qv-chip'; b.textContent=s; chips.appendChild(b);
  });
  (data.sizes_num||[]).forEach(s=>{
    const b=document.createElement('span'); b.className='qv-chip'; b.textContent=s; chips.appendChild(b);
  });

  // رابط صفحة المنتج
  mask.querySelector('.qv-actions a').href = data.url;

  mask.hidden = false;
  mask.querySelector('.qv-close').focus();
}

  function hide(){
    mask.hidden = true;
    returnFocus?.focus();
  }

  mask.addEventListener('click', (e)=>{ if(e.target.classList.contains('qv-mask')) hide(); });
  mask.querySelector('.qv-close').addEventListener('click', hide);

  // فتح العرض السريع
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest('.qv-btn');
    if(!btn) return;
    const data = products[btn.dataset.qvKey];
    if(data) show(data, btn);
  });

  document.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape' && !mask.hidden) hide();
  });

})();















/* ===== عداد السلة العام ===== */
(function () {
  const BADGE_SELECTORS = [
    '#cartCountTop',                 // شارة الهيدر المصغّرة
    '.header-cart .badge',           // شارة داخل زر المشتريات في الهيدر
    '.cart-count', '.cart-badge'     // أي شارات أخرى إن وُجدت
  ];

  function qsa(sel) { return Array.from(document.querySelectorAll(sel)); }
  function getCartCount() {
    // القيمة الأولية مرسلة من جلسة Laravel داخل القالب.
    for (const s of BADGE_SELECTORS) {
      const el = document.querySelector(s);
      if (el) {
        const n = parseInt((el.textContent || '0').replace(/[^\d]/g,''), 10);
        if (!Number.isNaN(n)) return n;
      }
    }
    return 0;
  }
  function setCartCount(n) {
    const v = Math.max(0, parseInt(n || 0, 10));
    BADGE_SELECTORS.forEach(sel => {
      qsa(sel).forEach(el => { el.textContent = String(v); });
    });
  }

  window.setCartCount = setCartCount;
  window.refreshCartCount = async function () {
    const url = document.querySelector('meta[name="cart-count-url"]')?.content;
    if (!url) return;
    const response = await fetch(url, {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    });
    if (!response.ok) return;
    const data = await response.json();
    if (typeof data.count === 'number') setCartCount(data.count);
  };

  // وحّد كل الشارات على القيمة التي أرسلها الخادم في القالب.
  setCartCount(getCartCount());

  // لا نقبل زيادات متوقعة؛ التحديث يجب أن يحمل العدد المؤكد من الخادم.
  window.addEventListener('cart:changed', (ev) => {
    const d = ev.detail || {};
    if (typeof d.count === 'number') setCartCount(d.count);
  });
})();


