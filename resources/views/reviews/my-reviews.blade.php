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
            <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center justify-between gap-3">
                <div>
                    <p class="font-medium text-slate-700">{{ $r['target'] }}</p>
                    <p class="text-sm text-amber-500">{{ str_repeat('★', $r['rating']) }}</p>
                    <p class="text-xs text-slate-400">{{ $r['time'] }}</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge>
                    @if ($r['isEditable'])
                        <a href="{{ route('reviews.form', ['type' => $r['type'], 'id' => $r['targetId']]) }}" class="text-xs font-medium text-rose-600 hover:text-rose-700 whitespace-nowrap">Sửa</a>
                    @endif
                </div>
            </div>
        @empty
            <x-empty-state title="Bạn chưa viết đánh giá nào" />
        @endforelse
    </div>
@endsection
