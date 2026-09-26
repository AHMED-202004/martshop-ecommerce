@extends('layouts.private-finance')
@section('title', 'تفاصيل رسالة العميل')
@section('privacy-notice', 'فتحت رسالة عميل خاصة، وقد سُجّل هذا الوصول في سجل التدقيق. استخدم البيانات لغرض الدعم فقط.')

@section('content')
<article class="contact-message-detail rtl">
  <a href="{{ route('admin.contact-messages.index') }}">العودة إلى رسائل العملاء</a>
  <header>
    <h1>{{ $contactMessage->topic }}</h1>
    <p>#{{ $contactMessage->id }} · {{ $contactMessage->created_at->format('Y-m-d H:i:s') }}</p>
  </header>
  @if(session('success'))<p class="support-success" role="status">{{ session('success') }}</p>@endif
  @if($errors->any())<p class="support-error" role="alert">{{ $errors->first() }}</p>@endif
  <dl>
    <div><dt>المرسل</dt><dd>{{ $contactMessage->user?->name ?? 'زائر' }}</dd></div>
    <div><dt>وسيلة التواصل</dt><dd><bdi>{{ $contactMessage->contact }}</bdi></dd></div>
    <div><dt>مرجع الطلب</dt><dd>{{ $contactMessage->ref ?: '—' }}</dd></div>
    <div><dt>الحالة</dt><dd>{{ $contactMessage->status->label() }}</dd></div>
    <div><dt>الموظف المسؤول</dt><dd>{{ $contactMessage->assignee?->name ?? 'غير مسندة' }}</dd></div>
    <div><dt>أول رد</dt><dd>{{ $contactMessage->first_response_at?->format('Y-m-d H:i:s') ?? '—' }}</dd></div>
  </dl>
  <section aria-labelledby="message-body-title">
    <h2 id="message-body-title">نص الرسالة</h2>
    <p>{{ $contactMessage->message }}</p>
  </section>
  @if($canManage)
    <section class="support-workflow" aria-labelledby="support-workflow-title">
      <h2 id="support-workflow-title">إدارة التذكرة</h2>
      <div class="support-workflow-grid">
        <form method="POST" action="{{ route('admin.contact-messages.assign', $contactMessage) }}">
          @csrf @method('PATCH')
          <input type="hidden" name="expected_version" value="{{ $contactMessage->lock_version }}">
          <label>إسناد إلى موظف
            <select name="assigned_to" required>
              <option value="">اختر</option>
              @foreach($agents as $agent)<option value="{{ $agent->id }}" @selected($contactMessage->assigned_to === $agent->id)>{{ $agent->name }}{{ $agent->employee_number ? ' — '.$agent->employee_number : '' }}</option>@endforeach
            </select>
          </label>
          <button>حفظ الإسناد</button>
        </form>
        <form method="POST" action="{{ route('admin.contact-messages.transition', $contactMessage) }}">
          @csrf @method('PATCH')
          <input type="hidden" name="expected_version" value="{{ $contactMessage->lock_version }}">
          <label>الحالة
            <select name="status" required>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($contactMessage->status === $status)>{{ $status->label() }}</option>@endforeach</select>
          </label>
          <button>تحديث الحالة</button>
        </form>
      </div>
      <form method="POST" action="{{ route('admin.contact-messages.reply', $contactMessage) }}">
        @csrf
        <input type="hidden" name="expected_version" value="{{ $contactMessage->lock_version }}">
        <label>الرد<textarea name="body" minlength="2" maxlength="4000" required></textarea></label>
        <label class="support-check"><input type="checkbox" name="is_internal" value="1"> ملاحظة داخلية لا تُحسب كأول رد للعميل</label>
        <button @disabled($contactMessage->status === \App\Enums\SupportTicketStatus::Closed)>حفظ الرد</button>
      </form>
    </section>
  @endif
  <section aria-labelledby="support-replies-title">
    <h2 id="support-replies-title">سجل الردود</h2>
    @forelse($contactMessage->replies as $reply)
      <article class="support-reply">
        <header><strong>{{ $reply->author?->name ?? 'حساب سابق' }}</strong><span>{{ $reply->created_at?->format('Y-m-d H:i:s') }}{{ $reply->is_internal ? ' — داخلي' : '' }}</span></header>
        <p>{{ $reply->body }}</p>
      </article>
    @empty
      <p>لا توجد ردود بعد.</p>
    @endforelse
  </section>
</article>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin-contact-messages.css') }}">
@endpush
