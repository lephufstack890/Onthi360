@extends('layouts.student')

@section('title', 'Kết quả')
@section('page-title', 'Kết quả bài làm')

@section('content')
    @php
        $isFinal = $isFinal ?? false;
        $score = $score ?? null;
        $total = $total ?? null;
        $breakdown = $breakdown ?? [];
        $eligibleForReview = $eligibleForReview ?? false;
        $reviewType = $reviewType ?? 'material';
        $reviewTargetId = $reviewTargetId ?? null;
        $submittedLabel = isset($attemptModel) && $attemptModel->submitted_at
            ? 'Nộp lúc '.$attemptModel->submitted_at->format('H:i d/m/Y')
                .(($attemptModel->started_at) ? ' · Thời gian làm bài: '.$attemptModel->started_at->diffInMinutes($attemptModel->submitted_at).' phút' : '')
            : 'Chưa nộp';

        $percent = ($total !== null && $total > 0 && $score !== null) ? (int) round($score / $total * 100) : null;

        [$resultEmoji, $resultHeadline] = match (true) {
            ! $isFinal => ['⏳', 'Đang chờ chấm phần còn lại'],
            $percent === null => ['📄', 'Đã ghi nhận bài làm'],
            $percent >= 90 => ['🏆', 'Xuất sắc!'],
            $percent >= 70 => ['🎉', 'Làm tốt lắm!'],
            $percent >= 50 => ['💪', 'Khá ổn, cố thêm chút nữa nhé!'],
            default => ['📚', 'Cần ôn luyện thêm — đừng nản nhé!'],
        };
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-sky-50 via-white to-rose-50 border border-slate-100 p-8 lg:p-10 mb-6 text-center">
        <div class="text-4xl mb-2">{{ $resultEmoji }}</div>
        <p class="text-base font-medium text-slate-700">{{ $resultHeadline }}</p>

        <p class="text-5xl font-bold text-slate-800 mt-4">
            {{ $score ?? '—' }}<span class="text-xl font-medium text-slate-400"> / {{ $total ?? '—' }}</span>
        </p>
        @if ($percent !== null)
            <div class="max-w-xs mx-auto mt-4 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                <div class="h-full rounded-full {{ $percent >= 70 ? 'bg-emerald-500' : ($percent >= 50 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ $percent }}%"></div>
            </div>
            <p class="text-xs text-slate-400 mt-1.5">{{ $percent }}% số điểm</p>
        @endif

        <p class="text-sm text-slate-500 mt-4">{{ $submittedLabel }}</p>

        @if (! $isFinal)
            <div class="mt-3">
                <x-status-badge tone="info">Kết quả tạm tính — còn câu lập trình đang chờ chấm</x-status-badge>
            </div>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-slate-100">
            <h2 class="font-medium text-slate-700">Chi tiết từng câu</h2>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr><th class="px-4 py-3">Câu</th><th class="px-4 py-3">Loại</th><th class="px-4 py-3">Kết quả</th><th class="px-4 py-3 text-right">Điểm</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($breakdown as $b)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-700">Câu {{ $b['no'] }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ match ($b['type']) { 'mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền đáp án', 'coding' => 'Lập trình', default => $b['type'] } }}</td>
                        <td class="px-4 py-3"><x-status-badge :tone="$b['tone']">{{ $b['verdict'] }}</x-status-badge></td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ $b['points'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu câu nào.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="flex flex-wrap gap-3 mb-8">
        <a href="{{ route('student.practice.index') }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">← Quay lại Luyện tập</a>
        <a href="#" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Xem lời giải (nếu đã công bố)</a>
    </div>

    {{-- CTA đánh giá nhẹ nhàng — không chặn hành trình học (10.1, 9.6); chỉ hiện khi đủ điều
    kiện, và trỏ ĐÚNG tài liệu/lớp học sinh vừa làm (trước đây hardcode type=material&id=1). --}}
    @if ($eligibleForReview && $reviewTargetId !== null)
        <div class="rounded-2xl bg-amber-50 border border-amber-100 p-5 flex items-center justify-between flex-wrap gap-3">
            <div>
                <p class="font-medium text-slate-700">{{ $reviewType === 'class' ? 'Bạn thấy lớp học này thế nào?' : 'Bạn thấy tài liệu này thế nào?' }}</p>
                <p class="text-sm text-slate-500">Chia sẻ trải nghiệm giúp học sinh khác chọn đúng {{ $reviewType === 'class' ? 'lớp' : 'tài liệu' }} hơn.</p>
            </div>
            <a href="{{ route('reviews.form', ['type' => $reviewType, 'id' => $reviewTargetId]) }}" class="px-4 py-2 rounded-lg bg-amber-500 text-white text-sm font-medium shrink-0">
                {{ $reviewType === 'class' ? 'Đánh giá lớp này' : 'Đánh giá tài liệu này' }}
            </a>
        </div>
    @endif
@endsection
