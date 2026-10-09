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
        // SỬA 9/10 — bộ test hiện có đưa nguyên vẹn (kể cả input/output nhiều dòng) cho khối "Bộ test"
        // (partial _test-set). Không còn ô textarea "input|||output" nên cũng hết cảnh phải khoá ô
        // khi test nhiều dòng; form chỉ gửi test_cases_json khi người dùng THẬT SỰ đổi bộ test.
        $qeExistingTests = collect($config['test_cases'] ?? [])->map(fn ($c) => [
            'input' => (string) ($c['input'] ?? ''),
            'output' => (string) ($c['output'] ?? ''),
        ])->values()->all();
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

    {{-- SỬA 9/10 (khách: "check UI khi tạo câu hỏi và cập nhật admin… giống UI source mới") — dựng lại
         BỐ CỤC như màn Tạo (banner + lưới 2 cột). TÊN Ô / LUẬT / LOGIC LƯU giữ NGUYÊN; chỉ thay ô
         textarea "input|||output" bằng khối "Bộ test" (ô ẩn test_cases_json). --}}
    @include('admin.content.questions._editor-style')

    <div class="qe">
        <a href="{{ route('admin.content.show', ['content' => $question->id, 'kind' => 'question']) }}" class="qe-back">‹ Quay lại chi tiết</a>

        <div class="qe-hero-gap">
            <x-ws.page-header title="Sửa câu hỏi" icon="pencil" eyebrow="Kho chung · Câu hỏi" :subtitle="$question->code.' · '.$question->title" />
        </div>

        @if ($hasBeenAttempted)
            <div class="qe-warn qe-hero-gap">
                <span class="shrink-0"><x-lucide name="alert-triangle" class="h-4 w-4" /></span>
                <p>Câu hỏi này đã có học sinh làm bài — không thể sửa trực tiếp (6.2). Lưu thay đổi bên dưới sẽ <strong>tạo một phiên bản mới (v{{ $question->version + 1 }})</strong> ở trạng thái Nháp, câu hỏi gốc giữ nguyên để không ảnh hưởng bài đã làm.</p>
            </div>
        @endif

        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div x-data="{ type: '{{ $question->type->value }}' }">
            <form method="POST" action="{{ $actionRoute }}" enctype="multipart/form-data" id="qe-form"
                  class="qe-layout">
                @csrf
                {{-- SỬA 4/10 — địa chỉ danh sách để quay về sau khi Lưu (giữ nguyên tab + bộ lọc).
                     old() để lần gửi hỏng không làm mất địa chỉ đã ghi nhớ: lúc đó trang Sửa được vẽ
                     lại và url()->previous() đã trỏ vào chính nó. --}}
                <input type="hidden" name="return_to" value="{{ old('return_to', $returnTo ?? '') }}">
                @unless ($hasBeenAttempted)
                    @method('PUT')
                @endunless

                <div class="qe-main">
                    <section class="qe-panel">
                        <h2 class="qe-h"><x-lucide name="circle-help" class="h-4 w-4" /> Thông tin bài</h2>
                        <div class="qe-stack">
                            <div class="qe-grid2">
                                @unless ($hasBeenAttempted)
                                    <div class="qe-field">
                                        <label for="code">Mã câu hỏi</label>
                                        <input id="code" name="code" type="text" value="{{ old('code', $question->code) }}" required maxlength="40"
                                               class="admin-input">
                                    </div>
                                @endunless
                                <div class="qe-field">
                                    <label>Loại câu hỏi</label>
                                    <p class="qe-readonly">{{ $types[$question->type->value] ?? $question->type->value }} <small>(không đổi được sau khi tạo)</small></p>
                                </div>
                            </div>

                            <div class="qe-field">
                                <label for="title">Tên câu hỏi</label>
                                <input id="title" name="title" type="text" value="{{ old('title', $question->title) }}" required maxlength="255"
                                       class="admin-input">
                            </div>

                            <div class="qe-grid2">
                                <div class="qe-field">
                                    <label for="subject">Môn học</label>
                                    <x-ws.select id="subject" name="subject">
                                        <option value="">— Chưa phân loại —</option>
                                        @foreach ($subjects as $code => $label)
                                            <option value="{{ $code }}" @selected(old('subject', $question->subject) === $code)>{{ $label }}</option>
                                        @endforeach
                                    </x-ws.select>
                                </div>
                                <div>
                                    @include('partials.question-grade-field', ['question' => $question ?? null])
                                </div>
                            </div>

                            {{-- SỬA 1/10 (khách: "thêm 1 cái field nữa cho chọn tỉnh thành và năm nha") —
                                 2 ô phân loại mới, ghi vào cột questions.province/exam_year (KHÔNG phải
                                 metadata) để bộ lọc ở admin/giáo viên và 2 cột mới ngoài trang Luyện tập
                                 chạy nhanh. Xem partial + App\Support\ProvinceCatalog. --}}
                            @include('partials.question-province-year', ['province' => $question->province, 'examYear' => $question->exam_year])

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
                        </div>
                    </section>

                    <section class="qe-panel" x-show="type === 'mcq'" x-cloak>
                        <h2 class="qe-h"><x-lucide name="list-checks" class="h-4 w-4" /> Phương án trả lời</h2>
                        <div class="qe-opts">
                            @foreach (['A', 'B', 'C', 'D'] as $i => $opt)
                                <label class="qe-opt">
                                    <input type="radio" name="correct_option" value="{{ $i }}" @checked((string) old('correct_option', $correctOption) === (string) $i) aria-label="Phương án {{ $opt }} là đáp án đúng">
                                    <b>{{ $opt }}</b>
                                    <input type="text" name="options[]" value="{{ old('options.'.$i, $options[$i] ?? '') }}" maxlength="255" placeholder="Phương án {{ $opt }}">
                                </label>
                            @endforeach
                        </div>
                        <p class="qe-note" style="margin-top:10px;">Chọn nút tròn ở phương án đúng.</p>
                    </section>

                    <section class="qe-panel" x-show="type === 'fill_blank'" x-cloak>
                        <h2 class="qe-h"><x-lucide name="text-cursor-input" class="h-4 w-4" /> Đáp án điền khuyết</h2>
                        <div class="qe-stack" style="gap:10px;">
                            <div class="qe-field">
                                <label for="accepted_answers">Đáp án được chấp nhận</label>
                                <textarea id="accepted_answers" name="accepted_answers" rows="3"
                                          class="admin-input">{{ old('accepted_answers', $acceptedAnswers) }}</textarea>
                            </div>
                            <label class="flex items-center gap-2 text-[13px] text-slate-600">
                                <input type="checkbox" name="case_sensitive" value="1" @checked(old('case_sensitive', $caseSensitive))> Phân biệt hoa/thường
                            </label>
                        </div>
                    </section>

                    <div class="qe-main" x-show="type === 'coding'" x-cloak>
                        <section class="qe-panel">
                            <h2 class="qe-h"><x-lucide name="code" class="h-4 w-4" /> Cấu hình chấm bài</h2>
                            <div class="qe-stack">
                                <div class="qe-grid2">
                                    <div class="qe-field">
                                        <label for="time_limit_ms">Time limit (ms)</label>
                                        <input id="time_limit_ms" name="time_limit_ms" type="number" min="1" value="{{ old('time_limit_ms', $timeLimitMs) }}"
                                               class="admin-input">
                                    </div>
                                    <div class="qe-field">
                                        <label for="memory_limit_mb">Memory limit (MB)</label>
                                        <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="1" value="{{ old('memory_limit_mb', $memoryLimitMb) }}"
                                               class="admin-input">
                                    </div>
                                </div>
                                {{-- SỬA 9/10 (khách: đề kiểu HELLOWORLD bắt đọc HELLOWORLD.INP / ghi HELLOWORLD.OUT, thiếu freopen thì chấm sai) —
                                     "Tệp có tên" = học sinh BẮT BUỘC đọc/ghi qua đúng 2 tệp này (CodeJudgingService::withFileIo()), tên tệp
                                     tự lấy theo mã câu hỏi (<MÃ>.INP / <MÃ>.OUT). "Bàn phím / màn hình" = xoá trắng 2 ô, đề đọc/ghi
                                     stdin/stdout như bình thường. --}}
                                <script>
                                    {{-- SỬA 9/10 — ô "Đọc / ghi dữ liệu": chọn "Tệp có tên" thì tên tệp vào/ra TỰ lấy theo mã câu hỏi
                                         (<MÃ>.INP / <MÃ>.OUT) và tự đổi theo khi gõ lại mã, trừ khi người ra đề đã tự sửa tay. --}}
                                    window.qeFileIo = window.qeFileIo || function (cfg) {
                                        return {
                                            ioMode: cfg.mode,
                                            auto: true,
                                            codeId: cfg.codeId || null,
                                            code() {
                                                var el = this.codeId ? document.getElementById(this.codeId) : null;
                                                var c = el ? el.value : (cfg.code || '');
                                                return String(c || '').trim().replace(/[^A-Za-z0-9._-]+/g, '_').slice(0, 59);
                                            },
                                            init() {
                                                var i = this.$refs.fioIn.value.trim(), o = this.$refs.fioOut.value.trim(), c = this.code();
                                                this.auto = (i === '' && o === '') || (c !== '' && i === c + '.INP' && o === c + '.OUT');
                                                if (this.ioMode === 'file') this.fill();
                                            },
                                            fill() {
                                                var c = this.code();
                                                if (!this.auto || c === '') return;
                                                this.$refs.fioIn.value = c + '.INP';
                                                this.$refs.fioOut.value = c + '.OUT';
                                            },
                                            pick() {
                                                if (this.ioMode === 'std') {
                                                    this.$refs.fioIn.value = '';
                                                    this.$refs.fioOut.value = '';
                                                    this.auto = true;
                                                    return;
                                                }
                                                if (this.$refs.fioIn.value.trim() === '' && this.$refs.fioOut.value.trim() === '') this.auto = true;
                                                this.fill();
                                            },
                                        };
                                    };
                                </script>
