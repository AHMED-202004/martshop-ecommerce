@extends('layouts.app')
@section('title', 'منتجاتي وعروضي - Mart.ps')

@section('content')
@php
  $statusLabels = [
    'pending_review' => 'قيد المراجعة',
    'changes_requested' => 'مطلوب تعديل',
    'active' => 'نشط',
    'rejected' => 'مرفوض',
    'paused' => 'متوقف',
  ];
@endphp
<div class="container rtl page-pad">
  <div class="page-head">
    <div>
      <h1>منتجاتي وعروضي</h1>
      <p>حالة التحقق: <strong>{{ $merchant->verification_status->value }}</strong></p>
    </div>
    <a class="btn" href="{{ route('merchant.orders.index') }}">طلبات التاجر</a>
    @if($merchant->verification_status === \App\Enums\MerchantVerificationStatus::Verified)
      <a class="btn btn-primary" href="{{ route('merchant.catalog.create') }}">إضافة منتج أو عرض</a>
    @else
      <a class="btn" href="{{ route('merchant.profile.edit') }}">استكمال التحقق</a>
    @endif
  </div>

  @if(session('success')) <div class="notice success">{{ session('success') }}</div> @endif
  @if($errors->any()) <div class="notice error">{{ $errors->first() }}</div> @endif

  <div class="panel table-wrap">
    <table>
      <thead><tr><th>المنتج</th><th>السعر</th><th>المخزون</th><th>المنطقة</th><th>حالة المنتج</th><th>حالة العرض</th><th>الملاحظات</th><th>الإجراءات</th></tr></thead>
      <tbody>
      @forelse($offers as $offer)
        <tr>
          <td>{{ $offer->product->name }}</td>
          <td>₪{{ number_format((float)$offer->price, 2) }}</td>
          <td>{{ $offer->stock ?? 'غير محدود' }}</td>
          <td>{{ $offer->location?->name ?? '—' }}</td>
          <td>{{ $statusLabels[$offer->product->status->value] ?? $offer->product->status->value }}</td>
          <td>{{ $statusLabels[$offer->status->value] ?? $offer->status->value }}</td>
          <td>{{ $offer->review_notes ?: '—' }}</td>
          <td>
            @if($merchant->verification_status === \App\Enums\MerchantVerificationStatus::Verified)
              @if($offer->product->status === \App\Enums\ProductStatus::ChangesRequested && $offer->product->created_by_merchant_id === $merchant->id)
                <a class="btn btn-primary" href="{{ route('merchant.catalog.products.edit', $offer->product) }}">تصحيح المنتج</a>
              @elseif($offer->product->status === \App\Enums\ProductStatus::Active)
                @php $changeRequest = $offer->product->changeRequests->first(); @endphp
                @if(!$changeRequest)
                  <a class="btn" href="{{ route('merchant.catalog.change-requests.create', $offer->product) }}">اقتراح تعديل المنتج</a>
                @elseif($changeRequest->status === \App\Enums\ProductChangeRequestStatus::ChangesRequested)
                  <a class="btn btn-primary" href="{{ route('merchant.catalog.change-requests.create', $offer->product) }}">تصحيح اقتراح المنتج</a>
                @else
                  <span>اقتراح المنتج قيد المراجعة</span>
                @endif
              @endif
              @if(in_array($offer->status, [\App\Enums\ProductOfferStatus::Active, \App\Enums\ProductOfferStatus::PendingReview, \App\Enums\ProductOfferStatus::ChangesRequested, \App\Enums\ProductOfferStatus::Paused], true))
                <a class="btn" href="{{ route('merchant.catalog.edit', $offer) }}">تعديل</a>
              @endif
              @if($offer->status === \App\Enums\ProductOfferStatus::Active)
                <form method="POST" action="{{ route('merchant.catalog.pause', $offer) }}" class="inline-form">
                  @csrf
                  <input type="hidden" name="lock_version" value="{{ $offer->lock_version }}">
                  <button class="btn" type="submit">إيقاف</button>
                </form>
              @elseif($offer->status === \App\Enums\ProductOfferStatus::Paused && $offer->paused_by === auth()->id() && $offer->pause_reason === 'merchant_requested')
                <form method="POST" action="{{ route('merchant.catalog.resume', $offer) }}" class="inline-form">
                  @csrf
                  <input type="hidden" name="lock_version" value="{{ $offer->lock_version }}">
                  <button class="btn btn-primary" type="submit">استئناف</button>
                </form>
              @endif
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="8">لا توجد عروض حتى الآن.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $offers->links() }}
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-catalog.css') }}">
@endpush
