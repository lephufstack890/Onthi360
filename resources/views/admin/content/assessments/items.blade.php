@extends('layouts.admin')

@section('title', 'Chọn câu hỏi cho đề/bộ bài')
@section('page-title', 'Chọn câu hỏi cho đề/bộ bài')

@section('content')
    @php
        $questions = $questions ?? [];
        $selectedIds = $selectedIds ?? [];
        $pointsOverrides = $pointsOverrides ?? [];
        $typeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻'];
    @endphp

    <a href="{{ route('admin.content.show', $assessment->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="📋 Chọn câu hỏi" :subtitle="$assessment->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('admin.content.assessments.items.update', $assessment->id) }}">
        @csrf
        @method('PUT')

        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>📚</span> Kho câu hỏi (Kho chung + kho riêng từng giáo viên)</h3>
                <a href="{{ route('admin.content.questions.create') }}" class="text-sm text-rose-600 font-medium">+ Tạo câu hỏi mới</a>
            </div>

            @if (empty($questions))
                <x-empty-state title="Kho câu hỏi đang trống" description="Tạo câu hỏi trước khi gắn vào đề này." actionLabel="Tạo câu hỏi" :actionHref="route('admin.content.questions.create')" />
            @else
                <div class="divide-y divide-slate-100 max-h-[32rem] overflow-y-auto">
                    @foreach ($questions as $q)
                        <label class="flex items-center justify-between py-3 gap-3 cursor-pointer">
                            <div class="flex items-center gap-3 min-w-0">
                                <input type="checkbox" name="question_ids[]" value="{{ $q['id'] }}" @checked(in_array($q['id'], old('question_ids', $selectedIds)))>
                                <span class="text-base shrink-0">{{ $typeIcons[$q['type']] ?? '❓' }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm text-slate-700 truncate">{{ $q['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $q['ownerLabel'] }} · {{ $q['status'] === 'published' ? 'Đã phát hành' : 'Nháp' }}</p>
                                </div>
                            </div>
                            <input type="number" name="points_override[{{ $q['id'] }}]" value="{{ old('points_override.'.$q['id'], $pointsOverrides[$q['id']] ?? $q['points']) }}" min="1" max="100"
                                   class="w-16 rounded-lg border border-slate-200 text-sm p-1.5 text-center shrink-0" onclick="event.stopPropagation()">
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-2">Câu còn "Nháp" vẫn gắn được vào đề, nhưng đề chỉ phát hành được khi mọi câu đã Phát hành (6.2).</p>
            @endif
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu danh sách câu hỏi</button>
            <a href="{{ route('admin.content.show', $assessment->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
        </div>
    </form>
@endsection