<div class="qe-stack" style="gap:10px;"
                                     x-data="qeFileIo({ mode: {{ (trim((string) old('file_io_input', $fileIoInput)) !== '' || trim((string) old('file_io_output', $fileIoOutput)) !== '') ? "'file'" : "'std'" }}, codeId: 'code' })"
                                     x-on:input.window="if ($event.target && $event.target.id === codeId) fill()">
                                    <div class="qe-field">
                                        <label for="io_mode">Đọc / ghi dữ liệu</label>
                                        <x-ws.select id="io_mode" x-model="ioMode" x-on:change="pick()">
                                            <option value="std">Bàn phím / màn hình</option>
                                            <option value="file">Tệp có tên</option>
                                        </x-ws.select>
                                    </div>
                                    <div class="qe-grid2" x-show="ioMode === 'file'" x-cloak>
                                        <div class="qe-field">
                                            <label for="file_io_input">Tên tệp vào</label>
                                            <input id="file_io_input" name="file_io_input" type="text" maxlength="64" x-ref="fioIn" x-on:input="auto = false"
                                                   value="{{ old('file_io_input', $fileIoInput) }}" placeholder="Tự lấy theo mã câu hỏi: MÃ.INP"
                                                   x-bind:required="ioMode === 'file' && type === 'coding'" class="admin-input font-mono">
                                        </div>
                                        <div class="qe-field">
                                            <label for="file_io_output">Tên tệp ra</label>
                                            <input id="file_io_output" name="file_io_output" type="text" maxlength="64" x-ref="fioOut" x-on:input="auto = false"
                                                   value="{{ old('file_io_output', $fileIoOutput) }}" placeholder="Tự lấy theo mã câu hỏi: MÃ.OUT"
                                                   x-bind:required="ioMode === 'file' && type === 'coding'" class="admin-input font-mono">
                                        </div>
                                    </div>
                                    <p class="qe-note" x-show="ioMode === 'std'">Học sinh đọc bằng bàn phím và in ra màn hình (stdin/stdout), như bình thường.</p>
                                    <p class="qe-note" x-show="ioMode === 'file'" x-cloak>Tên tệp tự lấy theo mã câu hỏi, có thể sửa tay. Học sinh <strong>bắt buộc</strong> đọc/ghi bằng đúng 2 tệp này (C++: <code>freopen</code> hoặc <code>ifstream/ofstream</code>; Python: <code>open</code>). Chỉ dùng cin/cout hoặc input/print thì bị chấm sai. Chỉ dùng chữ, số và các dấu <code>.</code> <code>_</code> <code>-</code>.</p>
                                </div>
                            </div>
                        </section>

                        <section class="qe-panel">
                            <h2 class="qe-h"><x-lucide name="database" class="h-4 w-4" /> Bộ test</h2>
                            @include('admin.content.questions._test-set', ['qeMode' => 'edit', 'qeInitialTests' => $qeExistingTests])
                            @error('test_cases_json')<p class="mt-2 text-[12px] text-rose-600">{{ $message }}</p>@enderror
                            @error('test_cases_raw')<p class="mt-2 text-[12px] text-rose-600">{{ $message }}</p>@enderror
                        </section>
                    </div>

                    {{-- SỬA 1/10 (khách: "tiện thể sửa luôn cả cập nhật luôn cho đầy đủ") — khối này
                         thay cho dải "Tệp đính kèm (nhập từ gói ZIP)" chỉ-để-tải cũ ở đầu trang: giờ
                         nằm TRONG form nên thay được tệp, bỏ được tệp, và dùng chung cho cả câu nhập
                         ZIP lẫn câu gõ tay. Xem ContentService::applyManualUploads(). --}}
                    <section class="qe-panel">
                        <h2 class="qe-h"><x-lucide name="paperclip" class="h-4 w-4" /> Tệp đính kèm</h2>
                        @php
                            // SỬA 4/10 — xem ghi chú ở ô "Bỏ tệp này" bên dưới.
                            $ftaRemoveSubmitted = old('remove_attachments_submitted') !== null;
                        @endphp
                        <input type="hidden" name="remove_attachments_submitted" value="1">
                        <div class="qe-stack">
                            @foreach ($attachmentFields as $kind => [$field, $label, $accept, $hint])
                                <div class="qe-field">
                                    <label for="{{ $field }}">{{ $label }}</label>
                                    @if (isset($currentAttachments[$kind]['path']))
                                        <div class="qe-cur" style="margin-bottom:8px;">
                                            <a href="{{ route('admin.content.questions.attachment', [$question->id, $kind]) }}">
                                                ⬇ {{ $currentAttachments[$kind]['filename'] ?? 'Tệp đang có' }}
                                            </a>
                                            {{-- SỬA 9/10 (khách: "bỏ luôn mặc định tick bỏ tệp này khi cập nhật câu hỏi") — KHÔNG còn
     tích sẵn: mở câu hỏi ra sửa rồi Lưu thì tệp đang có được GIỮ NGUYÊN. Chỉ khi người dùng tự
     tích "Bỏ tệp này" thì tệp mới bị xoá. (Trước đây 4/10 từng tích sẵn theo yêu cầu cũ.)
     old() giữ lại lựa chọn nếu lần gửi trước bị báo lỗi. --}}
                                            <label class="inline-flex items-center gap-1.5 text-xs text-rose-600">
                                                <input type="checkbox" name="remove_attachments[]" value="{{ $kind }}"
                                                       @checked(in_array($kind, (array) old('remove_attachments', []), true))> Bỏ tệp này
                                            </label>
                                        </div>
                                    @endif
                                    <input id="{{ $field }}" name="{{ $field }}" type="file" accept="{{ $accept }}" class="{{ $fileInputClass }}">
                                    <small>{{ $hint }} Chọn tệp mới sẽ thay tệp đang có.</small>
                                </div>
                            @endforeach
                            <div class="qe-field">
                                <label for="asset_files">Ảnh / âm thanh kèm câu hỏi</label>
                                @if (! empty($currentAssets))
                                    <div class="qe-cur" style="margin-bottom:8px;">
                                        @foreach ($currentAssets as $asset)
                                            <label class="qe-chip">
                                                <input type="checkbox" name="remove_assets[]" value="{{ $asset['id'] ?? '' }}">
                                                {{ match ($asset['kind'] ?? 'file') { 'image' => '🖼', 'audio' => '🔊', 'video' => '🎬', default => '📄' } }}
                                                {{ $asset['filename'] ?? ($asset['id'] ?? '') }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="qe-note" style="margin-bottom:8px;">Tick tệp muốn bỏ rồi bấm Lưu. Tệp chọn thêm bên dưới được THÊM vào danh sách này, không thay thế.</p>
                                @endif
                                <input id="asset_files" name="asset_files[]" type="file" multiple accept="image/*,audio/*" class="{{ $fileInputClass }}">
                                <small>Chọn được nhiều tệp (tối đa 20, mỗi tệp 20 MB) — hiện ảnh / phát audio ngay trong đề lúc làm bài. Cần ghi thêm lời thoại hoặc chú thích ảnh cho từng tệp thì phải nhập bằng gói ZIP.</small>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="qe-aside">
                    <div class="qe-aside-body">
                        <h3 class="qe-h"><x-lucide name="target" class="h-4 w-4" /> Điểm &amp; hiển thị</h3>
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
                        <div class="qe-field">
                            <label for="display_order">Thứ tự ưu tiên hiển thị</label>
                            <input id="display_order" name="display_order" type="number" min="0" max="65535" value="{{ old('display_order', $question->display_order) }}" class="admin-input">
                            <small>Số càng lớn càng hiện trước. Để 0 nếu không cần ưu tiên.</small>
                        </div>
                        <div class="qe-field">
                            <label for="visibility">Hiển thị</label>
                            <x-ws.select id="visibility" name="visibility" required>
                                @foreach ($visibilities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('visibility', $question->visibility->value) === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ws.select>
                        </div>
                        <div class="qe-field">
                            <label>Tag/Chuyên đề</label>
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
                    </div>
                    <div class="qe-aside-actions">
                        <button type="submit" class="qe-btn qe-btn--primary" style="width:100%;min-height:42px;">
                            <x-lucide name="save" class="h-4 w-4" /> {{ $hasBeenAttempted ? 'Tạo phiên bản mới' : 'Lưu thay đổi' }}
                        </button>
                        <small>Ctrl/⌘ + S để lưu nhanh</small>
                    </div>
                </aside>
            </form>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
        <script>
            document.addEventListener('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && String(e.key).toLowerCase() === 's') {
                    var form = document.getElementById('qe-form');
                    if (form) { e.preventDefault(); form.requestSubmit(); }
                }
            });
        </script>
    @endpush
@endsection
