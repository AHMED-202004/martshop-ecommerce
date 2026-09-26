@extends('layouts.app')
@section('title', 'ملف التاجر - Mart.ps')

@section('content')
@php
  $status = $merchant?->verification_status;
  $editable = !$merchant || in_array($status, [
    \App\Enums\MerchantVerificationStatus::Incomplete,
    \App\Enums\MerchantVerificationStatus::ChangesRequested,
    \App\Enums\MerchantVerificationStatus::Rejected,
  ], true);
  $statusLabels = [
    'incomplete' => 'غير مكتمل', 'pending_review' => 'قيد المراجعة',
    'changes_requested' => 'مطلوب تعديل', 'verified' => 'موثّق',
    'rejected' => 'مرفوض', 'suspended' => 'معلّق',
  ];
  $typeLabels = [
    'identity_front' => 'الهوية – الوجه الأمامي',
    'identity_back' => 'الهوية – الوجه الخلفي',
    'personal_photo' => 'الصورة الشخصية',
  ];
@endphp

<div class="container rtl page-pad page-narrow-980">
  <h1>ملف التاجر والتحقق</h1>

  @if(session('success')) <div class="notice success">{{ session('success') }}</div> @endif
  @if($errors->any())
    <div class="notice error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif

  @if(!$merchant && !($registrationEnabled ?? true))
    <div class="notice error" role="status">تسجيل تجار جدد متوقف مؤقتًا. إذا كان لديك طلب سابق فتواصل مع فريق الدعم.</div>
  @endif

  @if($merchant)
    <div class="panel">
      <strong>الحالة:</strong>
      <span>{{ $statusLabels[$status->value] ?? $status->value }}</span>
      @if($merchant->review_notes)<p><strong>ملاحظات المراجعة:</strong> {{ $merchant->review_notes }}</p>@endif
    </div>
  @endif

  @if($merchant || ($registrationEnabled ?? true))
  <form method="POST" action="{{ route('merchant.profile.update') }}" class="panel">
    @csrf @method('PUT')
    <h2>المعلومات القانونية</h2>
    <div class="grid">
      <label>الاسم القانوني الكامل<input name="legal_name" value="{{ old('legal_name', $merchant?->legal_name) }}" required {{ $editable ? '' : 'disabled' }}></label>
      <label>رقم الهوية<input name="identity_number" value="{{ old('identity_number', $editable ? $merchant?->identity_number : '') }}" required {{ $editable ? '' : 'disabled' }}></label>
      <label>رقم الهاتف<input name="phone" value="{{ old('phone', $merchant?->phone) }}" required {{ $editable ? '' : 'disabled' }}></label>
      <label>تاريخ الميلاد<input type="date" name="date_of_birth" value="{{ old('date_of_birth', $merchant?->date_of_birth?->toDateString()) }}" required {{ $editable ? '' : 'disabled' }}></label>
      <label>المنطقة
        <select name="location_id" required {{ $editable ? '' : 'disabled' }}>
          <option value="">اختر المنطقة</option>
          @foreach($locations as $location)
            <option value="{{ $location->id }}" @selected((string)old('location_id', $merchant?->location_id) === (string)$location->id)>{{ $location->name }}</option>
          @endforeach
        </select>
      </label>
      <label>نوع النشاط<input name="business_type" value="{{ old('business_type', $merchant?->business_type) }}" required {{ $editable ? '' : 'disabled' }}></label>
    </div>
    <label>العنوان الدقيق<textarea name="address" rows="3" required {{ $editable ? '' : 'disabled' }}>{{ old('address', $merchant?->address) }}</textarea></label>
    @if($editable)<label>كلمة المرور الحالية<input type="password" name="current_password" required autocomplete="current-password"></label>@endif
    @if($editable)<button class="btn btn-primary" type="submit">حفظ ملف التاجر</button>@endif
  </form>
  @endif

  @if($merchant)
    <section class="panel">
      <h2>المستندات المطلوبة</h2>
      <p>تُحفظ هذه الملفات في مساحة خاصة ولا تملك رابطًا عامًا.</p>
      @foreach($requiredDocumentTypes as $type)
        @php $latest = $merchant->documents->first(fn($doc) => $doc->type === $type); @endphp
        <div class="document-row">
          <div>
            <strong>{{ $typeLabels[$type->value] ?? $type->value }}</strong>
            @if($latest)
              <span>— {{ $latest->status->value }}</span>
              <a href="{{ route('merchant.documents.show', $latest) }}">تنزيل المستند</a>
            @else <span>— لم يُرفع</span> @endif
          </div>
          @if($editable)
            <form method="POST" action="{{ route('merchant.documents.store') }}" enctype="multipart/form-data">
              @csrf
              <input type="hidden" name="type" value="{{ $type->value }}">
              <input type="file" name="document" accept="image/jpeg,image/png,application/pdf" required>
              <input type="password" name="current_password" placeholder="كلمة المرور الحالية" required autocomplete="current-password">
              <button class="btn" type="submit">{{ $latest ? 'رفع نسخة جديدة' : 'رفع' }}</button>
            </form>
          @endif
        </div>
      @endforeach
    </section>

    @if($editable)
      <form method="POST" action="{{ route('merchant.submit') }}" class="panel">
        @csrf
        <p>بعد الإرسال لن تتمكن من تعديل البيانات حتى تنتهي المراجعة أو تُطلب تعديلات.</p>
        <label>كلمة المرور الحالية<input type="password" name="current_password" required autocomplete="current-password"></label>
        <button class="btn btn-primary" type="submit">إرسال الطلب للمراجعة</button>
      </form>
    @endif
  @endif
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/merchant-profile.css') }}">
@endpush
