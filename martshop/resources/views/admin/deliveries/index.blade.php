@extends('layouts.private-finance')
@section('title', 'إسناد مهام التوصيل')
@section('privacy-notice', 'صفحة تشغيلية خاصة تحتوي أسماء وهواتف وعناوين استلام وتسليم. لا تشارك البيانات خارج مهمة التوصيل، ولا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP.')

@section('content')
<div class="delivery-admin rtl">
  <div class="page-head"><p>تظهر هنا الطلبات المؤكدة والمدفوعة فقط.</p></div>

  <div class="top-grid">
    <form method="POST" action="{{ route('admin.deliveries.workers.promote') }}" class="panel form-grid">
      @csrf
      <h2>تفعيل حساب عامل توصيل</h2>
      <p>يجب أن يسجّل العامل حسابًا عاديًا أولًا، ثم أدخل بريده هنا. لا يمكن تحويل حساب تاجر أو مدير إلى عامل.</p>
      <label>البريد الإلكتروني<input type="email" name="email" value="{{ old('email') }}" required></label>
      <button class="btn btn-primary">تفعيل كعامل توصيل</button>
    </form>
    <section class="panel"><h2>العاملون المعتمدون</h2>
      @forelse($workers as $worker)
        @php($availability = $worker->deliveryWorkerProfile?->availability ?? \App\Enums\DeliveryWorkerAvailability::Available)
        <article class="worker-summary"><strong>{{ $worker->name }}</strong> — <span dir="ltr">{{ $worker->email }}</span><br>
          الحالة: {{ $availability->label() }} | نشطة: {{ $worker->current_active_count }} | بالانتظار: {{ $worker->queued_count }} | أُنجزت اليوم: {{ $worker->completed_today_count }}<br>
          آخر نشاط: {{ $worker->deliveryWorkerProfile?->last_activity_at?->format('Y-m-d H:i') ?? 'غير مسجل' }} | الموقع الحالي: لا يتم جمعه
          <form method="POST" action="{{ route('admin.deliveries.workers.availability', $worker) }}" class="compact-form">@csrf @method('PATCH')
            <select name="availability">@foreach(\App\Enums\DeliveryWorkerAvailability::cases() as $state)<option value="{{ $state->value }}" @selected($availability === $state)>{{ $state->label() }}</option>@endforeach</select>
            <input name="reason" maxlength="500" placeholder="سبب التغيير"><button class="btn">تحديث</button>
          </form>
        </article>
      @empty<p>لا يوجد عاملون بعد.</p>@endforelse
    </section>
  </div>

  <section class="panel"><h2>قواعد SLA للتوصيل</h2><p>اترك البعد فارغًا ليكون عامًا. القاعدة الأعلى أولوية والأكثر تحديدًا تُطبّق عند الإسناد.</p>
    <form method="POST" action="{{ route('admin.deliveries.sla-rules.store') }}" class="form-grid">@csrf
      <label>الاسم<input name="name" maxlength="120" required></label><label>موقع الانطلاق<select name="origin_location_id"><option value="">أي موقع</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
      <label>منطقة الوجهة<input name="destination_area" maxlength="120" placeholder="اسم المدينة أو المحافظة"></label><label>نوع الطلب<select name="order_type"><option value="">أي نوع</option><option value="standard">عادي</option><option value="express">سريع</option><option value="scheduled">مجدول</option></select></label>
      <label>طريقة التوصيل<select name="delivery_method"><option value="">أي طريقة</option><option value="courier">مندوب</option><option value="pickup_point">نقطة استلام</option><option value="third_party">طرف ثالث</option></select></label><label>أقل مسافة<input type="number" step="0.01" min="0" name="minimum_distance_km"></label><label>أقصى مسافة<input type="number" step="0.01" min="0" name="maximum_distance_km"></label>
      <label>المدة بالدقائق<input type="number" min="5" max="10080" name="target_minutes" required></label><label>الأولوية<input type="number" min="-1000" max="1000" name="priority" value="0" required></label><label><input type="checkbox" name="is_active" value="1" checked> فعالة</label>
      <label>سبب الإنشاء<input name="reason" minlength="5" maxlength="500" required></label><label>كلمة مرورك<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-primary">إنشاء القاعدة</button>
    </form>
    @forelse($slaRules as $rule)<article><strong>{{ $rule->name }}</strong> — {{ $rule->target_minutes }} دقيقة — أولوية {{ $rule->priority }} — {{ $rule->is_active ? 'فعالة' : 'معطلة' }}<p>{{ $rule->originLocation?->name ?? 'أي موقع' }} ← {{ $rule->destination_area ?: 'أي منطقة' }} | {{ $rule->order_type ?: 'أي نوع' }} | {{ $rule->delivery_method ?: 'أي طريقة' }}</p></article>@empty<p>لا توجد قواعد؛ يستخدم المسؤول الموعد اليدوي عند الإسناد.</p>@endforelse
  </section>

  <section class="delivery-list">
    @forelse($merchantOrders as $merchantOrder)
      @php($delivery = $merchantOrder->delivery)
      @php($destination = $merchantOrder->order->delivery_address_snapshot ?? [])
      <article class="panel task-card">
        <div class="card-head"><div><h2>طلب التاجر #{{ $merchantOrder->id }}</h2><p>الطلب #{{ $merchantOrder->order_id }} — {{ $merchantOrder->merchant->legal_name }}</p></div><strong>{{ $delivery ? (['unassigned'=>'غير مُسند','assigned'=>'مُسند','accepted'=>'قبله العامل','picked_up'=>'استلمه العامل','in_transit'=>'في الطريق','delivered'=>'تم التسليم','failed'=>'فشل','returned'=>'مرتجع','cancelled'=>'ملغي'][$delivery->status->value] ?? $delivery->status->value) : 'غير مُسند' }}</strong></div>
        <div class="details"><div><h3>الاستلام</h3><p>{{ $merchantOrder->merchant->address }}</p><p>{{ $merchantOrder->merchant->phone }}</p></div><div><h3>التسليم</h3><p>{{ $destination['recipient_name'] ?? 'العنوان غير مكتمل' }}</p><p>{{ $destination['governorate'] ?? '—' }} — {{ $destination['city'] ?? '—' }}</p><p>{{ $destination['address'] ?? '—' }} — {{ $destination['mobile'] ?? '—' }}</p></div></div>
        @if(!$delivery || in_array($delivery->status->value, ['assigned', 'unassigned'], true))
          <form method="POST" action="{{ route('admin.deliveries.assign', $merchantOrder) }}" class="assign-form">@csrf
            <input type="hidden" name="lock_version" value="{{ $delivery?->lock_version ?? 0 }}">
            <label>عامل التوصيل<select name="delivery_worker_id" required><option value="">اختر</option>@foreach($workers as $worker) @php($outsideScope = $worker->staffLocations->isNotEmpty() && !$worker->staffLocations->contains('id', $merchantOrder->origin_location_id))<option value="{{ $worker->id }}" @selected($delivery?->delivery_worker_id === $worker->id) @disabled($outsideScope)>{{ $worker->name }} — {{ $worker->email }}{{ $outsideScope ? ' — خارج نطاق الموقع' : '' }}</option>@endforeach</select></label>
            <label>ملاحظة الإسناد<input name="assignment_notes" value="{{ $delivery?->assignment_notes }}" maxlength="1000"></label>
            <label>موعد التسليم المتوقع (UTC)<input type="datetime-local" name="expected_delivery_at" value="{{ $delivery?->expected_delivery_at?->format('Y-m-d\TH:i') }}" required></label>
            <label>نوع الطلب<select name="order_type"><option value="standard">عادي</option><option value="express">سريع</option><option value="scheduled">مجدول</option></select></label>
            <label>طريقة التوصيل<select name="delivery_method"><option value="courier">مندوب</option><option value="pickup_point">نقطة استلام</option><option value="third_party">طرف ثالث</option></select></label>
            <label>المسافة بالكيلومتر — اختياري<input type="number" step="0.01" min="0" max="10000" name="distance_km"></label>
            <button class="btn btn-primary" @disabled($workers->isEmpty())>{{ $delivery?->delivery_worker_id ? 'إعادة الإسناد' : 'إسناد المهمة' }}</button>
          </form>
          @if($delivery?->status->value === 'assigned')<form method="POST" action="{{ route('admin.deliveries.unassign', $delivery) }}" class="compact-form">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><input name="reason" minlength="5" maxlength="1000" required placeholder="سبب إلغاء الإسناد"><button class="btn">إلغاء الإسناد</button></form>@endif
        @else
          <p>العامل: {{ $delivery->worker->name }} — تم القبول {{ $delivery->accepted_at?->format('Y-m-d H:i') }}</p>
          @if($delivery->proof)<a href="{{ route('delivery-proofs.show', $delivery->proof) }}">عرض إثبات التسليم الخاص</a>@endif
        @endif
        @if($delivery && !$delivery->status->isTerminal())
          <details><summary>تسجيل/تصحيح سبب التأخير</summary><form method="POST" action="{{ route('admin.deliveries.delay', $delivery) }}" class="assign-form">@csrf<input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}"><label>السبب<select name="reason" required><option value="merchant_not_ready">التاجر غير جاهز</option><option value="customer_unavailable">العميل غير متاح</option><option value="traffic">ازدحام</option><option value="access_issue">صعوبة وصول</option><option value="area_disruption">اضطراب بالمنطقة</option><option value="weather">طقس</option><option value="vehicle_issue">مشكلة مركبة</option><option value="courier_delay">تأخير المندوب</option><option value="incorrect_address">عنوان خاطئ</option><option value="other">أخرى</option></select></label><label>المسؤولية<select name="responsibility" required><option value="merchant">التاجر</option><option value="delivery_worker">المندوب</option><option value="customer">العميل</option><option value="platform">المنصة</option><option value="external_condition">ظرف خارجي</option></select></label><label>ملاحظة<input name="note" maxlength="1000"></label><button class="btn">حفظ</button></form></details>
          <form method="POST" action="{{ route('admin.deliveries.outcome', $delivery) }}" class="assign-form">@csrf @method('PATCH')
            <input type="hidden" name="lock_version" value="{{ $delivery->lock_version }}">
            <label>النتيجة النهائية<select name="status" required><option value="">اختر</option><option value="cancelled">إلغاء</option><option value="failed">فشل التوصيل</option><option value="returned">إرجاع الشحنة</option></select></label>
            <label>سبب النتيجة<input name="reason" minlength="5" maxlength="1000" required></label><button class="btn">تسجيل النتيجة</button>
          </form>
        @endif
      </article>
    @empty <div class="panel">لا توجد طلبات مدفوعة جاهزة للإسناد.</div> @endforelse
  </section>
  {{ $merchantOrders->links() }}
</div>
@endsection

@push('styles')
<style>
main{max-width:1150px}.delivery-admin{max-width:1110px;margin:0 auto}.delivery-admin .page-head,.card-head{display:flex;justify-content:space-between;gap:12px}.delivery-admin .panel{background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:15px;margin:14px 0}.top-grid,.details{display:grid;grid-template-columns:1fr 1fr;gap:14px}.form-grid,.assign-form{display:grid;gap:9px}.compact-form{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}.worker-summary{border-top:1px solid #e3e8ef;padding:9px 0}.form-grid label,.assign-form label{display:grid;gap:4px;font-weight:700}.form-grid input,.assign-form input,.assign-form select{border:1px solid #a7b4c5;border-radius:8px;padding:9px}.assign-form{grid-template-columns:1fr 1fr auto;align-items:end}@media(max-width:800px){.top-grid,.details,.assign-form{grid-template-columns:1fr}}
</style>
@endpush
