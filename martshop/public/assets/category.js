'use strict';

(function(){
  const $  = (s, r=document)=>r.querySelector(s);
  const $$ = (s, r=document)=>Array.from(r.querySelectorAll(s));
  const modal = $('#quickModal');
  if (!modal) return;
  let productData = {};
  try { productData = JSON.parse(modal.dataset.products || '{}'); } catch (_) { productData = {}; }

  /* ====== عرض سريع ====== */
  const img   = $('#qImg');
  const title = $('#qTitle');
  const priceN= $('#qNew');
  const priceO= $('#qOld');
  const sizes = $('#qSizes');
  const view  = $('#qView');

  // حقول فورم الإضافة في المودال
  const qId    = $('#qId');
  const qSlug  = $('#qSlug');
  const qOffer = $('#qOfferId');
  const qOfferVariant = $('#qOfferVariantId');
  const qSize  = $('#qSize');
  const qColor = $('#qColor');
  const closeButton = $('.q-close', modal);
  let returnFocus = null;

  $$('.js-quick').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      returnFocus = btn;
      const card = btn.closest('.p-card');
      const p = productData[card.dataset.productKey];
      if(!p) return;

      const imgSrc = p.image && (p.image.startsWith('http') || p.image.startsWith('/'))
        ? p.image : (modal.dataset.assetBase || '/')+(p.image || 'assets/img/placeholder.png');

      img.src = imgSrc; img.alt = p.name || '';
      title.textContent = p.name || '';
      priceN.textContent = p.price ? ('₪'+(+p.price).toFixed(2)) : '';
      if(p.old_price && +p.old_price > +p.price){
        priceO.textContent='₪'+Math.round(p.old_price);
        priceO.hidden = false;
      } else { priceO.hidden = true; }

      // رابط عرض المنتج
      if(p.slug){
        try{ view.href = modal.dataset.productUrlTemplate.replace('__slug__', encodeURIComponent(p.slug)); }
        catch(_){ view.href = '#'; }
      } else { view.href = '#'; }

      // تعبئة حقول فورم الإضافة
      qId.value    = p.slug || (p.id ?? '');
      qSlug.value  = p.slug || '';
      qOffer.value = p.offer_id || '';
      qOfferVariant.value = '';
      qSize.value  = '';
      qColor.value = '';

      // متغيرات العرض الدقيق تحمل المعرّف والسعر والسمات المعتمدة من الخادم.
      sizes.innerHTML = '';
      const offerVariants = Array.isArray(p.offer_variants) ? p.offer_variants : [];
      if(offerVariants.length){
        offerVariants.forEach(variant=>{
          const attrs = variant.attributes || {};
          const labels = [];
          if(attrs.size) labels.push('المقاس: '+attrs.size);
          if(attrs.color) labels.push('اللون: '+attrs.color);

          const chip = document.createElement('button');
          chip.type = 'button';
          chip.className = 'size';
          chip.textContent = labels.join(' — ') || 'الخيار '+variant.id;
          chip.addEventListener('click', ()=>{
            sizes.querySelectorAll('.size.active').forEach(x=>x.classList.remove('active'));
            chip.classList.add('active');
            qOfferVariant.value = String(variant.id);
            qSize.value = attrs.size ? String(attrs.size) : '';
            qColor.value = attrs.color ? String(attrs.color) : '';
            priceN.textContent = variant.price !== null && typeof variant.price !== 'undefined'
              ? ('₪'+(+variant.price).toFixed(2)) : '';
          });
          sizes.appendChild(chip);
        });
      } else (p.sizes||[]).forEach(s=>{
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'size';
        chip.textContent = s;
        chip.addEventListener('click', ()=>{
          sizes.querySelectorAll('.size.active').forEach(x=>x.classList.remove('active'));
          chip.classList.add('active');
          qSize.value = String(s);
        });
        sizes.appendChild(chip);
      });
      sizes.querySelector('.size')?.click();

      modal.hidden = false;
      document.body.classList.add('modal-open');
      closeButton?.focus();
    });
  });
  closeButton?.addEventListener('click', close);
  modal.addEventListener('click', e=>{ if(e.target===modal) close(); });
  modal.addEventListener('mart:close-quick-view', close);
  document.addEventListener('keydown', event=>{
    if(event.key==='Escape' && !modal.hidden) close();
  });
  function close(){
    modal.hidden = true;
    document.body.classList.remove('modal-open');
    returnFocus?.focus();
  }

  /* ====== الفلاتر ====== */
  const productGrid = $('#productGrid');
  const cards = $$('.p-card', productGrid);
  const brandInputs = $$('.f-brand');
  const colorInputs = $$('.f-color');
  const sizeInputs  = $$('.f-size');
  const clearBtn = $('#clearFilters');

  const activeValues = nodes => new Set(nodes.filter(i=>i.checked).map(i=>i.value));

  function applyFilters(){
    const brands = activeValues(brandInputs);
    const colors = activeValues(colorInputs);
    const sizes  = activeValues(sizeInputs);

    cards.forEach(card=>{
      const b = (card.dataset.brand || '').trim();
      const c = (card.dataset.color || '').trim();
      const ss= (card.dataset.sizes || '').split(',').map(s=>s.trim()).filter(Boolean);

      const passBrand = brands.size ? brands.has(b) : true;
      const passColor = colors.size ? colors.has(c) : true;
      const passSize  = sizes.size  ? ss.some(s=>sizes.has(s)) : true;

      card.hidden = ! (passBrand && passColor && passSize);
    });
  }

  [...brandInputs, ...colorInputs, ...sizeInputs].forEach(inp=>{
    inp.addEventListener('change', applyFilters);
  });
  clearBtn?.addEventListener('click', ()=>{
    [...brandInputs, ...colorInputs, ...sizeInputs].forEach(i=> i.checked=false);
    applyFilters();
  });

  applyFilters();
})();


(function(){
  const form = document.getElementById('qAddForm');
  const cartBadge = document.getElementById('cartCountTop');

  function toast(msg){
    let box = document.getElementById('toastBox');
    if(!box){
      box = document.createElement('div');
      box.id = 'toastBox';
      box.setAttribute('role', 'status');
      box.setAttribute('aria-live', 'polite');
      box.className = 'category-toast';
      document.body.appendChild(box);
    }
    box.textContent = msg || 'تمت الإضافة للسلة';
    box.hidden = false;
    setTimeout(()=> { box.hidden = true; }, 1600);
  }

  if(form){
    form.addEventListener('submit', async function(e){
      e.preventDefault();
      const url = form.getAttribute('action');
      const fd  = new FormData(form);

      try{
        const res = await fetch(url, {
          method: 'POST',
          body: fd,
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        });

        if(!res.ok){
          toast('تعذّر الإضافة'); return;
        }
        const data = await res.json();

        // تحديث عداد السلة
        if(cartBadge && typeof data.count !== 'undefined'){
          cartBadge.textContent = data.count;
        }

        toast(data.msg || 'تمت الإضافة للسلة');

        // إغلاق المودال والبقاء في الصفحة
        document.getElementById('quickModal')
          ?.dispatchEvent(new CustomEvent('mart:close-quick-view'));
      }catch(_){
        toast('تعذّر الإضافة');
      }
    });
  }
})();
