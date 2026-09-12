@extends('layouts.teacher')

@section('title', 'Chi tiết lần nộp')
@section('page-title', 'Chi tiết lần nộp')

@section('content')
    <a href="{{ route('teacher.results.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Kết quả</a>

    <x-ws.page-header title="Chi tiết lần nộp" subtitle="{{ $attempt->user->name ?? 'Học sinh' }} — {{ $attempt->assessment->title ?? 'Đề' }}" />

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <x-ws.stat label="Điểm tổng" :value="$attempt->total_score !== null ? (string) $attempt->total_score : '—'" tone="success" />
        <x-ws.stat label="Trạng thái" :value="$attempt->is_provisional ? 'Tạm tính' : 'Đã chấm xong'" :tone="$attempt->is_provisional ? 'warning' : 'success'" />
        <x-ws.stat label="Bắt đầu" :value="$attempt->started_at?->format('d/m/Y H:i') ?? '—'" tone="neutral" />
        <x-ws.stat label="Nộp bài" :value="$attempt->submitted_at?->format('d/m/Y H:i') ?? 'Chưa nộp'" tone="neutral" />
    </div>

    <div class="space-y-4">
        @forelse ($answers as $a)
            <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Câu {{ $a['no'] }} · {{ strtoupper($a['type']) }}</p>
                        <p class="font-medium text-slate-700">{{ $a['question']->title ?? '(Câu hỏi đã bị xoá)' }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <x-ws.badge :tone="$a['tone']">{{ $a['verdict'] }}</x-ws.badge>
                        <span class="text-[13px] font-semibold text-slate-600">{{ $a['score'] }} đ</span>
                    </div>
                </div>
                @if ($a['question']?->body)
                    <div class="prose prose-sm max-w-none text-slate-600 mb-3">{!! $a['question']->body !!}</div>
                @endif
                <div>
                    <p class="text-xs font-medium text-slate-500 mb-1">Bài làm học sinh nộp:</p>
                    <pre class="whitespace-pre-wrap break-words bg-slate-50 rounded-xl p-3 text-[13px] text-slate-700">{{ $a['submitted'] }}</pre>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-3xl border border-sky-100 p-6 text-center text-slate-400">Lần nộp này chưa có câu trả lời nào.</div>
        @endforelse
    </div>
@endsection
