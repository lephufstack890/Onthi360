@extends('layouts.admin')

@section('title', 'Tạo câu hỏi')
@section('page-title', 'Tạo câu hỏi (Kho chung)')

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $allTags = $allTags ?? collect(); $subjects = $subjects ?? []; $grades = $grades ?? []; @endphp

    {{-- SỬA 9/10 (khách: "check UI khi tạo câu hỏi… giống UI source mới") — dựng lại BỐ CỤC theo
         CompactContentEditor của source mới: banner, ô nhập gói ZIP viền đứt, lưới 2 cột (nội dung
         chính + cột "Điểm & hiển thị"). TÊN Ô, LUẬT KIỂM TRA, LOGIC LƯU giữ NGUYÊN như cũ; chỉ THÊM
         khối "Bộ test" (ô ẩn test_cases_json) thay cho ô textarea "input|||output". --}}
    @include('admin.content.questions._editor-style')

    <div class="qe">
        <a href="{{ route('admin.content.index', ['tab' => 'questions']) }}" class="qe-back">‹ Quay lại Nội dung</a>

        <div class="qe-hero-gap">
            <x-ws.page-header title="Thêm câu hỏi" icon="circle-help" eyebrow="Kho chung · Câu hỏi" subtitle="Câu hỏi tạo ở đây thuộc Kho chung — Editor/Admin/Super Admin quản lý (6.5)." />
        </div>

        @if ($errors->any())
            @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
        @endif

        <div x-data="{ type: '{{ old('type', 'mcq') }}' }">
            <div x-show="['coding', 'mcq', 'fill_blank'].includes(type)" x-cloak class="qe-hero-gap">
                <form method="POST" action="{{ route('admin.content.questions.zipImport') }}" enctype="multipart/form-data"
                      x-data="{ submitting: false, drag: false }"
                      class="qe-zip" :class="drag && 'is-drag'"
                      @dragover.prevent="drag = true" @dragleave.prevent="drag = false"
                      @drop.prevent="drag = false; if ($event.dataTransfer.files.length) { $refs.zip.files = $event.dataTransfer.files; submitting = true; $el.requestSubmit(); }">
                    @csrf
                    <span class="qe-zip-ico"><x-lucide name="package" class="h-5 w-5" /></span>
                    <div class="qe-zip-body">
                        <strong>
                            <span x-show="type === 'coding'">Nhập câu hỏi lập trình từ gói ZIP</span>
                            <span x-show="type === 'mcq'" x-cloak>Nhập câu trắc nghiệm từ gói ZIP</span>
                            <span x-show="type === 'fill_blank'" x-cloak>Nhập câu điền khuyết từ gói ZIP</span>
                        </strong>
                        <p><strong style="display:inline;font-size:12px;color:#526b87;">Chọn tệp xong hệ thống tự nhập ngay</strong>, không cần bấm nút — xong sẽ chuyển sang trang Sửa để kiểm tra và Lưu. Tối đa {{ number_format(\App\Services\Admin\ContentService::maxQuestionZipKb() / 1024) }} MB.</p>
                        <details>
                            <summary>Định dạng gói OT360-QPACK</summary>
                            <p>
                                Bắt buộc có <code>question.json</code> ở gốc gói, kèm <code>statement.pdf</code> (đề) và <code>solution.pdf</code> (lời giải) nếu có.
                                <span x-show="type === 'coding'"><code>content.type</code> = <strong>"programming"</strong>, kèm thư mục <code>tests/1/</code>, <code>tests/2/</code>… (mỗi thư mục 2 tệp input/output).</span>
                                <span x-show="type === 'mcq'" x-cloak><code>content.type</code> = <strong>"single_choice"</strong> (grading.choices + grading.correct_answer) hoặc <strong>"true_false"</strong> (grading.correct_answer là true/false) — không cần thư mục <code>tests/</code>.</span>
                                <span x-show="type === 'fill_blank'" x-cloak><code>content.type</code> = <strong>"short_answer"</strong> (grading.accepted_answers + grading.normalization) — không cần thư mục <code>tests/</code>.</span>
                                Loại câu hỏi lấy theo <code>content.type</code> trong gói, không phụ thuộc ô "Loại câu hỏi" đang chọn.
                            </p>
                        </details>
                    </div>
                    <input id="zip_package" name="zip_package" type="file" accept=".zip,application/zip" required x-ref="zip" class="hidden" style="display:none"
                           @change="submitting = true; $el.form.requestSubmit()" :disabled="submitting">
                    <label for="zip_package" class="qe-btn qe-btn--green" :style="submitting && 'opacity:.6;pointer-events:none'">
                        <x-lucide name="upload" class="h-4 w-4" />
                        <span x-text="submitting ? 'Đang xử lý…' : 'Nhập từ ZIP'">Nhập từ ZIP</span>
                    </label>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.content.questions.store') }}" enctype="multipart/form-data" id="qe-form"
                  class="qe-layout">
                @csrf

                <div class="qe-main">
                    <section class="qe-panel">
                        <h2 class="qe-h"><x-lucide name="circle-help" class="h-4 w-4" /> Thông tin bài</h2>
                        <div class="qe-stack">
                            <div class="qe-grid2">
                                <div class="qe-field">
                                    <label for="code">Mã câu hỏi</label>
                                    <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="40"
                                           placeholder="Ví dụ: TIN10-CH014"
                                           class="admin-input">
                                </div>
                                <div class="qe-field">
                                    <label for="type">Loại câu hỏi</label>
                                    <x-ws.select id="type" name="type" x-model="type" required>
                                        @foreach ($types as $value => $label)
                                            <option value="{{ $value }}" @selected(old('type', 'mcq') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-ws.select>
                                </div>
                            </div>

                            <div class="qe-field">
                                <label for="title">Tên câu hỏi</label>
                                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                                       placeholder="Ví dụ: Bài 14 - Quy hoạch động cơ bản"
                                       class="admin-input">
                            </div>

                            {{-- SỬA 8/9 (3) (khách: "kho câu hỏi giờ làm sao để phân loại được các môn") —
                                 2 ô phân loại, ghi thẳng vào cột questions.subject/questions.grade (KHÔNG
                                 phải metadata) để tab "Câu hỏi" lọc/đếm nhanh. Cả 2 để trống được: câu chưa
                                 rõ môn vẫn tạo được bình thường, nằm nhóm "Chưa phân loại" cho tới khi gán.
                                 Câu nhập từ gói ZIP tự điền sẵn từ taxonomy trong question.json. --}}
                            <div class="qe-grid2">
                                <div class="qe-field">
                                    <label for="subject">Môn học</label>
                                    {{-- SỬA 4/10 (khách: "đề môn mặc định là Tin học") — chọn sẵn Tin học ở form
                                         TẠO MỚI. Mã môn khai ở App\Support\SubjectCatalog::DEFAULT_QUESTION_SUBJECT,
                                         đổi môn mặc định thì sửa đúng một chỗ đó. Form Sửa KHÔNG đụng tới. --}}
                                    <x-ws.select id="subject" name="subject">
                                        <option value="">— Chưa phân loại —</option>
                                        @foreach ($subjects as $code => $label)
                                            <option value="{{ $code }}" @selected(old('subject', \App\Support\SubjectCatalog::DEFAULT_QUESTION_SUBJECT) === $code)>{{ $label }}</option>
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
                            {{-- SỬA 4/10 (khách: "tỉnh thành gán là toàn quốc mặc định") — chọn sẵn Toàn quốc
                                 ở form TẠO MỚI; ô Năm chọn sẵn năm hiện tại (SỬA 9/10). --}}
                            @include('partials.question-province-year', [
                                'province' => \App\Support\ProvinceCatalog::DEFAULT_QUESTION_SCOPE,
                                // SỬA 9/10 (khách: "năm mặc định là năm hiện tại") — chọn sẵn năm hiện tại, vẫn đổi được.
                                'examYear' => (int) date('Y'),
                            ])

                            {{-- SỬA 10/10 (khách: "thêm 1 field Nguồn cho nhập vào") — ô Nguồn, ghi vào questions.source_name. --}}
                            @include('partials.question-source', ['source' => null])

                            {{-- SỬA 1/10 (khách: "trong admin chỗ tạo câu hỏi tạm thời ẩn nội dung đề bài đi") —
                                 Ô "Nội dung đề bài" được ẩn TẠM ở form TẠO: đề bài nhập bằng tệp PDF ở mục
                                 "Tệp đính kèm" bên dưới (statement_file). Form SỬA vẫn còn ô này, CỐ Ý:
                                 ContentService::questionUpdate() ghi 'body' => $data['body'] ?? null, nên ẩn
                                 ô ở form Sửa sẽ XOÁ SẠCH đề bài cũ mỗi lần bấm Lưu.
                                 Bật lại: bỏ dấu mở ghi chú Blade ở đầu khối này và dấu đóng ở cuối khối
                                 (không cần sửa controller/service — luật 'body' vẫn còn ở questionsStore()).

                            <div>
                                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="body">Nội dung đề bài</label>
                                <textarea id="body" name="body" rows="5" data-rich-editor
                                          placeholder="Nhập đề bài..."
                                          class="admin-input">{{ old('body') }}</textarea>
                            </div>
                            --}}
                        </div>
                    </section>

                    {{-- MCQ --}}
                    <section class="qe-panel" x-show="type === 'mcq'" x-cloak>
                        <h2 class="qe-h"><x-lucide name="list-checks" class="h-4 w-4" /> Phương án trả lời</h2>
                        <div class="qe-opts">
                            @foreach (['A', 'B', 'C', 'D'] as $i => $opt)
                                <label class="qe-opt">
                                    <input type="radio" name="correct_option" value="{{ $i }}" @checked((string) old('correct_option') === (string) $i) aria-label="Phương án {{ $opt }} là đáp án đúng">
                                    <b>{{ $opt }}</b>
                                    <input type="text" name="options[]" value="{{ old('options.'.$i) }}" maxlength="255" placeholder="Phương án {{ $opt }}">
                                </label>
                            @endforeach
                        </div>
                        <p class="qe-note" style="margin-top:10px;">Chọn nút tròn ở phương án đúng. Chưa chọn = chặn phát hành (6.2).</p>
                    </section>

                    {{-- Fill blank --}}
                    <section class="qe-panel" x-show="type === 'fill_blank'" x-cloak>
                        <h2 class="qe-h"><x-lucide name="text-cursor-input" class="h-4 w-4" /> Đáp án điền khuyết</h2>
                        <div class="qe-stack" style="gap:10px;">
                            <div class="qe-field">
                                <label for="accepted_answers">Đáp án được chấp nhận</label>
                                <textarea id="accepted_answers" name="accepted_answers" rows="3"
                                          placeholder="Mỗi đáp án 1 dòng, ví dụ:&#10;Hà Nội&#10;Ha Noi"
                                          class="admin-input">{{ old('accepted_answers') }}</textarea>
                            </div>
                            <label class="flex items-center gap-2 text-[13px] text-slate-600">
                                <input type="checkbox" name="case_sensitive" value="1" @checked(old('case_sensitive'))> Phân biệt hoa/thường
                            </label>
                        </div>
                    </section>

                    {{-- Coding --}}
                    <div class="qe-main" x-show="type === 'coding'" x-cloak>
                        <section class="qe-panel">
                            <h2 class="qe-h"><x-lucide name="code" class="h-4 w-4" /> Cấu hình chấm bài</h2>
                            <div class="qe-stack">
                                <div class="qe-grid2">
                                    <div class="qe-field">
                                        <label for="time_limit_ms">Time limit (ms)</label>
                                        <input id="time_limit_ms" name="time_limit_ms" type="number" min="1" value="{{ old('time_limit_ms', 1000) }}"
                                               class="admin-input">
                                    </div>
                                    <div class="qe-field">
                                        <label for="memory_limit_mb">Memory limit (MB)</label>
                                        <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="1" value="{{ old('memory_limit_mb', 256) }}"
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
                                                // SỬA 10/10 (khách: chọn "Bàn phím / màn hình" mà vẫn lưu tên tệp) — CHỈ tự điền khi đang chọn "Tệp có tên".
                                                // Trước đây gõ mã câu hỏi là điền MÃ.INP/MÃ.OUT vào 2 ô đang bị ẩn, rồi form gửi chúng lên và lưu thành đề đọc/ghi tệp.
                                                if (this.ioMode !== 'file') return;
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
                                     x-data="qeFileIo({ mode: {{ (trim((string) old('file_io_input')) !== '' || trim((string) old('file_io_output')) !== '') ? "'file'" : "'std'" }}, codeId: 'code' })"
                                     x-on:input.window="if ($event.target && $event.target.id === codeId) fill()">
                                    <div class="qe-field">
                                        <label for="io_mode">Đọc / ghi dữ liệu</label>
                                        <x-ws.select id="io_mode" name="io_mode" x-model="ioMode" x-on:change="pick()">
                                            <option value="std">Bàn phím / màn hình</option>
                                            <option value="file">Tệp có tên</option>
                                        </x-ws.select>
                                    </div>
                                    <div class="qe-grid2" x-show="ioMode === 'file'" x-cloak>
                                        <div class="qe-field">
                                            <label for="file_io_input">Tên tệp vào</label>
                                            <input id="file_io_input" name="file_io_input" type="text" maxlength="64" x-ref="fioIn" x-on:input="auto = false"
                                                   value="{{ old('file_io_input') }}" placeholder="Tự lấy theo mã câu hỏi: MÃ.INP"
                                                   x-bind:required="ioMode === 'file' && type === 'coding'" class="admin-input font-mono">
                                        </div>
                                        <div class="qe-field">
                                            <label for="file_io_output">Tên tệp ra</label>
                                            <input id="file_io_output" name="file_io_output" type="text" maxlength="64" x-ref="fioOut" x-on:input="auto = false"
                                                   value="{{ old('file_io_output') }}" placeholder="Tự lấy theo mã câu hỏi: MÃ.OUT"
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
                            @include('admin.content.questions._test-set', ['qeMode' => 'create', 'qeInitialTests' => []])
                            @error('test_cases_json')<p class="mt-2 text-[12px] text-rose-600">{{ $message }}</p>@enderror
                            @error('test_cases_raw')<p class="mt-2 text-[12px] text-rose-600">{{ $message }}</p>@enderror
                        </section>
                    </div>

                    {{-- SỬA 1/10 (khách: "nhập thủ công đang thiếu chọn file pdf, thiếu nhiều") —
                         Trước đây 3 tệp đính kèm cố định + ảnh/âm thanh CHỈ nhập được qua gói ZIP,
                         nên câu gõ tay không bao giờ có tab "Đề bài PDF" cho học sinh. Đường dẫn lưu
                         dùng LẠI đúng quy ước của gói ZIP (questions/{id}/{kind}.{ext} trên disk
                         'local'), xem ContentService::applyManualUploads(). --}}
                    <section class="qe-panel">
                        <h2 class="qe-h"><x-lucide name="paperclip" class="h-4 w-4" /> Tệp đính kèm</h2>
                        <div class="qe-stack">
                            <div class="qe-grid2">
                                <div class="qe-field">
                                    <label for="statement_file">Đề bài (PDF)</label>
                                    <input id="statement_file" name="statement_file" type="file" accept="application/pdf"
                                           class="w-full text-[13px] text-slate-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:text-[13px]">
                                    <small>Học sinh đọc được ở tab "Đề bài" lúc làm bài. Tối đa 20 MB.</small>
                                </div>
                                <div class="qe-field">
                                    <label for="solution_file">Lời giải (PDF)</label>
                                    <input id="solution_file" name="solution_file" type="file" accept="application/pdf"
                                           class="w-full text-[13px] text-slate-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:text-[13px]">
                                    <small>Chỉ Admin/Giáo viên tải được — không lộ cho học sinh. Tối đa 20 MB.</small>
                                </div>
                            </div>
                            <div class="qe-field">
                                <label for="reference_file">Code mẫu / lời giải tham khảo</label>
                                <input id="reference_file" name="reference_file" type="file" accept=".cpp,.cc,.c,.py,.pas,.java,.js,.ts,.txt,.md"
                                       class="w-full text-[13px] text-slate-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:text-[13px]">
                                <small>Tệp mã nguồn (.cpp, .py, .pas…), tối đa 2 MB. Chỉ Admin/Giáo viên tải được.</small>
                            </div>
                            <div class="qe-field">
                                <label for="asset_files">Ảnh / âm thanh kèm câu hỏi</label>
                                <input id="asset_files" name="asset_files[]" type="file" multiple accept="image/*,audio/*"
                                       class="w-full text-[13px] text-slate-700 file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:bg-indigo-600 file:text-white file:text-[13px]">
                                <small>Chọn được nhiều tệp (tối đa 20, mỗi tệp 20 MB) — hiện ảnh / phát audio ngay trong đề lúc làm bài. Cần ghi thêm lời thoại hoặc chú thích ảnh cho từng tệp thì phải nhập bằng gói ZIP.</small>
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="qe-aside">
                    <div class="qe-aside-body">
                        <h3 class="qe-h"><x-lucide name="target" class="h-4 w-4" /> Điểm &amp; hiển thị</h3>
                        @include('partials.question-difficulty-points', ['selected' => null])
                        {{-- SỬA 30/9 (khách: "chưa có thứ tự ưu tiên hiển thị") — SỐ CÀNG LỚN CÀNG HIỆN
                             TRƯỚC trong kho và ngoài trang Luyện tập; 0 = bình thường (xếp theo mới nhất
                             như trước). Xem migration add_display_order_to_questions_table. --}}
                        <div class="qe-field">
                            <label for="display_order">Thứ tự ưu tiên hiển thị</label>
                            <input id="display_order" name="display_order" type="number" min="0" max="65535" value="{{ old('display_order', 0) }}" class="admin-input">
                            <small>Số càng lớn càng hiện trước. Để 0 nếu không cần ưu tiên.</small>
                        </div>
                        <div class="qe-field">
                            <label for="visibility">Hiển thị</label>
                            <x-ws.select id="visibility" name="visibility" required>
                                @foreach ($visibilities as $value => $label)
                                    <option value="{{ $value }}" @selected(old('visibility', 'public') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-ws.select>
                        </div>
                        <div class="qe-field">
                            <label>Tag/Chuyên đề</label>
                            @include('partials.tag-picker', ['tags' => $allTags, 'selected' => old('tag_ids', [])])
                            <div style="height:8px"></div>
                            <input type="text" name="new_tags" value="{{ old('new_tags') }}" maxlength="500" placeholder="Tag mới, cách nhau bằng dấu phẩy"
                                   class="admin-input">
                        </div>

                        <div class="rounded-xl bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                            Câu hỏi luôn tạo ở trạng thái <span class="font-medium">Nháp</span> — vào trang chi tiết để phát hành sau khi đủ điều kiện (6.2).
                        </div>
                    </div>
                    <div class="qe-aside-actions">
                        <button type="submit" class="qe-btn qe-btn--primary" style="width:100%;min-height:42px;">
                            <x-lucide name="save" class="h-4 w-4" /> Tạo câu hỏi (Nháp)
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
