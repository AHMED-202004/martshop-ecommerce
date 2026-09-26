@extends('layouts.app')
@section('title','التصنيفات - Mart.ps')

@section('content')
<div class="container categories-index">
  <h1>التصنيفات</h1>

  <div class="categories-index-grid">
    @foreach($roots as $r)
      <a class="categories-index-card" href="{{ url('/c/'.$r['slug']) }}">
        <div class="categories-index-title">{{ $r['title'] }}</div>
        <div class="categories-index-hint">عرض الخيارات</div>
      </a>
    @endforeach
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/category-index.css') }}">
@endpush
