











@extends('layouts.app')

@section('title', 'أسئلة شائعة')

@section('content')
<div class="container hp-topwrap">

  {{-- صف الأدوات (مثل الرئيسية): سلة + بحث --}}
  
<div class="hp-topwrap">
  <div class="container">
    <div class="hp-row">
     


 <form action="{{ url('/search') }}" method="GET" class="hp-search" role="search">
      <input
        type="search"
        name="q"
        placeholder="ابحثْ في مارت..."
        aria-label="ابحثْ في مارت"
      />
      <button type="submit" aria-label="بحث">
        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true">
          <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5A6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19zM4 9.5C4 6.46 6.46 4 9.5 4S15 6.46 15 9.5S12.54 15 9.5 15S4 12.54 4 9.5"/>
        </svg>
      </button>
  
      </form>
    </div>
  </div>
  

  {{-- تبويبات أعلى الصفحة للقفز بين الأقسام --}}


  {{-- القسم 1: أسئلة عن عملية الشراء --}}
  <section id="shop" class="faq-section">
    <div class="faq-title-tag">أسئلة عن عملية الشراء</div>
    <h2 class="faq-h2">أسئلة شائعة</h2>

    <article class="qa">
      <h3 class="faq-q">1) وين موقعكم؟</h3>
      <p class="faq-a">
        شركـة مارت للتسويق الإلكتروني تعمل داخل فلسطين وتعرض منتجات لتجّار محليين، مع خدمة زبائن على مدار الساعة وتوصيل سريع. مقرّنا الإداري يقع في الجنوب/الوسط (حسب فرعك) ونغطي أغلب المناطق المذكورة في سياسة الشحن.
      </p>
    </article>

    <article class="qa">
      <h3 class="faq-q">2) كيف بامكاني أشتري (أطلب)؟</h3>
      <p class="faq-a">ادخل صفحة المنتج، حدّد القياس/اللون (إن وُجد) ثم اضغط “اضف إلى المشتريات”، وأكمل خطوات تأكيد الطلب.</p>
      <div class="faq-steps">
        {{-- ضَع صورة الخطوات 1–6 هنا --}}

      </div>
    </article>


  </section>

  {{-- القسم 2: أسئلة عن المنتجات والتبديل --}}
  <section id="products" class="faq-section">
    <div class="faq-title-tag">أسئلة عن المنتجات والتبديل</div>

    <article class="qa">
      <h3 class="faq-q">1) إذا ما طلع القياس مناسب، بقدر أبدّل؟</h3>
      <p class="faq-a">
        يمكنك تقديم طلب ومراجعته مع خدمة العملاء. يعتمد القرار على حالة المنتج وتوفر البديل، ولا يُعد التبديل مقبولًا تلقائيًا قبل المراجعة.
      </p>
    </article>

    <article class="qa">
      <h3 class="faq-q">2) ما قدرت أفحص القياس لحظة الاستلام؟</h3>
      <p class="faq-a">
        احتفظ بالمنتج وتغليفه وملحقاته كما استلمتها، وراجع <a href="{{ route('policies') }}#exchange">طلبات التبديل</a> قبل اتخاذ أي إجراء.
      </p>
    </article>

    <article class="qa">
      <h3 class="faq-q">3) بقدر أرجّع الطلب إذا طلع فيه خَلل؟</h3>
      <p class="faq-a">
        يمكنك فتح نزاع من تاريخ الطلبات بعد التسليم، ثم طلب مراجعة الاسترداد إذا انطبقت شروط المسار. تقديم الطلب لا يضمن الموافقة أو التحويل.
      </p>
    </article>

    <article class="qa">
      <h3 class="faq-q">4) ألوان/شكل المنتج يختلف عن الصورة؟</h3>
      <p class="faq-a">
        نحاول عرض صور واقعية قدر الإمكان، لكن قد تختلف الإضاءة أو شاشة هاتفك. إن كان الاختلاف مؤثّر، تواصل معنا لنبدّل بما يناسبك.
      </p>
    </article>

    <article class="qa">
      <h3 class="faq-q">5) المنتج أصلي؟</h3>
      <p class="faq-a">
        نعرض تشكيلة واسعة من المنتجات (أصلية وماركات تجارية/اقتصادية). لو المنتج أصلي بنذكر ذلك في الاسم/الوصف بوضوح.
      </p>
    </article>

   
  </section>

  {{-- القسم 3: أسئلة عن التوصيل --}}
  <section id="shipping" class="faq-section">
    <div class="faq-title-tag">أسئلة عن التوصيل</div>

    <article class="qa">
      <h3 class="faq-q">1) كم تكلفة التوصيل؟</h3>
      <p class="faq-a">
        تظهر الرسوم المحسوبة وفق الإعدادات التشغيلية الحالية في السلة قبل تأكيد الطلب. راجع <a href="{{ url('/policies#shipping') }}">سياسة الشحن</a>.
      </p>
    </article>

    <article class="qa">
      <h3 class="faq-q">2) هل رسوم التوصيل تُفرض على كل منتج؟</h3>
      <p class="faq-a">
        لا، الرسوم تُحتسب على الطلب كاملًا (السلة) وليس على كل قطعة على حدة.
      </p>
    </article>

    <article class="qa">
      <h3 class="faq-q">3) كم يحتاج الطلب ليصلني؟</h3>
      <p class="faq-a">
        تعتمد المدة على تأكيد التاجر والتجهيز والإسناد والتحديثات التشغيلية. تابع حالة الطلب من حسابك.
      </p>
    </article>

    
  </section>

</div>
@endsection
