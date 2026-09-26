@extends('layouts.private-finance')
@section('title', 'التحقق من وسائل استرداد العملاء')
@section('content')
<p>الأرقام مخفية في القائمة. فتح التفاصيل يسجّل في سجل التدقيق. اعتماد الوسيلة مستقل عن الموافقة على مبلغ الاسترداد.</p>
@forelse($destinations as $destination)
  <section>
    <h2>{{ $destination->refund->reference }} — وسيلة #{{ $destination->id }}</h2>
    <p>{{ $destination->maskedIdentifier() }} — {{ $destination->status->label() }}</p>
    <p>حالة طلب الاسترداد: {{ $destination->refund->status->label() }}</p>
    <a href="{{ route('refund-destinations.show', $destination) }}">فتح البيانات الخاصة والتحقق</a>
  </section>
@empty<p>لا توجد وسائل استلام مسجلة للمراجعة حاليًا.</p>@endforelse
{{ $destinations->links() }}
@endsection
