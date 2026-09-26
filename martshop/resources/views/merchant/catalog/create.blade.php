@extends('layouts.app')
@section('title', 'إضافة منتج أو عرض - Mart.ps')

@section('content')
<div class="container rtl page-pad page-narrow-980">
  <h1>إضافة منتج أو عرض</h1>
  <p>لن يظهر المحتوى للعملاء قبل مراجعة الإدارة واعتماده.</p>

  @if($errors->any())
    <div class="notice error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  <form method="POST" action="{{ route('merchant.catalog.store') }}" enctype="multipart/form-data" class="panel" id="catalogForm">
    @csrf
    <fieldset>
      <legend>نوع الإضافة</legend>
      <label><input type="radio" name="product_mode" value="existing" @checked(old('product_mode', 'existing') === 'existing')> عرض لمنتج موجود</label>
      <label><input type="radio" name="product_mode" value="new" @checked(old('product_mode') === 'new')> منتج جديد مع عرض</label>
    </fieldset>

    <section id="existingFields">
      <label>المنتج الموجود
        <select name="product_id">
          <option value="">اختر المنتج</option>
          @foreach($products as $product)
            <option value="{{ $product->id }}" @selected((string)old('product_id') === (string)$product->id)>{{ $product->name }}</option>
          @endforeach
        </select>
      </label>
    </section>

    <section id="newFields" class="grid">
      <label>اسم المنتج<input name="name" value="{{ old('name') }}"></label>
      <label>التصنيف
        <select name="category_id"><option value="">اختر التصنيف</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id') === (string)$category->id)>{{ $category->path }} — {{ $category->name }}</option>@endforeach</select>
      </label>
      <label>الماركة
        <select name="brand_id"><option value="">بدون ماركة</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected((string)old('brand_id') === (string)$brand->id)>{{ $brand->name }}</option>@endforeach</select>
      </label>
      <label>الموديل<input name="model" value="{{ old('model') }}"></label>
      <label class="full">الوصف<textarea name="description" rows="4">{{ old('description') }}</textarea></label>
      <label class="full">صورة المنتج<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    </section>

    <h2>بيانات العرض</h2>
    <div class="grid">
      <label>السعر الحالي<input type="number" name="price" step="0.01" min="0.01" value="{{ old('price') }}" required></label>
      <label>السعر قبل الخصم<input type="number" name="compare_at_price" step="0.01" min="0.01" value="{{ old('compare_at_price') }}"></label>
      <label>الكمية المتوفرة<input type="number" name="stock" min="0" value="{{ old('stock', 1) }}" required></label>
      <label>منطقة المخزون
        <select name="location_id" required><option value="">اختر المنطقة</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((string)old('location_id') === (string)$location->id)>{{ $location->name }}</option>@endforeach</select>
      </label>
      <label>مدة التجهيز بالأيام<input type="number" name="preparation_time_days" min="0" max="365" value="{{ old('preparation_time_days', 1) }}" required></label>
      <label>اللون<input name="variant_color" value="{{ old('variant_color') }}"></label>
      <label class="full">المقاسات، مفصولة بفاصلة<input name="variant_sizes" value="{{ old('variant_sizes') }}" placeholder="40, 41, 42 أو S, M, L"></label>
      <label class="full">الضمان<textarea name="warranty" rows="3">{{ old('warranty') }}</textarea></label>
    </div>

    <button class="btn btn-primary" type="submit">إرسال للمراجعة</button>
  </form>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-catalog.css') }}">
@endpush
