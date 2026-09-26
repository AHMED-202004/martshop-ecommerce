@extends('layouts.app')
@section('title', 'تاريخ الطلبات - Mart.ps')
@section('content')
@php
  $orderLabels = [
    'pending' => 'بانتظار التأكيد',
    'pending_confirmation' => 'بانتظار تأكيد التجار',
    'partially_confirmed' => 'مؤكد جزئيًا',
    'confirmed' => 'مؤكد',
    'rejected' => 'مرفوض',
    'expired' => 'انتهت مهلة التأكيد',
    'cancelled' => 'ملغي',
  ];
  $paymentLabels = [
    'unpaid' => 'غير مدفوع', 'pending_review' => 'الدفع قيد المراجعة',
    'paid' => 'مدفوع', 'action_required' => 'الدفع يحتاج إجراء',
  ];
  $deliveryLabels = [
    'assigned' => 'تم إسناد التوصيل', 'accepted' => 'قبل عامل التوصيل المهمة',
    'picked_up' => 'استلم العامل الطرد', 'in_transit' => 'الطرد في الطريق', 'delivered' => 'تم التسليم',
  ];
@endphp
<main class="rtl order-history container">
  <h2 class="order-history-title">تاريخ الطلبات</h2>
  @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
  @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif

  @forelse ($orders as $order)
    @php $latestPayment = $order->latestPayment; @endphp
    <article class="order-card" aria-labelledby="order-{{ $order->id }}">
      <div class="order-summary">
        <div id="order-{{ $order->id }}">الرقم: {{ $order->id }}</div>
        <div>التاريخ: {{ $order->created_at->format('Y-m-d') }}</div>
        <div>السعر: {{ number_format($order->total, 2) }} {{ $order->currency ?: 'ILS' }}</div>
        <div class="badge">{{ $orderLabels[$order->status->value] ?? $order->status->value }}</div>
        <div class="badge">{{ $paymentLabels[$order->payment_status->value] ?? $order->payment_status->value }}</div>
      </div>
      <div class="order-body">
        @if($order->merchant_orders_count > 1)
          <div class="order-split-note">تم تقسيم الطلب إلى {{ $order->merchant_orders_count }} طلبات حسب التجار.</div>
        @endif
        @foreach($order->items as $item)
          <div class="order-item">
            <img src="{{ $item->image ?? asset('assets/img/placeholder.png') }}" alt="{{ $item->product_name }}">
            <div>
              <div class="order-item-name">{{ $item->product_name }}</div>
            </div>
            <div>السعر: {{ number_format($item->price, 2) }} {{ $order->currency ?: 'ILS' }}</div>
            <div>الكمية: {{ $item->qty }}</div>
          </div>
        @endforeach
        <div class="order-actions">
          @foreach($order->deliveries as $delivery)
            <span>توصيل #{{ $delivery->merchant_order_id }}: {{ $deliveryLabels[$delivery->status->value] ?? $delivery->status->value }}</span>
            @if($delivery->status->value !== 'delivered')
              <strong>رمز الاستلام: {{ $delivery->confirmation_pin }}</strong>
              <small>لا تعطِ الرمز إلا بعد استلام الطرد وفحصه.</small>
            @elseif($delivery->dispute)
              <span>النزاع: {{ $delivery->dispute->status === 'open' ? 'قيد المراجعة' : ($delivery->dispute->status === 'refunded' ? 'أُغلق باسترداد مسجّل' : 'مغلق') }}</span>
              @if($delivery->dispute->status === 'closed')<small>{{ $delivery->dispute->close_reason }}</small>@endif
              @if($refund = $delivery->dispute->refundRequest)
                <span>{{ $refund->reference }}: {{ $refund->status->label() }} — {{ number_format($refund->amount / 100, 2) }} {{ $refund->currency }}</span>
                @if($refund->review_reason)<small>سبب القرار: {{ $refund->review_reason }}</small>@endif
                <a href="{{ route('refund-destinations.index', $refund) }}">وسيلة استلام الاسترداد</a>
                @if($refund->transfer?->paid_at)
                  <a href="{{ route('refund-transfers.proof', $refund->transfer) }}">تنزيل إثبات حوالة الاسترداد</a>
                @endif
              @elseif($delivery->dispute->status === 'open' && !$delivery->settled_at)
                <form method="POST" action="{{ route('refund-requests.store', $delivery) }}">@csrf
                  <p>يمكنك طلب مراجعة استرداد كامل لهذا الطلب الفرعي، بما فيه رسوم التوصيل والخدمة. تقديم الطلب لا يضمن الموافقة ولا يعني تحويل المبلغ.</p>
                  <label for="refund-reason-{{ $delivery->id }}">سبب طلب الاسترداد</label>
                  <textarea id="refund-reason-{{ $delivery->id }}" name="reason" required minlength="5" maxlength="2000"></textarea>
                  <button class="btn">طلب مراجعة الاسترداد الكامل</button>
                </form>
              @endif
            @elseif(!$delivery->settled_at && $delivery->settlement_due_at?->isFuture())
              <form method="POST" action="{{ route('delivery-disputes.store', $delivery) }}">@csrf
                <small>يمكنك فتح نزاع حتى {{ $delivery->settlement_due_at->format('Y-m-d H:i') }}</small>
                <textarea name="reason" required minlength="5" maxlength="2000" placeholder="اشرح المشكلة في الطرد"></textarea>
                <button class="btn">فتح نزاع</button>
              </form>
            @endif
            @if($delivery->status->value === 'delivered')
              @if($delivery->rating)<p>تقييم التوصيل: {{ $delivery->rating->rating }} من 5</p>
              @else<form method="POST" action="{{ route('delivery-ratings.store', $delivery) }}">@csrf
                <label>قيّم تجربة التوصيل<select name="rating" required><option value="">اختر</option>@foreach(range(5, 1) as $score)<option value="{{ $score }}">{{ $score }} من 5</option>@endforeach</select></label>
                <label>تعليق اختياري<textarea name="comment" maxlength="1000"></textarea></label><button class="btn">إرسال التقييم</button>
              </form>@endif
            @endif
          @endforeach
          @if($latestPayment)
            <span>آخر إثبات: #{{ $latestPayment->id }} — {{ $latestPayment->status->value }}</span>
            @if($latestPayment->proof)<a href="{{ route('payment-proofs.show', $latestPayment->proof) }}">عرض الإثبات</a>@endif
          @endif
          @if($order->status === \App\Enums\OrderStatus::Confirmed && $order->payment_status !== \App\Enums\OrderPaymentStatus::Paid && $order->payment_status !== \App\Enums\OrderPaymentStatus::PendingReview)
            <a class="btn btn-primary" href="{{ route('payments.create', $order) }}">تحويل المبلغ ورفع الإثبات</a>
          @elseif($order->payment_status === \App\Enums\OrderPaymentStatus::PendingReview)
            <a class="btn" href="{{ route('payments.create', $order) }}">متابعة مراجعة الدفع</a>
          @endif
        </div>
      </div>
    </article>
  @empty
    <div class="order-empty">لا يوجد طلبات بعد.</div>
  @endforelse

  @if($orders->hasPages())
    <nav class="order-pagination" aria-label="صفحات سجل الطلبات">
      @if($orders->onFirstPage())<span aria-disabled="true">السابق</span>@else<a href="{{ $orders->previousPageUrl() }}">السابق</a>@endif
      <strong>صفحة {{ $orders->currentPage() }} من {{ $orders->lastPage() }}</strong>
      @if($orders->hasMorePages())<a href="{{ $orders->nextPageUrl() }}">التالي</a>@else<span aria-disabled="true">التالي</span>@endif
    </nav>
  @endif

  <div class="order-return">
    <a href="{{ route('my-account') }}" class="btn btn-secondary">عودة إلى حسابي في مارت</a>
  </div>
</main>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/order-history.css') }}">
@endpush
