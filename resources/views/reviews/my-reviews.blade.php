{{--
  Route: reviews.myReviews | Frame: REV-04
  Spec: 9.4 (trạng thái review: Bản nháp → Đã gửi → Đang kiểm duyệt →
  Đã công bố / Cần chỉnh sửa / Từ chối có lý do / Ẩn sau khi công bố).
  TODO controller: truyền $myReviews (Review::where('reviewer_id', ...)).
--}}
@extends('layouts.student')

@section('title', 'Đánh giá của tôi')
@section('page-title', 'Đánh giá của tôi')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\ReviewController truyền vào. --}}
    @php
        $myReviews = $myReviews ?? [];
    @endphp

    <x-page-header title="Đánh giá của tôi" />

    <div class="space-y-3">
        @forelse ($myReviews as $r)
            <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center justify-between">
                <div>
                    <p class="font-medium text-slate-700">{{ $r['target'] }}</p>
                    <p class="text-sm text-amber-500">{{ str_repeat('★', $r['rating']) }}</p>
                    <p class="text-xs text-slate-400">{{ $r['time'] }}</p>
                </div>
                <x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge>
            </div>
        @empty
            <x-empty-state title="Bạn chưa viết đánh giá nào" />
        @endforelse
    </div>
@endsection
