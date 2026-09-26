@extends('layouts.app')
@section('title', 'اقتراح تعديل المنتج - Mart.ps')

@section('content')
@php
  $value = fn($key, $fallback = null) => old($key, array_key_exists($key, $changes) ? $changes[$key] : $fallback);
  $specifications = $changes['specifications'] ?? $product->specifications;
@endphp
<div class="container rtl page-pad page-narrow-920">
  <h1>اقتراح تعديل: {{ $product->name }}</h1>
  <p>تبقى النسخة المنشورة الحالية كما هي حتى تعتمد الإدارة الاقتراح.</p>
  @if($changeRequest?->review_notes)<div class="notice error"><strong>ملاحظات الإدارة:</strong> {{ $changeRequest->review_notes }}</div>@endif
  @if($errors->any())<div class="notice error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('merchant.catalog.change-requests.store', $product) }}" enctype="multipart/form-data" class="panel">
    @csrf
    @if($changeRequest)<input type="hidden" name="change_request_lock_version" value="{{ $changeRequest->lock_version }}">@endif
    <div class="grid">
      <label>الاسم المقترح<input name="name" value="{{ $value('name', $product->name) }}" required></label>
      <label>التصنيف المقترح<select name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)$value('category_id', $product->category_id) === (string)$category->id)>{{ $category->path }} — {{ $category->name }}</option>@endforeach</select></label>
      <label>الماركة المقترحة<select name="brand_id"><option value="">بدون ماركة</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)$value('brand_id', $product->brand_id) === (string)$brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
      <label>الموديل<input name="model" value="{{ $value('model', $product->model) }}"></label>
      <label class="full">الوصف<textarea name="description" rows="5">{{ $value('description', $product->description) }}</textarea></label>
      <label class="full">المواصفات بصيغة JSON<textarea name="specifications" rows="5">{{ old('specifications', $specifications ? json_encode($specifications, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '') }}</textarea></label>
      <label class="full">صورة مقترحة اختيارية<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
      @if($changeRequest?->proposed_image_path)<a href="{{ route('product-change-requests.image', $changeRequest) }}">عرض الصورة المقترحة الحالية</a>@endif
    </div>
    <button class="btn btn-primary" type="submit">إرسال الاقتراح للمراجعة</button>
  </form>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/merchant-catalog.css') }}">@endpush
