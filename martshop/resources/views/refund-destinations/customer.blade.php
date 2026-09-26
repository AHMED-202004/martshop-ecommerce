@extends('layouts.private-finance')
@section('title', 'وسيلة استلام الاسترداد')
@section('content')
<p>{{ $refund->reference }} — {{ $refund->status->label() }}</p>
<p>المبلغ المطلوب: {{ number_format($refund->amount / 100, 2) }} {{ $refund->currency }}.</p>
@if($active)
  <section>
    <h2>الوسيلة الحالية</h2>
    <p>{{ $active->maskedIdentifier() }} — {{ $active->status->label() }}</p>
    <a href="{{ route('refund-destinations.show', $active) }}">عرض بيانات الوسيلة الخاصة</a>
    @if($canChange)
      <form method="POST" action="{{ route('refund-destinations.revoke', $active) }}">@csrf
        <p>لتصحيح الرقم أو تغييره: ألغِ الوسيلة الحالية، ثم سجّل بديلًا ليخضع لتحقق جديد. الإلغاء لا يحذف التاريخ.</p>
        <input type="hidden" name="lock_version" value="{{ $active->lock_version }}">
        <label for="revoke-password">كلمة مرور حسابك في Mart.ps لتأكيد الإلغاء</label>
        <input id="revoke-password" name="current_password" type="password" autocomplete="current-password" required>
        <button>إلغاء استخدام هذه الوسيلة</button>
      </form>
    @endif
  </section>
@elseif($canChange)
  <section>
    <h2>تسجيل وسيلة باسمك</h2>
    <p>اكتب اسم صاحب الحساب كما هو لدى البنك أو المحفظة. لا تسجّل حساب شخص آخر. اسم الجهة ورقم الحساب لا يُنسخان من حساب المنصة الذي استقبل دفعتك.</p>
    <form method="POST" action="{{ route('refund-destinations.store', $refund) }}">@csrf
      <input type="hidden" name="lock_version" value="{{ $refund->lock_version }}">
      <label for="type">نوع الوسيلة</label>
      <select id="type" name="type" required><option value="">اختر النوع</option><option value="bank_account">حساب بنكي</option><option value="mobile_wallet">محفظة إلكترونية</option></select>
      <label for="provider-name">اسم البنك أو مزود المحفظة</label><input id="provider-name" name="provider_name" minlength="2" maxlength="100" required autocomplete="off">
      <label for="account-name">اسم صاحب الحساب</label><input id="account-name" name="account_name" minlength="2" maxlength="150" required autocomplete="off">
      <label for="account-identifier">رقم الحساب أو IBAN أو رقم المحفظة (حروف إنجليزية وأرقام)</label><input id="account-identifier" name="account_identifier" dir="ltr" minlength="5" maxlength="120" required autocomplete="off">
      <label for="submit-password">كلمة مرور حسابك في Mart.ps للتأكيد</label><input id="submit-password" name="current_password" type="password" autocomplete="current-password" required>
      <button>إرسال الوسيلة للتحقق</button>
    </form>
  </section>
@else<p>لا يمكن تسجيل أو تغيير وسيلة لهذا الطلب في حالته الحالية.</p>@endif
<h2>تاريخ وسائل الاستلام</h2>
@forelse($destinations as $destination)
  <section><p>{{ $destination->created_at->format('Y-m-d H:i') }} — {{ $destination->maskedIdentifier() }} — {{ $destination->status->label() }}</p>
    <a href="{{ route('refund-destinations.show', $destination) }}">تفاصيل الوسيلة #{{ $destination->id }}</a>
  </section>
@empty<p>لم تُسجّل وسيلة استلام لهذا الطلب بعد.</p>@endforelse
{{ $destinations->links() }}
@endsection
