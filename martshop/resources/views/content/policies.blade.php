








@extends('layouts.app')

@section('title', 'سياسات الشركة - Mart.ps')

@section('content')
<div class="container policies-page">



{{-- شريط المشتريات + البحث (مثل الرئيسية) --}}
@php
  $cartUrl = \Illuminate\Support\Facades\Route::has('cart.index')
              ? route('cart.index') : url('/cart');   // نتجنب خطأ cart.index
@endphp

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
</div>
  {{-- أعلى الصفحة: عنوان + تبويبات سريعة --}}
  <div class="policies-header">
    <h1>سياسات شركة مارت للتسويق الإلكتروني</h1>
    <div class="quick-tabs">
      <a href="#shipping">طرق التوصيل</a>
      <a href="#exchange">طلبات التبديل</a>
      <a href="#returns">الاسترداد والنزاعات</a>
      <a href="#payment">طرق الدفع</a>
      <a href="#privacy">سياسة الخصوصية</a>
    </div>
  </div>

  {{-- طرق التوصيل --}}
  <section id="shipping" class="card policy-card">
    <div class="card-title">طرق التوصيل</div>
    <ul class="note-list">
      <li>يتم توصيل المنتجات خلال خدمة التوصيل الخاصة بـ <strong>شركة مارت</strong>.</li>
      <li>{{ $deliveryText }}</li>
      <li>تظهر الرسوم النهائية بوضوح في السلة قبل تأكيد الطلب.</li>
    </ul>
  </section>

  {{-- سياسة التبديل --}}
  <section id="exchange" class="card policy-card">
    <div class="card-title">طلبات التبديل</div>
    <ol class="num-list">
      <li>احتفظ بالمنتج وتغليفه وملحقاته كما استلمتها، وقد يلزم فحص الحالة قبل اتخاذ القرار.</li>
      <li>طلب التبديل يخضع للمراجعة وتوفر البديل، ولا يُعد مقبولًا تلقائيًا عند إرساله.</li>
      <li>لا تسلّم منتجًا أو تدفع فرقًا خارج تعليمات موثقة من المنصة.</li>
      <li>تظهر أي رسوم أو فروقات معتمدة للعميل قبل تنفيذ التبديل.</li>
    </ol>
  </section>

  {{-- الاسترداد والنزاعات --}}
  <section id="returns" class="card policy-card">
    <div class="card-title">الاسترداد والنزاعات</div>
    <ol class="num-list">
      <li>يبدأ المسار الموثق من <a href="{{ route('orders.history') }}">تاريخ الطلبات</a> بفتح نزاع على طلب مسلّم ومدفوع.</li>
      <li>يمكن طلب مراجعة استرداد كامل للطلب الفرعي المرتبط بالنزاع قبل تحرير مستحقاته.</li>
      <li>تقديم الطلب لا يعني قبوله ولا يعني أن المبلغ حُوّل؛ تراجع الإدارة الطلب والأدلة أولًا.</li>
      <li>بعد الموافقة يحدد العميل وسيلة الاسترداد، ثم تُراجع قبل تسجيل الحوالة وإثباتها.</li>
      <li>تظهر حالة الطلب والتحويل في الحساب، ولا نعتمد مهلة أو رسومًا غير معروضة داخل المسار التشغيلي.</li>
    </ol>
  </section>

  {{-- طرق الدفع --}}
  <section id="payment" class="card policy-card">
    <div class="card-title">طرق الدفع</div>
    <ul class="note-list">
      <li>الدفع المتاح حاليًا هو <strong>التحويل اليدوي</strong> عبر وسائل الدفع التي تفعلها الإدارة.</li>
      <li>تظهر بيانات الحساب المعتمد بعد تأكيد جميع التجار للطلب؛ لا تحوّل إلى حساب غير ظاهر داخل المنصة.</li>
      <li>بعد التحويل، يرفع العميل رقم العملية والمبلغ والوقت وبيانات المرسل وإثبات الدفع للمراجعة.</li>
      <li>رفع الإثبات لا يعني قبول الدفع؛ تظهر نتيجة المراجعة وحالة الطلب داخل الحساب.</li>
      <li>عملة الموقع شيكل، ويظهر سعر الطرد ورسوم التوصيل بوضوح قبل تأكيد الطلب.</li>
    </ul>
  </section>

  {{-- سياسة الخصوصية --}}
  <section id="privacy" class="card policy-card">
    <div class="card-title">سياسة الخصوصية</div>
    <p class="p-text">
      نلتزم بحماية بياناتك الشخصية وعدم مشاركتها مع أي طرف ثالث إلا للضرورة المتعلقة بإتمام الطلب (شركة التوصيل/الدفع).
      قد نستخدم بيانات الاتصال (مثل رقم الهاتف) للتواصل حول الطلبات، العروض، أو حل المشاكل الفنية.
      باستخدامك الموقع فأنت توافق على سياسة الخصوصية هذه. يمكنك التواصل معنا لأي استفسار أو طلب حذف بياناتك.
    </p>
  </section>

</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/policies.css') }}">
@endpush








