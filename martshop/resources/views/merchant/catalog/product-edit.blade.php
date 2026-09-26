@extends('layouts.app')
@section('title', 'تصحيح المنتج - Mart.ps')

@section('content')
<div class="container rtl page-pad page-narrow-900">
  <h1>تصحيح المنتج وإعادة إرساله</h1>
  <div class="notice error"><strong>ملاحظات الإدارة:</strong> {{ $product->review_notes }}</div>
  @if($product->submission_image_path)
    <p><a href="{{ route('product-submissions.image', $product) }}">عرض الصورة المرسلة الحالية</a></p>
  @endif

  @if($errors->any())<div class="notice error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <form method="POST" action="{{ route('merchant.catalog.products.update', $product) }}" enctype="multipart/form-data" class="panel">
    @csrf @method('PATCH')
    <div class="grid">
      <label>اسم المنتج<input name="name" value="{{ old('name', $product->name) }}" required></label>
      <label>التصنيف<select name="category_id" required>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id', $product->category_id) === (string)$category->id)>{{ $category->path }} — {{ $category->name }}</option>@endforeach</select></label>
      <label>الماركة<select name="brand_id"><option value="">بدون ماركة</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)old('brand_id', $product->brand_id) === (string)$brand->id)>{{ $brand->name }}</option>@endforeach</select></label>
      <label>الموديل<input name="model" value="{{ old('model', $product->model) }}"></label>
      <label class="full">الوصف<textarea name="description" rows="5">{{ old('description', $product->description) }}</textarea></label>
      <label class="full">صورة بديلة اختيارية<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    </div>
    <button class="btn btn-primary" type="submit">إعادة الإرسال للمراجعة</button>
  </form>
</div>
@endsection
@push('styles')<link rel="stylesheet" href="{{ asset('assets/merchant-catalog.css') }}">@endpush
