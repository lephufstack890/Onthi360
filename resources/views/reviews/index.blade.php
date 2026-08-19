@extends('layouts.guest')

@section('title', 'Đánh giá')

@section('content')
    @php
        $type = $type ?? request('type', 'material');
        $targetId = $targetId ?? 1;
        $targetTitle = $targetTitle ?? '';
        $distribution = $distribution ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $reviews = $reviews ?? [];
        $ratingAverage = $ratingSummary->avg_rating ?? 0;
        $ratingCount = $ratingSummary->review_count ?? 0;
        $isRankable = $isRankable ?? false;
    @endphp

    <div class="max-w-4xl mx-auto px-4 py-10">
        <a href="{{ url()->previous() }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại</a>

        <x-page-header :title="'Đánh giá — '.$targetTitle" subtitle="Rating chỉ đến từ người đã trải nghiệm thực — không đánh đồng với bảng xếp hạng học tập (9.1).">
            <x-slot:actions>
                <a href="{{ route('reviews.form', ['type' => $type, 'id' => $targetId]) }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Viết đánh giá</a>
            </x-slot:actions>
        </x-page-header>

        {{-- Summary + phân phối --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-6 mb-6 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="text-center sm:border-r sm:border-slate-100">
                @if ($isRankable)
                    <p class="text-4xl font-semibold text-slate-800">{{ number_format($ratingAverage, 1) }}</p>
                @endif
                <x-rating-summary :average="$ratingAverage" :count="$ratingCount" />
            </div>
            <div class="space-y-1.5">
                @foreach ($distribution as $star => $pct)
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="w-8">{{ $star }} sao</span>
                        <div class="flex-1 h-2 rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full bg-amber-400 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                        <span class="w-8 text-right">{{ $pct }}%</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Lọc --}}
        <div class="flex flex-wrap gap-2 mb-4 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Mới nhất</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Hữu ích nhất</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">5 sao</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">4 sao</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Có nhận xét</button>
        </div>

        {{-- Danh sách review --}}
        <div class="space-y-4">
            @forelse ($reviews as $r)
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <p class="text-sm font-medium text-slate-700">{{ $r['author'] }}</p>
                            <p class="text-xs text-slate-400">{{ $r['time'] }}</p>
                        </div>
                        <span class="text-amber-500">{{ str_repeat('★', $r['rating']) }}{{ str_repeat('☆', 5 - $r['rating']) }}</span>
                    </div>
                    <p class="text-sm text-slate-600">{{ $r['content'] }}</p>
                    {{-- TODO: nút "Báo lỗi nội dung" tách khỏi review sao (9.1) --}}
                </div>
            @empty
                <x-empty-state title="Chưa có đánh giá nào" description="Hãy là người đầu tiên chia sẻ trải nghiệm." />
            @endforelse
        </div>
    </div>
@endsection
