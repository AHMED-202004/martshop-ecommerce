@extends('layouts.private-finance')
@section('title', 'مراجعة طلب تاجر')
@section('privacy-notice', 'ملف تحقق خاص يحتوي بيانات هوية وعنوان ومستندات. يلزم تأكيد كلمة مرور حساب المسؤول عند اتخاذ قرار. لا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP.')
@section('content')
@php $canViewIdentity = auth()->user()->hasPermission('merchant-documents.view'); @endphp
<div class="merchant-review rtl">
  <a href="{{ route('admin.merchants.index') }}">← جميع الطلبات</a>
  <h2>{{ $merchant->legal_name }}</h2>

  <section class="admin-panel">
    <p><strong>الحالة:</strong> {{ $merchant->verification_status->value }}</p>
    <p><strong>الحساب:</strong> {{ $merchant->user->name }} — {{ $merchant->user->email }}</p>
    <p><strong>الهاتف:</strong> {{ $merchant->phone }}</p>
    <p><strong>المنطقة:</strong> {{ $merchant->location?->name ?? '—' }}</p>
    <p><strong>العنوان:</strong> {{ $merchant->address }}</p>
    <p><strong>النشاط:</strong> {{ $merchant->business_type }}</p>
    <p><strong>رقم الهوية:</strong> {{ $canViewIdentity ? $merchant->identity_number : '••••••••' }}</p>
    @if($merchant->review_notes)<p><strong>ملاحظة سابقة:</strong> {{ $merchant->review_notes }}</p>@endif
  </section>

  <section class="admin-panel">
    <h2>المستندات</h2>
    @foreach($merchant->documents->sortByDesc('id') as $document)
      <div class="doc-review">
        <div><strong>{{ $document->type->value }}</strong> — {{ $document->status->value }}</div>
        @can('view', $document)<a href="{{ route('merchant.documents.show', $document) }}">تنزيل خاص</a>@endcan
        @can('review', $document)
          <form method="POST" action="{{ route('admin.merchants.documents.review', $document) }}">
            @csrf @method('PATCH')
            <select name="status" required><option value="accepted">قبول</option><option value="rejected">رفض</option></select>
            <input name="review_notes" placeholder="سبب الرفض عند الحاجة">
            <input type="password" name="current_password" autocomplete="current-password" placeholder="كلمة مرور حساب المسؤول" required>
            <button class="btn" type="submit">حفظ المراجعة</button>
          </form>
        @endcan
      </div>
    @endforeach
  </section>

  @can('verify', $merchant)
    <section class="admin-panel actions">
      <form method="POST" action="{{ route('admin.merchants.approve', $merchant) }}">@csrf<input type="password" name="current_password" autocomplete="current-password" placeholder="كلمة مرور حساب المسؤول" required><button class="btn btn-primary">اعتماد التاجر</button></form>
      @foreach(['request-changes' => 'طلب تعديلات', 'reject' => 'رفض', 'suspend' => 'تعليق'] as $action => $label)
        <form method="POST" action="{{ route('admin.merchants.'.$action, $merchant) }}">
          @csrf <input name="reason" placeholder="السبب" required><input type="password" name="current_password" autocomplete="current-password" placeholder="كلمة مرور حساب المسؤول" required><button class="btn" type="submit">{{ $label }}</button>
        </form>
      @endforeach
    </section>
  @endcan
</div>
@endsection
@push('styles')
<style>main{max-width:1100px}.merchant-review{max-width:1050px;margin:0 auto}.admin-panel{background:#fff;border:1px solid #dce2ea;border-radius:12px;padding:18px;margin:16px 0}.doc-review{padding:12px 0;border-bottom:1px solid #e7ebf0}.doc-review form,.actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px}.doc-review form input,.doc-review form select,.actions input{min-width:210px}.actions form{display:flex;gap:8px;align-items:center;flex-wrap:wrap}@media(max-width:650px){.doc-review form,.actions,.actions form{align-items:stretch;flex-direction:column}.doc-review form input,.doc-review form select,.actions input{min-width:0;width:100%}}</style>
@endpush
