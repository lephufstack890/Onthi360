{{--
  Route: reviews.myReviews | Frame: REV-04
  Spec: 9.4 (trạng thái review: Bản nháp → Đã gửi → Đang kiểm duyệt →
  Đã công bố / Cần chỉnh sửa / Từ chối có lý do / Ẩn sau khi công bố).
  Dữ liệu thật ($myReviews) do App\Http\Controllers\ReviewController::myReviews() truyền vào
  qua App\Services\Review\ReviewService::buildMyReviews().
--}}
@extends('layouts.student')

@section('title', 'Đánh giá của tôi')
@section('page-title', 'Đánh giá của tôi')

@section('content')
    @php
        $myReviews = $myReviews ?? [];
    @endphp

    @if (session('status') === 'review-submitted')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gửi đánh giá — sẽ hiển thị công khai sau khi được kiểm duyệt (9.4).'])
    @endif

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
