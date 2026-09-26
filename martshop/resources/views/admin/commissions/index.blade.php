@extends('layouts.private-finance')
@section('title', 'قواعد العمولة')
@section('privacy-notice', 'إعدادات مالية خاصة. يلزم تأكيد كلمة مرور حساب المسؤول عند كل إضافة أو تعديل. لا تطلب المنصة كلمة مرور البنك أو المحفظة أو رمز OTP، والتغييرات لا تعدّل عمولات الطلبات السابقة.')

@section('content')
<div class="container rtl commission-admin page-pad">
  <div class="page-head"><p>الأولوية: تاجر + تصنيف، ثم تاجر أو تصنيف، ثم القاعدة العامة. عند التعادل تُستخدم الأولوية الأعلى.</p><a class="btn" href="{{ route('admin.ledger.index') }}">السجل المحاسبي</a></div>

  <form method="POST" action="{{ route('admin.commissions.store') }}" class="panel rule-form">
    @csrf
    <h2>إضافة قاعدة</h2>
    <label>الاسم<input name="name" value="{{ old('name') }}" required></label>
    <label>التاجر<select name="merchant_id"><option value="">كل التجار</option>@foreach($merchants as $merchant)<option value="{{ $merchant->id }}" @selected(old('merchant_id') == $merchant->id)>{{ $merchant->legal_name }}</option>@endforeach</select></label>
    <label>التصنيف<select name="category_id"><option value="">كل التصنيفات</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->path }}</option>@endforeach</select></label>
    <label>النسبة %<input type="number" step="0.01" min="0" max="100" name="percentage" value="{{ old('percentage', 0) }}" required></label>
    <label>الأولوية<input type="number" name="priority" value="{{ old('priority', 0) }}"></label>
    <label>بداية الصلاحية (UTC)<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"></label>
    <label>نهاية الصلاحية (UTC)<input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"></label>
    <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> مفعّلة</label>
    <label class="reauth">كلمة مرور حساب المسؤول<input type="password" name="current_password" autocomplete="current-password" required></label>
    <button class="btn btn-primary">إضافة القاعدة</button>
  </form>

  <div class="rule-list">
    @foreach($rules as $rule)
      <form method="POST" action="{{ route('admin.commissions.update', $rule) }}" class="panel rule-form">
        @csrf @method('PUT')
        <h2>#{{ $rule->id }} — {{ $rule->name }}</h2>
        <label>الاسم<input name="name" value="{{ $rule->name }}" required></label>
        <label>التاجر<select name="merchant_id"><option value="">كل التجار</option>@foreach($merchants as $merchant)<option value="{{ $merchant->id }}" @selected($rule->merchant_id === $merchant->id)>{{ $merchant->legal_name }}</option>@endforeach</select></label>
        <label>التصنيف<select name="category_id"><option value="">كل التصنيفات</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($rule->category_id === $category->id)>{{ $category->path }}</option>@endforeach</select></label>
        <label>النسبة %<input type="number" step="0.01" min="0" max="100" name="percentage" value="{{ $rule->percentage }}" required></label>
        <label>الأولوية<input type="number" name="priority" value="{{ $rule->priority }}"></label>
        <label>بداية الصلاحية (UTC)<input type="datetime-local" name="starts_at" value="{{ $rule->starts_at?->format('Y-m-d\TH:i') }}"></label>
        <label>نهاية الصلاحية (UTC)<input type="datetime-local" name="ends_at" value="{{ $rule->ends_at?->format('Y-m-d\TH:i') }}"></label>
        <label class="check"><input type="checkbox" name="is_active" value="1" @checked($rule->is_active)> مفعّلة</label>
        <label class="reauth">كلمة مرور حساب المسؤول<input type="password" name="current_password" autocomplete="current-password" required></label>
        <button class="btn btn-primary">حفظ دون تعديل الطلبات السابقة</button>
      </form>
    @endforeach
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin-commissions.css') }}">
@endpush
