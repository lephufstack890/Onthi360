{{--
  Route: admin.content.questions.edit / .update / .newVersion
  Spec: 6.2 — "Câu hỏi đã có người làm — sửa nội dung phải tạo phiên bản mới, không sửa âm
  thầm". $hasBeenAttempted do ContentService::questionEditFormData() tính qua
  QuestionPublishGuard::hasBeenAttempted() — quyết định form nào hiện ra ở đây.
--}}
@extends('layouts.admin')

@section('title', 'Sửa câu hỏi')
@section('page-title', 'Sửa câu hỏi')

@section('content')
    @php
        $types = $types ?? []; $visibilities = $visibilities ?? []; $allTags = $allTags ?? collect();
        $config = $question->grading_config ?? [];
        $options = $config['options'] ?? [];
        $correctOption = ($config['correct_options'][0] ?? null);
        $acceptedAnswers = implode("\n", $config['accepted_answers'] ?? []);
        $caseSensitive = $config['case_sensitive'] ?? false;
        $testCasesRaw = collect($config['test_cases'] ?? [])->map(fn ($c) => ($c['input'] ?? '').'|||'.($c['output'] ?? ''))->implode("\n");
        $timeLimitMs = $config['time_limit_ms'] ?? 1000;
        $memoryLimitMb = $config['memory_limit_mb'] ?? 256;
        $actionRoute = $hasBeenAttempted ? route('admin.content.questions.newVersion', $question->id) : route('admin.content.questions.update', $question->id);
    @endphp

    <a href="{{ route('admin.content.show', $question->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="✏️ Sửa câu hỏi" :subtitle="$question->code.' · '.$question->title" />

    @if ($hasBeenAttempted)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 mb-6 text-sm text-amber-800 flex items-start gap-2">
            <span class="shrink-0">⚠️</span>
            <p>Câu hỏi này đã có học sinh làm bài — không thể sửa trực tiếp (6.2). Lưu thay đổi bên dưới sẽ <strong>tạo một phiên bản mới (v{{ $question->version + 1 }})</strong> ở trạng thái Nháp, câu hỏi gốc giữ nguyên để không ảnh hưởng bài đã làm.</p>
        </div>
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div x-data="{ type: '{{ $question->type->value }}' }">
        <form method="POST" action="{{ $actionRoute }}" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @csrf
            @unless ($hasBeenAttempted)
                @method('PUT')
            @endunless

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @unless ($hasBeenAttempted)
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã câu hỏi</label>
                            <input id="code" name="code" type="text" value="{{ old('code', $question->code) }}" required maxlength="40"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                        </div>
                    @endunless
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1">Loại câu hỏi</label>
                        <p class="text-sm text-slate-500 py-2.5 px-1">{{ $types[$question->type->value] ?? $question->type->value }} <span class="text-xs text-slate-400">(không đổi được sau khi tạo)</span></p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên câu hỏi</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $question->title) }}" required maxlength="255"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="body">Nội dung đề bài</label>
                    <textarea id="body" name="body" rows="5" data-rich-editor
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('body', $question->body) }}</textarea>
                </div>

                <div x-show="type === 'mcq'" x-cloak>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Phương án trả lời</label>
                    <div class="space-y-2">
                        @foreach (['A', 'B', 'C', 'D'] as $i => $opt)
                            <div class="flex items-center gap-2">
                                <input type="radio" name="correct_option" value="{{ $i }}" @checked((string) old('correct_option', $correctOption) === (string) $i)>
                                <input type="text" name="options[]" value="{{ old('options.'.$i, $options[$i] ?? '') }}" maxlength="255"
                                       class="flex-1 rounded-lg border border-slate-200 text-sm p-2" placeholder="Phương án {{ $opt }}">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div x-show="type === 'fill_blank'" x-cloak class="space-y-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="accepted_answers">Đáp án được chấp nhận</label>
                    <textarea id="accepted_answers" name="accepted_answers" rows="3"
                              class="w-full rounded-lg border border-slate-200 text-sm p-2">{{ old('accepted_answers', $acceptedAnswers) }}</textarea>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="case_sensitive" value="1" @checked(old('case_sensitive', $caseSensitive))> Phân biệt hoa/thường
                    </label>
                </div>

                <div x-show="type === 'coding'" x-cloak class="space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="time_limit_ms">Time limit (ms)</label>
                            <input id="time_limit_ms" name="time_limit_ms" type="number" min="1" value="{{ old('time_limit_ms', $timeLimitMs) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="memory_limit_mb">Memory limit (MB)</label>
                            <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="1" value="{{ old('memory_limit_mb', $memoryLimitMb) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="test_cases_raw">Test cases</label>
                        <textarea id="test_cases_raw" name="test_cases_raw" rows="4"
                                  class="w-full rounded-lg border border-slate-200 text-sm p-2 font-mono">{{ old('test_cases_raw', $testCasesRaw) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 h-fit space-y-4">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>🎯</span> Điểm & hiển thị</h3>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="points">Điểm</label>
                    <input id="points" name="points" type="number" min="0" value="{{ old('points', $question->points) }}" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="visibility">Hiển thị</label>
                    <x-select id="visibility" name="visibility" required>
                        @foreach ($visibilities as $value => $label)
                            <option value="{{ $value }}" @selected(old('visibility', $question->visibility->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                {{-- SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): tick tag có sẵn
                     hoặc gõ tag mới ngay ở đây (cách nhau bằng dấu phẩy) — xem
                     ContentService::resolveTagIds(). Tag hiện tại của câu hỏi lấy từ
                     $question->tags (đã eager-load ở ContentService::questionEditFormData()). --}}
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Tag/Chuyên đề</label>
                    @if ($allTags->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach ($allTags as $tagOption)
                                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-slate-200 text-xs text-slate-600 has-[:checked]:bg-rose-50 has-[:checked]:border-rose-300 has-[:checked]:text-rose-600">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tagOption->id }}"
                                           @checked(collect(old('tag_ids', $question->tags->pluck('id')->all()))->contains((string) $tagOption->id))>
                                    {{ $tagOption->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input type="text" name="new_tags" value="{{ old('new_tags') }}" maxlength="500" placeholder="Tag mới, cách nhau bằng dấu phẩy"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">
                    {{ $hasBeenAttempted ? 'Tạo phiên bản mới' : 'Lưu thay đổi' }}
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
