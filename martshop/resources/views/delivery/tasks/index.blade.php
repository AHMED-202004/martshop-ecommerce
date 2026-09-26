@extends('layouts.private-finance')
@section('title', 'مهام التوصيل الخاصة بي')
@section('privacy-notice', 'مهام خاصة تحتوي بيانات اتصال وعناوين دقيقة للضرورة التشغيلية فقط. لا تشاركها أو تستخدمها خارج المهمة، ولا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP.')

@section('content')
<div class="worker-tasks rtl">
  <p>لا تظهر لك إلا المهام المسندة إلى حسابك.</p>
  @php($availability = $workerProfile?->availability ?? \App\Enums\DeliveryWorkerAvailability::Available)
  <section class="panel"><h2>حالة التوفر</h2><p>الحالة الحالية: {{ $availability->label() }} — الموقع الحالي لا تجمعه المنصة.</p>
    <form method="POST" action="{{ route('delivery.tasks.availability') }}">@csrf @method('PATCH')
      <label>حالتي<select name="availability">@foreach(\App\Enums\DeliveryWorkerAvailability::cases() as $state) @if($state !== \App\Enums\DeliveryWorkerAvailability::Suspended)<option value="{{ $state->value }}" @selected($availability === $state)>{{ $state->label() }}</option>@endif @endforeach</select></label>
      <input name="reason" maxlength="500" placeholder="ملاحظة اختيارية"><button class="btn">حفظ الحالة</button>
    </form>
  </section>

  @forelse($deliveries as $delivery)
    <article class="panel">
      <div class="card-head"><div><h2>{{ $delivery->reference }}</h2><p>طلب التاجر #{{ $delivery->merchant_order_id }}</p></div><strong>{{ ['assigned'=>'بانتظار قبولك','accepted'=>'مقبولة','picked_up'=>'تم الاستلام من التاجر','in_transit'=>'في الطريق','delivered'=>'تم التسليم','failed'=>'فشل التوصيل','returned'=>'مرتجع','cancelled'=>'ملغي'][$delivery->status->value] ?? $delivery->status->value }}</strong></div>
      <div class="details"><section><h3>نقطة الاستلام</h3><p>{{ $delivery->origin_snapshot['contact_name'] }}</p><p>{{ $delivery->origin_snapshot['location_name'] ?? '—' }} — {{ $delivery->origin_snapshot['address'] }}</p><p>{{ $delivery->origin_snapshot['phone'] }}</p></section><section><h3>نقطة التسليم</h3><p>{{ $delivery->destination_snapshot['recipient_name'] }}</p><p>{{ $delivery->destination_snapshot['governorate'] }} — {{ $delivery->destination_snapshot['city'] }}</p><p>{{ $delivery->destination_snapshot['address'] }}</p><p>{{ $delivery->destination_snapshot['mobile'] }}</p></section></div>
      <p>ملاحظة: {{ $delivery->assignment_notes ?: '—' }}</p>
      <p>المحتويات: @foreach($delivery->merchantOrder->items as $item){{ $item->product_name }} × {{ $item->qty }}@if(!$loop->last)، @endif @endforeach</p>
      @if(!$delivery->status->isTerminal())<details><summary>تسجيل سبب تأخير</summary><form method="POST" action="{{ route('delivery.tasks.delay', $delivery) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><label>السبب<select name="reason" required><option value="merchant_not_ready">التاجر غير جاهز</option><option value="customer_unavailable">العميل غير متاح</option><option value="traffic">ازدحام</option><option value="access_issue">صعوبة وصول</option><option value="area_disruption">اضطراب بالمنطقة</option><option value="weather">طقس</option><option value="vehicle_issue">مشكلة مركبة</option><option value="courier_delay">تأخير المندوب</option><option value="incorrect_address">عنوان خاطئ</option><option value="other">أخرى</option></select></label><label>المسؤولية<select name="responsibility" required><option value="merchant">التاجر</option><option value="delivery_worker">المندوب</option><option value="customer">العميل</option><option value="platform">المنصة</option><option value="external_condition">ظرف خارجي</option></select></label><label>ملاحظة<textarea name="note" maxlength="1000"></textarea></label><button class="btn">حفظ التأخير</button></form></details>@endif
      @if($delivery->status->value === 'assigned')
        <form method="POST" action="{{ route('delivery.tasks.accept', $delivery) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><button class="btn btn-primary">قبول المهمة</button></form>
      @elseif($delivery->status->value === 'accepted')
        @if(!$delivery->heading_to_merchant_at)<form method="POST" action="{{ route('delivery.tasks.milestone', [$delivery, 'heading-to-merchant']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><button class="btn btn-primary">بدأت التوجه للتاجر</button></form>
        @elseif(!$delivery->merchant_arrived_at)<form method="POST" action="{{ route('delivery.tasks.milestone', [$delivery, 'merchant-arrived']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><button class="btn btn-primary">وصلت إلى التاجر</button></form>@endif
        <form method="POST" action="{{ route('delivery.tasks.picked-up', $delivery) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><textarea name="note" maxlength="1000" placeholder="ملاحظة اختيارية"></textarea><button class="btn btn-primary">استلمت الطرد من التاجر</button></form>
      @elseif($delivery->status->value === 'picked_up')
        <form method="POST" action="{{ route('delivery.tasks.in-transit', $delivery) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><textarea name="note" maxlength="1000" placeholder="ملاحظة اختيارية"></textarea><button class="btn btn-primary">الطرد في الطريق</button></form>
      @elseif($delivery->status->value === 'in_transit')
        @if(!$delivery->out_for_delivery_at)<form method="POST" action="{{ route('delivery.tasks.milestone', [$delivery, 'out-for-delivery']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><button class="btn btn-primary">بدأت التوجه للعميل</button></form>
        @elseif(!$delivery->customer_arrived_at)<form method="POST" action="{{ route('delivery.tasks.milestone', [$delivery, 'customer-arrived']) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><button class="btn btn-primary">وصلت إلى العميل</button></form>@endif
        <form method="POST" enctype="multipart/form-data" action="{{ route('delivery.tasks.delivered', $delivery) }}">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><label>رمز العميل<input name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required></label><label>صورة إثبات التسليم — اختيارية<input type="file" name="proof" accept="image/jpeg,image/png"></label><textarea name="note" maxlength="1000" placeholder="ملاحظة اختيارية"></textarea><button class="btn btn-primary">تأكيد التسليم</button></form>
      @elseif($delivery->status->value === 'delivered')
        <p>تم التسليم في {{ $delivery->delivered_at?->format('Y-m-d H:i') }}</p>
        @if($delivery->proof)<a href="{{ route('delivery-proofs.show', $delivery->proof) }}">عرض إثبات التسليم</a>@endif
      @else
        <p>أُغلقت المهمة بهذه النتيجة. راجع الإدارة عند الحاجة إلى تفاصيل السبب.</p>
      @endif
      <details><summary>سجل المهمة</summary>@foreach($delivery->events as $event)<p>{{ $event->created_at->format('Y-m-d H:i') }} — {{ $event->event_type }}</p>@endforeach</details>
    </article>
  @empty <div class="panel">لا توجد مهام مسندة إليك.</div> @endforelse
  {{ $deliveries->links() }}
</div>
@endsection

@push('styles')
<style>
.worker-tasks{max-width:900px;margin:0 auto}.worker-tasks .panel{background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:15px;margin:14px 0}.worker-tasks .card-head{display:flex;justify-content:space-between;gap:12px}.worker-tasks .details{display:grid;grid-template-columns:1fr 1fr;gap:14px}.worker-tasks .details section{border:1px solid #dce2ea;border-radius:10px;padding:12px}.worker-tasks form{display:grid;gap:8px;margin-top:12px}.worker-tasks textarea,.worker-tasks input{padding:9px;border:1px solid #a7b4c5;border-radius:7px}@media(max-width:700px){.worker-tasks .details{grid-template-columns:1fr}.worker-tasks .card-head{flex-direction:column}}
</style>
@endpush
