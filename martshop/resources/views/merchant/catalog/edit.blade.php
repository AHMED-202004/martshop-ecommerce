@extends('layouts.app')
@section('title', 'تعديل العرض - Mart.ps')

@section('content')
<div class="container rtl page-pad page-narrow-900">
  <h1>تعديل عرض: {{ $offer->product->name }}</h1>
  <div class="panel summary">
    <div><strong>حالة العرض:</strong> {{ $offer->status->value }}</div>
    <div><strong>حالة المنتج:</strong> {{ $offer->product->status->value }}</div>
    @if($offer->review_notes)<div><strong>ملاحظات المراجعة:</strong> {{ $offer->review_notes }}</div>@endif
    @if($offer->pause_reason && $offer->pause_reason !== 'merchant_requested')
      <div class="notice error">هذا العرض أوقفته الإدارة أو النظام، ويمكن تعديل بياناته لكن لا يمكن استئنافه من حساب التاجر.</div>
    @endif
  </div>

  @if($errors->any())
    <div class="notice error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <form method="POST" action="{{ route('merchant.catalog.update', $offer) }}" class="panel">
    @csrf @method('PATCH')
    <input type="hidden" name="lock_version" value="{{ old('lock_version', $offer->lock_version) }}">

    <div class="grid">
      <label>السعر الحالي<input type="number" name="price" step="0.01" min="0.01" value="{{ old('price', $offer->price) }}" required></label>
      <label>السعر قبل الخصم<input type="number" name="compare_at_price" step="0.01" min="0.01" value="{{ old('compare_at_price', $offer->compare_at_price) }}"></label>
      <label>الكمية المتوفرة<input type="number" name="stock" min="0" value="{{ old('stock', $offer->stock) }}" required></label>
      <label>منطقة المخزون
        <select name="location_id" required>
          @foreach($locations as $location)
            <option value="{{ $location->id }}" @selected((string)old('location_id', $offer->location_id) === (string)$location->id)>{{ $location->name }}</option>
          @endforeach
        </select>
      </label>
      <label>مدة التجهيز بالأيام<input type="number" name="preparation_time_days" min="0" max="365" value="{{ old('preparation_time_days', $offer->preparation_time_days) }}" required></label>
      <label>اللون<input name="variant_color" value="{{ old('variant_color', $variantColor) }}"></label>
      <label class="full">المقاسات، مفصولة بفاصلة<input name="variant_sizes" value="{{ old('variant_sizes', $variantSizes) }}"></label>
      <label class="full">الضمان<textarea name="warranty" rows="3">{{ old('warranty', $offer->warranty) }}</textarea></label>
    </div>

    @if($offer->status === \App\Enums\ProductOfferStatus::ChangesRequested)
      <p>بعد الحفظ سيعود العرض إلى قائمة المراجعة بحالة <strong>pending_review</strong>.</p>
    @elseif($offer->status === \App\Enums\ProductOfferStatus::Active)
      <p>تغييرات السعر والمخزون التشغيلية تُطبّق مباشرة وتُسجّل في سجل التدقيق.</p>
    @endif
    <button class="btn btn-primary" type="submit">حفظ التعديلات</button>
  </form>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-catalog.css') }}">
@endpush
