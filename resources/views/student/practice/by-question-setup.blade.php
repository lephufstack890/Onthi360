@extends('layouts.student')

@section('title', 'Luyện tập theo câu')
@section('page-title', 'Luyện tập theo câu')

@section('content')
    @php $allTags = $allTags ?? collect(); @endphp

    <a href="{{ route('student.practice.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Luyện tập</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-cyan-50 to-emerald-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <p class="text-sm text-sky-600 font-medium">🎯 Luyện tập theo câu</p>
            <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">Luyện từng câu, biết đúng/sai ngay</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-lg">Chọn chuyên đề muốn ôn — hệ thống sẽ trộn ngẫu nhiên câu hỏi từ Kho chung, luyện xong 1 câu là biết ngay đúng hay sai rồi qua câu tiếp theo.</p>
        </div>
        <div class="text-5xl">🧠</div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('student.practiceByQuestion.start') }}" class="bg-white rounded-2xl border border-slate-200 p-5 lg:p-6 space-y-5 max-w-10xl">
        @csrf

        <div>
            <p class="text-sm font-medium text-slate-700 mb-2">Dạng câu hỏi</p>
            <div class="flex flex-wrap gap-2">
                @foreach ([['value' => '', 'label' => 'Cả 2 dạng', 'icon' => '🌈'], ['value' => 'mcq', 'label' => 'Trắc nghiệm', 'icon' => '🔤'], ['value' => 'fill_blank', 'label' => 'Điền đáp án', 'icon' => '✏️']] as $tf)
                    <label class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm font-medium border border-slate-200 text-slate-600 has-[:checked]:bg-rose-600 has-[:checked]:border-rose-600 has-[:checked]:text-white transition cursor-pointer">
                        <input type="radio" name="type" value="{{ $tf['value'] }}" class="hidden" @checked(old('type', '') === $tf['value'])>
                        <span>{{ $tf['icon'] }}</span> {{ $tf['label'] }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <p class="text-sm font-medium text-slate-700 mb-2">Chuyên đề (bỏ trống = tất cả)</p>
            @if ($allTags->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($allTags as $tagOption)
                        <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm border border-slate-200 text-slate-600 has-[:checked]:bg-sky-50 has-[:checked]:border-sky-300 has-[:checked]:text-sky-700 transition cursor-pointer">
                            <input type="checkbox" name="tag_ids[]" value="{{ $tagOption->id }}"
                                   @checked(collect(old('tag_ids', []))->contains((string) $tagOption->id))>
                            {{ $tagOption->name }}
                        </label>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-slate-400">Chưa có chuyên đề nào — bỏ trống để luyện toàn bộ câu hỏi Kho chung.</p>
            @endif
        </div>

        <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
            Chỉ luyện câu Trắc nghiệm/Điền đáp án thuộc Kho chung đã phát hành — không tính vào lịch sử làm bài, không giới hạn số lần luyện.
        </div>

        <button type="submit" class="w-full sm:w-auto px-6 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Bắt đầu luyện ›</button>
    </form>
@endsection
