@extends('layouts.private-finance')
@section('title', 'إدارة السحوبات')
@section('privacy-notice', 'صفحة مالية خاصة. قرارات الاعتماد والتحويل تحتاج كلمة مرور حساب المراجع. لا تدخل كلمة مرور البنك أو المحفظة أو رمز OTP هنا.')

@section('content')
<div class="container rtl admin-withdrawals page-pad">
  <div class="page-head"><p>راجع الوسائل والطلبات من حساب مستقل عن حساب التاجر صاحب السحب.</p><a class="btn" href="{{ route('admin.ledger.index') }}">السجل المحاسبي</a></div>

  @if(auth()->user()->hasPermission('withdrawals.settings'))
    <form method="POST" action="{{ route('admin.withdrawals.settings.update') }}" class="panel policy-form">
      @csrf @method('PUT')
      <h2>سياسة السحب</h2>
      <label class="check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $policy['enabled']))> تفعيل استقبال طلبات السحب</label>
      <label>الحد الأدنى<input type="number" step="0.01" min="0.01" name="minimum_amount" value="{{ old('minimum_amount', $policy['minimum_amount']) }}" required></label>
      <label>الأعلى للطلب<input type="number" step="0.01" min="0.01" name="maximum_amount" value="{{ old('maximum_amount', $policy['maximum_amount']) }}" required></label>
      <label>الحد اليومي<input type="number" step="0.01" min="0.01" name="daily_limit" value="{{ old('daily_limit', $policy['daily_limit']) }}" required></label>
      <label>الحد الأسبوعي<input type="number" step="0.01" min="0.01" name="weekly_limit" value="{{ old('weekly_limit', $policy['weekly_limit']) }}" required></label>
      <label class="reauth">كلمة مرور حساب المسؤول<input type="password" name="current_password" autocomplete="current-password" required></label>
      <button class="btn btn-primary">حفظ السياسة</button>
    </form>
  @endif

  @if($canReview)
    <section class="panel"><h2>وسائل استلام بانتظار التحقق</h2><div class="review-grid">
      @forelse($payoutMethods as $method)
        <div class="review-card"><h3>{{ $method->merchant->legal_name }}</h3><p>{{ $method->provider_name }} — {{ $method->type === 'bank_account' ? 'حساب بنكي' : 'محفظة' }}</p><p>صاحب الحساب: {{ $method->account_name }}</p><p dir="ltr">{{ $method->account_identifier }}</p>
          <form method="POST" action="{{ route('admin.withdrawals.payout-methods.update', $method) }}" class="decision-form">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $method->lock_version }}"><textarea name="notes" placeholder="ملاحظات/سبب الرفض"></textarea><label>كلمة مرور حساب المراجع<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-primary" name="decision" value="verified">اعتماد</button><button class="btn" name="decision" value="rejected">رفض</button></form>
        </div>
      @empty <p>لا توجد وسائل بانتظار التحقق.</p> @endforelse
    </div></section>

    <form method="GET" class="panel filters"><select name="status"><option value="">كل الحالات</option>@foreach(['requested'=>'قيد المراجعة','approved'=>'معتمد','rejected'=>'مرفوض','paid'=>'مدفوع'] as $value=>$label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select><button class="btn">تصفية</button></form>

    <section class="withdrawal-list">
      @forelse($withdrawals as $withdrawal)
        <article class="panel withdrawal-card"><div class="card-head"><div><h2>{{ $withdrawal->reference }}</h2><p>{{ $withdrawal->merchant->legal_name }} — ₪{{ number_format($withdrawal->amount/100, 2) }}</p></div><strong>{{ ['requested'=>'قيد المراجعة','approved'=>'معتمد بانتظار التحويل','rejected'=>'مرفوض','paid'=>'مدفوع'][$withdrawal->status->value] ?? $withdrawal->status->value }}</strong></div>
          <p>الوجهة: {{ $withdrawal->payoutMethod->provider_name }} — {{ $withdrawal->payoutMethod->account_name }} — <span dir="ltr">{{ $withdrawal->payoutMethod->account_identifier }}</span></p>
          <p>طُلب: {{ $withdrawal->requested_at->format('Y-m-d H:i') }}</p>
          @if($withdrawal->status->value === 'requested')
            <form method="POST" action="{{ route('admin.withdrawals.review', $withdrawal) }}" class="decision-form">@csrf @method('PATCH')<input type="hidden" name="lock_version" value="{{ $withdrawal->lock_version }}"><textarea name="notes" placeholder="ملاحظات/سبب الرفض"></textarea><label>كلمة مرور حساب المراجع<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-primary" name="decision" value="approved">اعتماد</button><button class="btn" name="decision" value="rejected">رفض وإعادة الرصيد</button></form>
          @elseif($withdrawal->status->value === 'approved')
            <form method="POST" enctype="multipart/form-data" action="{{ route('admin.withdrawals.pay', $withdrawal) }}" class="pay-form">@csrf<input type="hidden" name="lock_version" value="{{ $withdrawal->lock_version }}"><label>مرجع التحويل<input name="transaction_reference" autocomplete="off" required></label><label>وقت التحويل (UTC)<input type="datetime-local" name="transferred_at" value="{{ now()->format('Y-m-d\TH:i') }}" required></label><label>إثبات التحويل (حتى 10 MiB)<input type="file" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required></label><label>كلمة مرور حساب المسؤول<input type="password" name="current_password" autocomplete="current-password" required></label><button class="btn btn-primary">تسجيل كمدفوع</button></form>
          @elseif($withdrawal->status->value === 'paid')
            <p>مرجع التحويل: {{ $withdrawal->transaction_reference }} — {{ $withdrawal->transferred_at?->format('Y-m-d H:i') }} @if($withdrawal->proof)<a href="{{ route('withdrawal-proofs.show', $withdrawal->proof) }}">عرض الإثبات</a>@endif</p>
          @else <p>سبب الرفض: {{ $withdrawal->review_notes ?: '—' }}</p> @endif
        </article>
      @empty <div class="panel">لا توجد طلبات سحب.</div> @endforelse
    </section>
    {{ $withdrawals->links() }}
  @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin-withdrawals.css') }}">
@endpush
