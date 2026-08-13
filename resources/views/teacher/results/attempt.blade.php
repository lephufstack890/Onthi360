{{--
  Route: teacher.results.attempt
  Spec: 10.2 — giáo viên xem chi tiết một lần nộp cụ thể (đáp án học sinh nộp,
  verdict/điểm từng câu). Chỉ xem được lần nộp thuộc lớp mình dạy (thực thi tại
  App\Services\Teacher\ResultService::attemptDetailFor()).
--}}
@extends('layouts.teacher')

@section('title', 'Chi tiết lần nộp')
@section('page-title', 'Chi tiết lần nộp')

@section('content')
    <a href="{{ route('teacher.results.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Kết quả</a>

    <x-page-header title="Chi tiết lần nộp" subtitle="{{ $attempt->user->name ?? 'Học sinh' }} — {{ $attempt->assessment->title ?? 'Đề' }}" />

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <x-stat-tile label="Điểm tổng" :value="$attempt->total_score !== null ? (string) $attempt->total_score : '—'" tone="success" />
        <x-stat-tile label="Trạng thái" :value="$attempt->is_provisional ? 'Tạm tính' : 'Đã chấm xong'" :tone="$attempt->is_provisional ? 'warning' : 'success'" />
        <x-stat-tile label="Bắt đầu" :value="$attempt->started_at?->format('d/m/Y H:i') ?? '—'" tone="neutral" />
        <x-stat-tile label="Nộp bài" :value="$attempt->submitted_at?->format('d/m/Y H:i') ?? 'Chưa nộp'" tone="neutral" />
    </div>

    <div class="space-y-4">
        @forelse ($answers as $a)
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-4 mb-3">
                    <div>
                        <p class="text-xs text-slate-400 mb-1">Câu {{ $a['no'] }} · {{ strtoupper($a['type']) }}</p>
                        <p class="font-medium text-slate-700">{{ $a['question']->title ?? '(Câu hỏi đã bị xoá)' }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <x-status-badge :tone="$a['tone']">{{ $a['verdict'] }}</x-status-badge>
                        <span class="text-sm font-semibold text-slate-600">{{ $a['score'] }} đ</span>
                    </div>
                </div>
                @if ($a['question']?->body)
                    <div class="prose prose-sm max-w-none text-slate-600 mb-3">{!! $a['question']->body !!}</div>
                @endif
                <div>
                    <p class="text-xs font-medium text-slate-500 mb-1">Bài làm học sinh nộp:</p>
                    <pre class="whitespace-pre-wrap break-words bg-slate-50 rounded-lg p-3 text-sm text-slate-700">{{ $a['submitted'] }}</pre>
                </div>
            </div>
        @empty
            <div class="bg-white rounded-2xl border border-slate-200 p-6 text-center text-slate-400">Lần nộp này chưa có câu trả lời nào.</div>
        @endforelse
    </div>
@endsection
