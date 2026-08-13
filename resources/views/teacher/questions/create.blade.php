{{--
  Route: teacher.questions.create / .store / .edit / .update
  Spec: 6.1 (3 loại câu) + 6.2 (điều kiện phát hành — chặn nếu thiếu
  test/đáp án/điểm/cấu hình OJ) + 6.5 (kho riêng giáo viên).
  Dữ liệu thật ($type, $question) do App\Http\Controllers\Teacher\QuestionController
  truyền vào. $question null khi tạo mới, khác null khi sửa (form dùng chung).
--}}
@extends('layouts.teacher')

@section('title', $question ? 'Sửa câu hỏi' : 'Tạo câu hỏi')
@section('page-title', $question ? 'Sửa câu hỏi' : 'Tạo câu hỏi')

@section('content')
    @php
        $question = $question ?? null;
        $type = old('type', $question->type->value ?? ($type ?? request('type', 'mcq')));
        $types = [
            ['key' => 'mcq', 'label' => 'Trắc nghiệm', 'icon' => '🔤'],
            ['key' => 'fill_blank', 'label' => 'Điền đáp án', 'icon' => '✏️'],
            ['key' => 'coding', 'label' => 'Lập trình', 'icon' => '💻'],
        ];
        $config = $question->grading_config ?? [];
        $options = old('options', $config['options'] ?? ['', '', '', '']);
        $correctOption = old('correct_option', $config['correct_options'][0] ?? null);
        $acceptedAnswers = old('accepted_answers', implode(', ', $config['accepted_answers'] ?? []));
        $caseSensitive = old('case_sensitive', $config['case_sensitive'] ?? false);
        $timeLimitMs = old('time_limit_ms', $config['time_limit_ms'] ?? 1000);
        $memoryLimitMb = old('memory_limit_mb', $config['memory_limit_mb'] ?? 256);
        $testCasesText = old('test_cases', collect($config['test_cases'] ?? [])->map(fn ($tc) => ($tc['input'] ?? '').' => '.($tc['output'] ?? ''))->implode("\n"));
        $canPublishNow = $question ? app(\App\Services\QuestionPublishGuard::class)->canPublish($question)->allowed : false;
    @endphp

    <a href="{{ route('teacher.questions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Kho câu hỏi</a>

    <x-page-header :title="$question ? 'Sửa câu hỏi' : 'Tạo câu hỏi'" subtitle="Chọn loại câu hỏi phù hợp — mỗi loại có cấu hình phát hành riêng (6.2)." />

    @if (session('status') === 'question-created-draft-only' || session('status') === 'question-updated-draft-only')
        @include('partials.toast-flash', ['type' => 'warning', 'message' => 'Đã lưu nháp — chưa đủ điều kiện phát hành, xem lỗi bên dưới.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @if ($question && $question->version > 1)
        <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700 mb-4">
            Đây là phiên bản v{{ $question->version }} — được tạo tự động vì câu gốc đã có người làm (6.2: không sửa âm thầm làm thay đổi kết quả cũ).
        </div>
    @endif

    <div class="flex gap-3 mb-6">
        @foreach ($types as $t)
            <a href="{{ $question ? '#' : route('teacher.questions.create', ['type' => $t['key']]) }}"
               class="flex-1 flex items-center gap-2 justify-center px-4 py-3 rounded-xl text-sm font-medium border transition {{ $type === $t['key'] ? 'bg-rose-600 border-rose-600 text-white shadow-sm' : 'border-slate-200 text-slate-600 hover:border-rose-200' }} {{ $question ? 'pointer-events-none opacity-60' : '' }}">
                <span class="text-base">{{ $t['icon'] }}</span> {{ $t['label'] }}
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ $question ? route('teacher.questions.update', $question->id) : route('teacher.questions.store') }}">
        @csrf
        @if ($question)
            @method('PUT')
        @endif
        <input type="hidden" name="type" value="{{ $type }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên câu hỏi</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $question->title ?? '') }}" required maxlength="255"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="VD: Bài 14 - Quy hoạch động cơ bản">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="body">Nội dung đề bài</label>
                    <textarea id="body" name="body" rows="5" data-rich-editor class="w-full rounded-lg border border-slate-200 text-sm p-3" placeholder="Nhập đề bài...">{{ old('body', $question->body ?? '') }}</textarea>
                </div>

                @if ($type === 'mcq')
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-2">Phương án trả lời</label>
                        <div class="space-y-2">
                            @foreach (['A', 'B', 'C', 'D'] as $i => $opt)
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="correct_option" value="{{ $opt }}" @checked($correctOption === $opt)>
                                    <input type="text" name="options[]" value="{{ $options[$i] ?? '' }}" class="flex-1 rounded-lg border border-slate-200 text-sm p-2" placeholder="Phương án {{ $opt }}">
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-400 mt-2">Chọn radio ở phương án đúng. Chưa chọn = chặn phát hành (6.2).</p>
                    </div>
                @elseif ($type === 'fill_blank')
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="accepted_answers">Đáp án đúng (cách nhau bằng dấu phẩy nếu có nhiều đáp án chấp nhận)</label>
                        <input id="accepted_answers" name="accepted_answers" type="text" value="{{ $acceptedAnswers }}" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="case_sensitive" value="1" @checked($caseSensitive)> Phân biệt hoa/thường
                    </label>
                @else
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="time_limit_ms">Time limit (ms)</label>
                            <input id="time_limit_ms" name="time_limit_ms" type="number" value="{{ $timeLimitMs }}" min="100" max="60000" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="memory_limit_mb">Memory limit (MB)</label>
                            <input id="memory_limit_mb" name="memory_limit_mb" type="number" value="{{ $memoryLimitMb }}" min="16" max="2048" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="test_cases">Test cases — mỗi dòng "input => output"</label>
                        <textarea id="test_cases" name="test_cases" rows="4" class="w-full rounded-lg border border-slate-200 text-sm p-3 font-mono" placeholder="1 2 => 3&#10;5 5 => 10">{{ $testCasesText }}</textarea>
                        <p class="text-xs text-slate-400 mt-1">Thiếu test/giới hạn thời gian-bộ nhớ = chặn phát hành (6.2).</p>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🎯</span> Điểm & trạng thái</h3>
                <div class="mb-4">
                    <label class="block text-sm text-slate-600 mb-1" for="points">Điểm</label>
                    <input id="points" name="points" type="number" value="{{ old('points', $question->points ?? 10) }}" min="1" max="100" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>

                @if ($question && $question->status->value === 'published')
                    <div class="rounded-lg bg-emerald-50 border border-emerald-100 p-3 text-xs text-emerald-700 mb-4">✓ Đã phát hành.</div>
                @elseif ($question && ! $canPublishNow)
                    <div class="rounded-lg bg-amber-50 border border-amber-100 p-3 text-xs text-amber-700 mb-4 flex items-start gap-2">
                        <span>⚠️</span><span>Chưa đủ điều kiện phát hành — kiểm tra lại cấu hình chấm/đáp án.</span>
                    </div>
                @endif

                <button type="submit" name="action" value="draft" class="w-full px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium mb-2">Lưu nháp</button>
                <button type="submit" name="action" value="publish" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu & Phát hành</button>
            </div>
        </div>
    </form>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
