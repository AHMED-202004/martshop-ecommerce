@extends('layouts.app')
@section('title','اتصل بنا - Mart.ps')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/contact.css') }}">
@endpush

@section('content')
<div class="rtl container-narrow">
  <h1 class="contact-title">خدمات الزبائن - اتصل بمارْت</h1>

  @if(session('status'))
    <div role="status" class="contact-status">
      {{ session('status') }}
    </div>
  @endif

  <div class="card">
    <form method="post" action="{{ route('contact.store') }}" class="rtl">
      @csrf

      <div class="row">
        <div>
          <label for="contactTopic">الموضوع</label>
          <select id="contactTopic" name="topic" class="select" required>
            <option value="">--اختر--</option>
            @foreach($topics as $t)
              <option value="{{ $t }}" {{ old('topic')===$t?'selected':'' }}>{{ $t }}</option>
            @endforeach
          </select>
          @error('topic')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div>
          <label for="contactValue">البريد الإلكتروني أو رقم الهاتف</label>
          <input id="contactValue" type="text" name="contact" class="input" value="{{ old('contact',$prefill['contact']) }}" placeholder="أدخل البريد الإلكتروني أو رقم الهاتف" maxlength="190" aria-describedby="contactHelp" required>
          <div class="note" id="contactHelp">يرجى إدخال البريد الإلكتروني أو رقم الهاتف.</div>
          @error('contact')<div class="err">{{ $message }}</div>@enderror
        </div>

        <div>
          <label for="contactReference">رقم أو مرجع الطلب (اختياري)</label>
          <input id="contactReference" type="text" name="ref" class="input" value="{{ old('ref') }}" placeholder="اختياري" maxlength="190">
          @error('ref')<div class="err">{{ $message }}</div>@enderror
        </div>
      </div>

      <div class="row">
        <div>
          <label for="contactMessage">أرسل رسالتك لفريق الدعم</label>
          <textarea id="contactMessage" name="message" class="input" rows="8" placeholder="اكتب رسالتك هنا..." minlength="5" maxlength="5000" required>{{ old('message') }}</textarea>
          @error('message')<div class="err">{{ $message }}</div>@enderror
        </div>
      </div>

      <button type="submit" class="btn btn-primary">إرسال</button>
    </form>
  </div>
</div>
@endsection
