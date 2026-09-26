@extends('layouts.private-finance')
@section('title', 'رسائل العملاء')
@section('privacy-notice', 'رسائل العملاء خاصة وقد تحتوي بيانات تواصل أو مراجع طلبات. استخدمها لغرض الدعم فقط ولا تشاركها خارج الفريق المخوّل.')

@section('content')
<div class="contact-inbox rtl">
  <div class="inbox-head">
    <h1>رسائل العملاء</h1>
    <strong>{{ $messages->total() }} رسالة</strong>
  </div>

  <div class="inbox-list">
    @forelse($messages as $contactMessage)
      <article class="inbox-message" aria-labelledby="message-{{ $contactMessage->id }}">
        <header>
          <div>
            <h2 id="message-{{ $contactMessage->id }}">{{ $contactMessage->topic }}</h2>
            <span>#{{ $contactMessage->id }} · {{ $contactMessage->created_at->format('Y-m-d H:i:s') }}</span>
          </div>
          <span>{{ $contactMessage->user?->name ?? 'زائر' }}</span>
        </header>
        <a class="message-open" href="{{ route('admin.contact-messages.show', $contactMessage) }}">فتح التفاصيل الخاصة</a>
      </article>
    @empty
      <p class="empty">لا توجد رسائل حاليًا.</p>
    @endforelse
  </div>

  {{ $messages->links() }}
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/admin-contact-messages.css') }}">
@endpush
