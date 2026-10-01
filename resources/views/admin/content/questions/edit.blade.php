@extends('layouts.admin')

@section('title', 'Sửa câu hỏi')
@section('page-title', 'Sửa câu hỏi')

@section('content')
    @php
        $types = $types ?? []; $visibilities = $visibilities ?? []; $allTags = $allTags ?? collect(); $subjects = $subjects ?? []; $grades = $grades ?? [];
        $config = $question->grading_config ?? [];
        $options = $config['options'] ?? [];
        $correctOption = ($config['correct_options'][0] ?? null);
        $acceptedAnswers = implode("\n", $config['accepted_answers'] ?? []);
        $caseSensitive = $config['case_sensitive'] ?? false;
        // Bỏ xuống dòng cuối của input/output khi hiển thị để mỗi test nằm đúng 1 dòng "input|||output".
        // Test có input/output nhiều dòng thật thì không thể sửa an toàn ở dạng 1 dòng → khoá ô (không gửi lên),
        // server giữ nguyên test cũ thay vì cắt mất dữ liệu.
        $testCasesList = collect($config['test_cases'] ?? [])->map(fn ($c) => [
            'input' => rtrim((string) ($c['input'] ?? ''), "\r\n"),
            'output' => rtrim((string) ($c['output'] ?? ''), "\r\n"),
        ]);
        $hasMultilineTests = $testCasesList->contains(fn ($c) => str_contains($c['input'], "\n") || str_contains($c['output'], "\n"));
        $testCasesRaw = $testCasesList->map(fn ($c) => $c['input'].'|||'.$c['output'])->implode("\n");
        $timeLimitMs = $config['time_limit_ms'] ?? 1000;
        $memoryLimitMb = $config['memory_limit_mb'] ?? 256;
        // SỬA 1/10 — PHẢI điền sẵn 2 ô Tên tệp vào/ra từ grading_config.file_io. Để trống thì
        // ContentService::resolveFileIo() hiểu là "cố ý bỏ quy ước tên tệp" và XOÁ mất cấu hình
        // của câu nhập từ gói ZIP ngay lần bấm Lưu đầu tiên.
        $fileIoInput = $config['file_io']['input'] ?? '';
        $fileIoOutput = $config['file_io']['output'] ?? '';
        $currentAttachments = $question->metadata['attachments'] ?? [];
        $currentAssets = $question->metadata['assets'] ?? [];
        $attachmentFields = [
            'statement' => ['statement_file', 'Đề bài (PDF)', 'application/pdf', 'Học sinh đọc được ở tab "Đề bài" lúc làm bài. Tối đa 20 MB.'],
            'solution' => ['solution_file', 'Lời giải (PDF)', 'application/pdf', 'Chỉ Admin/Giáo viên tải được — không lộ cho học sinh. Tối đa 20 MB.'],
            'reference' => ['reference_file', 'Code mẫu / lời giải tham khảo', '.cpp,.cc,.c,.py,.pas,.java,.js,.ts,.txt,.md', 'Tệp mã nguồn (.cpp, .py, .pas…), tối đa 2 MB. Chỉ Admin/Giáo viên tải được.'],
        ];
        $fileInputClass = 'w-full text-[13px] text-slate-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:text-[13px]';
        $actionRoute = $hasBeenAttempted ? route('admin.content.questions.newVersion', $question->id) : route('admin.content.questions.update', $question->id);
    @endphp

    <a href="{{ route('admin.content.show', ['content' => $question->id, 'kind' => 'question']) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết</a>

    <x-ws.page-header title="Sửa câu hỏi" icon="pencil" :subtitle="$question->code.' · '.$question->title" />

    @if ($hasBeenAttempted)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 mb-6 text-[13px] text-amber-800 flex items-start gap-2">
            <span class="shrink-0"><x-lucide name="alert-triangle" class="h-4 w-4" /></span>
            <p>Câu hỏi này đã có học sinh làm bài — không thể sửa trực tiếp (6.2). Lưu thay đổi bên dưới sẽ <strong>tạo một phiên bản mới (v{{ $question->version + 1 }})</strong> ở trạng thái Nháp, câu hỏi gốc giữ nguyên để không ảnh hưởng bài đã làm.</p>
        </div>
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div x-data="{ type: '{{ $question->type->value }}' }">
        <form method="POST" action="{{ $actionRoute }}" enctype="multipart/form-data"
              class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @csrf
            @unless ($hasBeenAttempted)
                @method('PUT')
            @endunless

            <div class="lg:col-span-2 bg-white rounded-3xl border border-sky-100 p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @unless ($hasBeenAttempted)
                        <div>
                            <label class="block text-[13px] font-medium text-slate-600 mb-1" for="code">Mã câu hỏi</label>
                            <input id="code" name="code" type="text" value="{{ old('code', $question->code) }}" required maxlength="40"
                                   class="admin-input">
                        </div>
                    @endunless
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1">Loại câu hỏi</label>
                        <p class="text-[13px] text-slate-500 py-2.5 px-1">{{ $types[$question->type->value] ?? $question->type->value }} <span class="text-xs text-slate-400">(không đổi được sau khi tạo)</span></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <x-ws.select id="subject" name="subject">
                            <option value="">— Chưa phân loại —</option>
                            @foreach ($subjects as $code => $label)
                                <option value="{{ $code }}" @selected(old('subject', $question->subject) === $code)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-ws.select id="grade" name="grade">
                            <option value="">— Chưa gán —</option>
                            @foreach ($grades as $g)
                                <option value="{{ $g }}" @selected((string) old('grade', $question->grade) === (string) $g)>Lớp {{ $g }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                </div>

                {{-- SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm nha") —
                     2 ô phân loại mới, ghi vào cột questions.province/exam_year (KHÔNG phải
                     metadata) để bộ lọc ở admin/giáo viên và 2 cột mới ngoài trang Luyện tập
                     chạy nhanh. Xem partial + App\Support\ProvinceCatalog. --}}
                @include('partials.question-province-year', ['province' => $question->province, 'examYear' => $question->exam_year])

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên câu hỏi</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $question->title) }}" required maxlength="255"
                           class="admin-input">
                </div>

                {{-- SỬA 1/10 (khách: "nội dung đề bài bên chỗ cập nhật câu hỏi trong admin cũng
                     ẩn đi nha") — ẩn TẠM, đề bài nhập bằng tệp PDF ở mục "Tệp đính kèm" dưới.
                     ĐÃ SỬA KÈM ở ContentService::questionUpdate()/questionCreateNewVersion():
                     'body' chỉ ghi khi form CÓ gửi ô đó (array_key_exists) — nếu vẫn để
                     `$data['body'] ?? null` như trước thì ẩn ô này đi là mỗi lần bấm Lưu XOÁ
                     SẠCH đề bài cũ của câu hỏi.
                     Bật lại: bỏ dấu mở ghi chú Blade ở đầu khối này và dấu đóng ở cuối khối.

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="body">Nội dung đề bài</label>
                    <textarea id="body" name="body" rows="5" data-rich-editor
                              class="admin-input">{{ old('body', $question->body) }}</textarea>
                </div>
                --}}

                <div x-show="type === 'mcq'" x-cloak>
                    <label class="block text-[13px] font-medium text-slate-600 mb-2">Phương án trả lời</label>
                    <div class="space-y-2">
                        @foreach (['A', 'B', 'C', 'D'] as $i => $opt)
                            <div class="flex items-center gap-2">
                                <input type="radio" name="correct_option" value="{{ $i }}" @checked((string) old('correct_option', $correctOption) === (string) $i)>
                                <input type="text" name="options[]" value="{{ old('options.'.$i, $options[$i] ?? '') }}" maxlength="255"
                                       class="flex-1 rounded-xl border border-sky-100 text-[13px] p-2" placeholder="Phương án {{ $opt }}">
                            </div>
                        @endforeach
                    </div>
                </div>

                <div x-show="type === 'fill_blank'" x-cloak class="space-y-2">
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="accepted_answers">Đáp án được chấp nhận</label>
                    <textarea id="accepted_answers" name="accepted_answers" rows="3"
                              class="admin-input">{{ old('accepted_answers', $acceptedAnswers) }}</textarea>
                    <label class="flex items-center gap-2 text-[13px] text-slate-600">
                        <input type="checkbox" name="case_sensitive" value="1" @checked(old('case_sensitive', $caseSensitive))> Phân biệt hoa/thường
                    </label>
                </div>

                <div x-show="type === 'coding'" x-cloak class="space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-slate-600 mb-1" for="time_limit_ms">Time limit (ms)</label>
                            <input id="time_limit_ms" name="time_limit_ms" type="number" min="1" value="{{ old('time_limit_ms', $timeLimitMs) }}"
                                   class="admin-input">
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-slate-600 mb-1" for="memory_limit_mb">Memory limit (MB)</label>
                            <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="1" value="{{ old('memory_limit_mb', $memoryLimitMb) }}"
                                   class="admin-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="test_cases_raw">Test cases</label>
                        @if ($hasMultilineTests)
                            <p class="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-[12px] text-amber-800">
                                Câu này có test input/output nhiều dòng ({{ $testCasesList->count() }} test) — không sửa được ở dạng 1 dòng nên ô bị khoá, lưu form sẽ giữ nguyên test cũ. Muốn thay test hãy import lại file ZIP.
                            </p>
                            <textarea id="test_cases_raw" rows="6" readonly
                                      class="admin-input font-mono bg-slate-50 text-slate-500">{{ $testCasesRaw }}</textarea>
                        @else
                            <textarea id="test_cases_raw" name="test_cases_raw" rows="8"
                                      class="admin-input font-mono">{{ old('test_cases_raw', $testCasesRaw) }}</textarea>
                            <p class="mt-1 text-[12px] text-slate-500">Mỗi dòng 1 test: <code>input|||output</code> (ví dụ <code>2 3|||5</code>). Dòng thiếu <code>|||</code> sẽ bị báo lỗi, không lưu.</p>
                        @endif
                    </div>
                    {{-- SỬA 1/10 — quy ước tên tệp vào/ra, trước đây chỉ gói ZIP điền được.
                         CodeJudgingService::withFileIo() dùng ĐÚNG 2 giá trị này để chèn đoạn
                         mở/đóng tệp vào mã của học sinh. Giá trị điền sẵn từ grading_config —
                         xoá trắng rồi Lưu nghĩa là bỏ quy ước tên tệp của câu này. --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[13px] font-medium text-slate-600 mb-1" for="file_io_input">Tên tệp dữ liệu vào</label>
                            <input id="file_io_input" name="file_io_input" type="text" maxlength="64"
                                   value="{{ old('file_io_input', $fileIoInput) }}" placeholder="Ví dụ: TONG.INP"
                                   class="admin-input font-mono">
                        </div>
                        <div>
                            <label class="block text-[13px] font-medium text-slate-600 mb-1" for="file_io_output">Tên tệp dữ liệu ra</label>
                            <input id="file_io_output" name="file_io_output" type="text" maxlength="64"
                                   value="{{ old('file_io_output', $fileIoOutput) }}" placeholder="Ví dụ: TONG.OUT"
                                   class="admin-input font-mono">
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">Để trống nếu bài đọc/ghi bằng bàn phím và màn hình (stdin/stdout). Chỉ dùng chữ, số và các dấu <code>.</code> <code>_</code> <code>-</code>.</p>
                </div>

                {{-- SỬA 1/10 (khách: "tiện thể sửa luôn cả cập nhật luôn cho đầy đủ") — khối này
                     thay cho dải "Tệp đính kèm (nhập từ gói ZIP)" chỉ-để-tải cũ ở đầu trang: giờ
                     nằm TRONG form nên thay được tệp, bỏ được tệp, và dùng chung cho cả câu nhập
                     ZIP lẫn câu gõ tay. Xem ContentService::applyManualUploads(). --}}
                <div class="pt-4 border-t border-sky-100 space-y-3">
                    <h4 class="font-medium text-slate-700">📎 Tệp đính kèm</h4>
                    @foreach ($attachmentFields as $kind => [$field, $label, $accept, $hint])
                        <div>
                            <label class="block text-[13px] font-medium text-slate-600 mb-1" for="{{ $field }}">{{ $label }}</label>
                            @if (isset($currentAttachments[$kind]['path']))
                                <div class="flex flex-wrap items-center gap-3 mb-2">
                                    <a href="{{ route('admin.content.questions.attachment', [$question->id, $kind]) }}"
                                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-sky-100 text-xs text-slate-600 hover:border-blue-200 hover:text-blue-600">
                                        ⬇ {{ $currentAttachments[$kind]['filename'] ?? 'Tệp đang có' }}
                                    </a>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-rose-600">
                                        <input type="checkbox" name="remove_attachments[]" value="{{ $kind }}"> Bỏ tệp này
                                    </label>
                                </div>
                            @endif
                            <input id="{{ $field }}" name="{{ $field }}" type="file" accept="{{ $accept }}" class="{{ $fileInputClass }}">
                            <p class="text-xs text-slate-400 mt-1">{{ $hint }} Chọn tệp mới sẽ thay tệp đang có.</p>
                        </div>
                    @endforeach
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="asset_files">Ảnh / âm thanh kèm câu hỏi</label>
                        @if (! empty($currentAssets))
                            <div class="flex flex-wrap items-center gap-3 mb-2">
                                @foreach ($currentAssets as $asset)
                                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-sky-100 text-xs text-slate-600">
                                        <input type="checkbox" name="remove_assets[]" value="{{ $asset['id'] ?? '' }}">
                                        {{ match ($asset['kind'] ?? 'file') { 'image' => '🖼', 'audio' => '🔊', 'video' => '🎬', default => '📄' } }}
                                        {{ $asset['filename'] ?? ($asset['id'] ?? '') }}
                                    </label>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-400 mb-2">Tick tệp muốn bỏ rồi bấm Lưu. Tệp chọn thêm bên dưới được THÊM vào danh sách này, không thay thế.</p>
                        @endif
                        <input id="asset_files" name="asset_files[]" type="file" multiple accept="image/*,audio/*" class="{{ $fileInputClass }}">
                        <p class="text-xs text-slate-400 mt-1">Chọn được nhiều tệp (tối đa 20, mỗi tệp 20 MB) — hiện ảnh / phát audio ngay trong đề lúc làm bài. Cần ghi thêm lời thoại hoặc chú thích ảnh cho từng tệp thì phải nhập bằng gói ZIP.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-sky-100 p-5 h-fit space-y-4">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="target" class="h-4 w-4" /></span> Điểm & hiển thị</h3>
                {{-- SỬA 1/10 — $selected lấy QuestionDifficulty::resolve() chứ không phải
                     stored(): câu CŨ chưa đặt độ khó thì điền sẵn ĐÚNG mức đang hiển thị khắp hệ
                     thống (suy theo điểm), để mở form Sửa rồi bấm Lưu không làm câu đó nhảy sang
                     mức đầu danh sách. LƯU Ý: điểm của câu đó VẪN đổi theo bảng quy đổi mới
                     (vd câu 100 điểm mức "Rất khó" thành 10 điểm) — đúng ý khách "không cho
                     nhập điểm", nhưng là thay đổi thật nên đã báo trước. --}}
                @include('partials.question-difficulty-points', ['selected' => \App\Support\QuestionDifficulty::resolve($question->metadata, (int) $question->points)])
                {{-- SỬA 30/9 (khách: "chưa có thứ tự ưu tiên hiển thị") — SỐ CÀNG LỚN CÀNG HIỆN
                     TRƯỚC trong kho và ngoài trang Luyện tập; 0 = bình thường (xếp theo mới nhất
                     như trước). Xem migration add_display_order_to_questions_table. --}}
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1" for="display_order">Thứ tự ưu tiên hiển thị</label>
                    <input id="display_order" name="display_order" type="number" min="0" max="65535" value="{{ old('display_order', $question->display_order) }}" class="admin-input">
                    <p class="text-xs text-slate-400 mt-1">Số càng lớn càng hiện trước. Để 0 nếu không cần ưu tiên.</p>
                </div>
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1" for="visibility">Hiển thị</label>
                    <x-ws.select id="visibility" name="visibility" required>
                        @foreach ($visibilities as $value => $label)
                            <option value="{{ $value }}" @selected(old('visibility', $question->visibility->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1">Tag/Chuyên đề</label>
                    @if ($allTags->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach ($allTags as $tagOption)
                                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-sky-100 text-xs text-slate-600 has-[:checked]:bg-blue-50 has-[:checked]:border-blue-300 has-[:checked]:text-blue-600">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tagOption->id }}"
                                           @checked(collect(old('tag_ids', $question->tags->pluck('id')->all()))->contains((string) $tagOption->id))>
                                    {{ $tagOption->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input type="text" name="new_tags" value="{{ old('new_tags') }}" maxlength="500" placeholder="Tag mới, cách nhau bằng dấu phẩy"
                           class="admin-input">
                </div>
                <button type="submit" class="w-full inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">
                    {{ $hasBeenAttempted ? 'Tạo phiên bản mới' : 'Lưu thay đổi' }}
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
