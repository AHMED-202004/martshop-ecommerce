@extends('layouts.private-finance')
@section('title', 'طلبات السحب')
@section('privacy-notice', 'هذه صفحة مالية خاصة. المنصة تطلب كلمة مرور حساب Mart.ps فقط لتأكيد العمليات الحساسة، ولا تطلب كلمة مرور البنك أو المحفظة أو رمز OTP.')

@section('content')
<div class="container rtl withdrawals-page page-pad">
  <div class="page-head"><p>يمكن السحب من الرصيد المتاح فقط. المبالغ المعلقة أو المحجوزة لا تدخل في الرصيد القابل للسحب.</p><a class="btn" href="{{ route('merchant.ledger.index') }}">السجل المحاسبي</a></div>

  <div class="balances">
    @foreach(['available'=>'متاح','pending'=>'معلّق','held'=>'محجوز','withdrawn'=>'مسحوب'] as $key=>$label)
      <div class="panel"><span>{{ $label }}</span><strong>₪{{ number_format(($balances[$key]['ILS'] ?? 0) / 100, 2) }}</strong></div>
    @endforeach
  </div>

  @if(!$policy['enabled'])
    <div class="notice warning">طلبات السحب متوقفة حاليًا بقرار الإدارة. يمكنك تسجيل وسيلة الاستلام والتحقق منها الآن.</div>
  @endif

  <div class="forms-grid">
    <form method="POST" action="{{ route('merchant.withdrawals.store') }}" class="panel form-grid">
      @csrf
      <h2>طلب سحب جديد</h2>
      <p>الحد الأدنى ₪{{ number_format($policy['minimum_minor']/100, 2) }}، الأعلى للطلب ₪{{ number_format($policy['maximum_minor']/100, 2) }}.</p>
      <label>وسيلة الاستلام الموثقة
        <select name="merchant_payout_method_id" required @disabled(!$policy['enabled'] || $verifiedMethods->isEmpty())>
          <option value="">اختر</option>
          @foreach($verifiedMethods as $method)
            <option value="{{ $method->id }}" @selected(old('merchant_payout_method_id') == $method->id)>{{ $method->provider_name }} — {{ $method->maskedIdentifier() }}</option>
          @endforeach
        </select>
      </label>
      <label>المبلغ بالشيكل<input type="number" name="amount" min="0.01" step="0.01" value="{{ old('amount') }}" required @disabled(!$policy['enabled'])></label>
      <label>كلمة المرور الحالية<input type="password" name="current_password" autocomplete="current-password" required @disabled(!$policy['enabled'])></label>
      <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
      <button class="btn btn-primary" @disabled(!$policy['enabled'] || $verifiedMethods->isEmpty())>إرسال الطلب وحجز المبلغ</button>
      @if($verifiedMethods->isEmpty()) <small>تحتاج وسيلة استلام موثقة أولًا.</small> @endif
    </form>

    <form method="POST" action="{{ route('merchant.payout-methods.store') }}" class="panel form-grid">
      @csrf
      <h2>تسجيل وسيلة استلام</h2>
      <p>لا يمكن تعديل البيانات الحساسة بعد إرسالها؛ سجّل وسيلة جديدة لتغيير الحساب، وستحتاج تحققًا إداريًا جديدًا.</p>
      <label>النوع<select name="type" required><option value="mobile_wallet">محفظة إلكترونية</option><option value="bank_account" @selected(old('type') === 'bank_account')>حساب بنكي</option></select></label>
      <label>البنك أو مزود المحفظة<input name="provider_name" value="{{ old('provider_name') }}" required></label>
      <label>اسم صاحب الحساب<input name="account_name" value="{{ old('account_name') }}" required></label>
      <label>رقم الحساب/IBAN/رقم المحفظة<input name="account_identifier" dir="ltr" autocomplete="off" required></label>
      <label>كلمة المرور الحالية<input type="password" name="current_password" autocomplete="current-password" required></label>
      <button class="btn btn-primary">إرسال للتحقق</button>
    </form>
  </div>

  <section class="panel"><h2>وسائل الاستلام</h2><div class="method-list">
    @forelse($methods as $method)
      <div class="method"><strong>{{ $method->provider_name }} — {{ $method->maskedIdentifier() }}</strong><span>{{ ['pending'=>'قيد التحقق','verified'=>'موثقة','rejected'=>'مرفوضة','disabled'=>'معطلة'][$method->status->value] ?? $method->status->value }}</span>
      @if($method->status->value !== 'disabled')
        <form method="POST" action="{{ route('merchant.payout-methods.disable', $method) }}">@csrf<input type="password" name="current_password" placeholder="كلمة المرور" autocomplete="current-password" required><button class="btn">تعطيل</button></form>
      @endif</div>
    @empty <p>لا توجد وسائل استلام مسجلة.</p> @endforelse
  </div></section>

  <section class="panel table-wrap"><h2>الطلبات السابقة</h2><table><thead><tr><th>المرجع</th><th>المبلغ</th><th>الحالة</th><th>الوجهة</th><th>مرجع التحويل</th><th>الإثبات</th><th>التاريخ</th></tr></thead><tbody>
    @forelse($withdrawals as $withdrawal)
      <tr><td>{{ $withdrawal->reference }}</td><td>₪{{ number_format($withdrawal->amount/100, 2) }}</td><td>{{ ['requested'=>'قيد المراجعة','approved'=>'معتمد بانتظار التحويل','rejected'=>'مرفوض','paid'=>'مدفوع'][$withdrawal->status->value] ?? $withdrawal->status->value }}</td><td>{{ $withdrawal->destination_snapshot['provider_name'] }} — {{ $withdrawal->destination_snapshot['masked_identifier'] }}</td><td>{{ $withdrawal->transaction_reference ?: '—' }}</td><td>@if($withdrawal->proof)<a href="{{ route('withdrawal-proofs.show', $withdrawal->proof) }}">عرض</a>@else — @endif</td><td>{{ $withdrawal->requested_at->format('Y-m-d H:i') }}</td></tr>
    @empty <tr><td colspan="7">لا توجد طلبات سحب.</td></tr> @endforelse
  </tbody></table></section>
  {{ $withdrawals->links() }}
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-withdrawals.css') }}">
@endpush
