{{--
  Route: reviews.ineligible | Frame: REV-03
  Spec: 9.2 (nêu rõ còn thiếu "mở tài liệu" hay "tham gia 2 buổi").
  TODO controller: truyền $reason thật từ ReviewEligibilityService (App\Services).
--}}
@extends('layouts.guest')

@section('title', 'Chưa đủ điều kiện đánh giá')

@section('content')
    {{-- $reason do App\Http\Controllers\ReviewController truyền vào. --}}
    @php
        $reason = $reason ?? 'Bạn cần tham gia ít nhất 2 buổi học hoặc hoàn thành một hoạt động trong lớp trước khi đánh giá.';
    @endphp

    <div class="max-w-md mx-auto px-4 py-16 text-center">
        <div class="text-5xl mb-4">🌱</div>
        <h1 class="text-lg font-semibold text-slate-800 mb-2">Chưa đủ điều kiện đánh giá</h1>
        <p class="text-sm text-slate-500">{{ $reason }}</p>
        <a href="{{ route('dashboard') }}" class="inline-block mt-6 px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Quay lại Tổng quan</a>
    </div>
@endsection
