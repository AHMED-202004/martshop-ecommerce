@extends('layouts.private-finance')
@section('title', 'مراجعة المنتجات والعروض')
@section('privacy-notice', 'صفحة مراجعة داخلية خاصة. تحقّق من بيانات العنصر وهوية التاجر، ولا تشارك محتوى الطلب أو ملاحظات المراجعة خارج فريق العمل المخوّل.')

@section('content')
<div class="container rtl page-pad">
  <h2>منتجات جديدة</h2>
  <div class="review-grid">
    @forelse($products as $product)
      <article class="panel">
        <div class="item-head">
          @if($product->submission_image_path)
            <img src="{{ route('product-submissions.image', $product) }}" alt="صورة المنتج المرسلة للمراجعة">
          @elseif($product->image)
            <img src="{{ asset($product->image) }}" alt="">
          @endif
          <div><h3>{{ $product->name }}</h3><p>{{ $product->category?->path }} · {{ $product->brand?->name ?? 'بدون ماركة' }}</p></div>
        </div>
        <p>{{ $product->description }}</p>
        <p><strong>التاجر:</strong> {{ $product->creatorMerchant?->user?->name }} · <strong>الحالة:</strong> {{ $product->status->value }}</p>
        <form method="POST" action="{{ route('admin.catalog.products.update', $product) }}" class="decision-form">
          @csrf @method('PATCH')
          <select name="decision" required><option value="active">اعتماد</option><option value="changes_requested">طلب تعديل</option><option value="rejected">رفض</option></select>
          <textarea name="reason" rows="2" placeholder="سبب القرار عند طلب تعديل أو الرفض"></textarea>
          <label>كلمة مرور حساب المراجع<input type="password" name="current_password" autocomplete="current-password" required></label>
          <button class="btn btn-primary">حفظ القرار</button>
        </form>
      </article>
    @empty
      <div class="panel">لا توجد منتجات بانتظار المراجعة.</div>
    @endforelse
  </div>
  {{ $products->links() }}

  <h2>عروض التجار</h2>
  <div class="review-grid">
    @forelse($offers as $offer)
      <article class="panel">
        <h3>{{ $offer->product->name }}</h3>
        <p><strong>التاجر:</strong> {{ $offer->merchant?->user?->name }} · <strong>المنطقة:</strong> {{ $offer->location?->name }}</p>
        <p><strong>السعر:</strong> ₪{{ number_format((float)$offer->price, 2) }} · <strong>المخزون:</strong> {{ $offer->stock }} · <strong>التجهيز:</strong> {{ $offer->preparation_time_days }} يوم</p>
        @if($offer->variants->isNotEmpty())<p><strong>المتغيرات:</strong> {{ $offer->variants->map(fn($v) => implode(' / ', $v->attributes))->implode('، ') }}</p>@endif
        <p><strong>حالة المنتج:</strong> {{ $offer->product->status->value }} · <strong>حالة العرض:</strong> {{ $offer->status->value }}</p>
        <form method="POST" action="{{ route('admin.catalog.offers.update', $offer) }}" class="decision-form">
          @csrf @method('PATCH')
          <select name="decision" required><option value="active">تفعيل العرض</option><option value="changes_requested">طلب تعديل</option><option value="rejected">رفض</option></select>
          <textarea name="reason" rows="2" placeholder="سبب القرار عند طلب تعديل أو الرفض"></textarea>
          <label>كلمة مرور حساب المراجع<input type="password" name="current_password" autocomplete="current-password" required></label>
          <button class="btn btn-primary">حفظ القرار</button>
        </form>
      </article>
    @empty
      <div class="panel">لا توجد عروض بانتظار المراجعة.</div>
    @endforelse
  </div>
  {{ $offers->links() }}

  <h2>اقتراحات تغيير المنتجات المنشورة</h2>
  <div class="review-grid">
    @forelse($changeRequests as $changeRequest)
      @php $changes = $changeRequest->proposed_changes; @endphp
      <article class="panel">
        <h3>{{ $changeRequest->product->name }}</h3>
        <p><strong>التاجر:</strong> {{ $changeRequest->merchant?->user?->name }}</p>
        <table class="compare-table">
          <tr><th>الحقل</th><th>الحالي</th><th>المقترح</th></tr>
          <tr><td>الاسم</td><td>{{ $changeRequest->product->name }}</td><td>{{ $changes['name'] }}</td></tr>
          <tr><td>التصنيف</td><td>{{ $changeRequest->product->category_id }}</td><td>{{ $changes['category_id'] }}</td></tr>
          <tr><td>الماركة</td><td>{{ $changeRequest->product->brand_id ?? '—' }}</td><td>{{ $changes['brand_id'] ?? '—' }}</td></tr>
          <tr><td>الموديل</td><td>{{ $changeRequest->product->model ?? '—' }}</td><td>{{ $changes['model'] ?? '—' }}</td></tr>
        </table>
        @if($changeRequest->proposed_image_path)<p><a href="{{ route('product-change-requests.image', $changeRequest) }}">عرض الصورة المقترحة الخاصة</a></p>@endif
        <form method="POST" action="{{ route('admin.catalog.change-requests.update', $changeRequest) }}" class="decision-form">
          @csrf @method('PATCH')
          <select name="decision" required><option value="approved">اعتماد وتطبيق</option><option value="changes_requested">طلب تعديل</option><option value="rejected">رفض</option></select>
          <textarea name="reason" rows="2" placeholder="سبب القرار عند طلب تعديل أو الرفض"></textarea>
          <label>كلمة مرور حساب المراجع<input type="password" name="current_password" autocomplete="current-password" required></label>
          <button class="btn btn-primary">حفظ القرار</button>
        </form>
      </article>
    @empty
      <div class="panel">لا توجد اقتراحات تغيير بانتظار المراجعة.</div>
    @endforelse
  </div>
  {{ $changeRequests->links() }}
</div>
@endsection

@push('styles')
<style>
.review-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;margin:14px 0 28px}.panel{background:#fff;border:1px solid #e7e7e7;border-radius:12px;padding:16px}.item-head{display:flex;gap:12px}.item-head img{width:90px;height:90px;object-fit:contain}.decision-form{display:grid;gap:8px}.decision-form select,.decision-form textarea{border:1px solid #ddd;border-radius:8px;padding:9px}.compare-table{width:100%;border-collapse:collapse;margin:10px 0}.compare-table th,.compare-table td{padding:7px;border:1px solid #eee;text-align:right}.notice{padding:12px;border-radius:8px;margin:12px 0}.success{background:#e9f8ee}.error{background:#fff0f0;color:#9f1d1d}@media(max-width:850px){.review-grid{grid-template-columns:1fr}}
</style>
@endpush
