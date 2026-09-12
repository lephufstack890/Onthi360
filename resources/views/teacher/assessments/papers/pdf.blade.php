@extends('layouts.teacher')

@section('title', 'Quản lý đề PDF')
@section('page-title', 'Quản lý đề PDF')

@section('content')
    @php
        $answerKeys = $answerKeys ?? collect();
        $codingItems = $codingItems ?? collect();
        $answerSheetTypes = $answerSheetTypes ?? [];
        $publishDecision = $publishDecision ?? null;

        $pdfStatusMessage = match (session('status')) {
            'paper-pdf-updated' => 'Đã lưu file PDF/đáp án của đề.',
            'coding-item-created' => 'Đã thêm bài lập trình.',
            'coding-item-updated' => 'Đã lưu bài lập trình.',
            'coding-item-deleted' => 'Đã xoá bài lập trình.',
            'test-cases-imported' => 'Đã nhập '.session('testCasesImportedCount').' cặp test case từ gói ZIP.',
            // SỬA 9/9 — nhập từ Excel mới chỉ ĐỔ RA FORM, chưa ghi vào CSDL, nên câu chữ phải
            // nói rõ còn 1 bước bấm Lưu nữa, tránh giáo viên tưởng xong rồi và thoát trang.
            'answer-sheet-imported' => 'Đã đọc '.session('importedAnswerKeysCount').' câu từ tệp Excel — kiểm tra lại bên dưới rồi bấm "Lưu đề PDF + đáp án" để lưu.',
            default => null,
        };
    @endphp

    <a href="{{ route('teacher.papers.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Đề PDF của tôi</a>

    <x-ws.page-header title="Quản lý đề PDF" icon="scroll-text" :subtitle="$assessment->title" />

    @if ($pdfStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $pdfStatusMessage])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @if ($publishDecision && ! $publishDecision->allowed)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 mb-5">
            <p class="text-[13px] font-medium text-amber-800">Chưa thể phát hành: {{ $publishDecision->message }}</p>
        </div>
    @elseif ($publishDecision)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 mb-5">
            <p class="text-[13px] font-medium text-emerald-700">Đề đã đủ điều kiện phát hành.</p>
        </div>
    @endif

    @php
        // SỬA 9/9 — nếu vừa bấm "Nhập đáp án từ Excel" thì dựng form theo dữ liệu đọc từ tệp
        // (chưa lưu, xem AssessmentController::papersAnswerKeysImport); còn lại dùng đáp án đã lưu.
        $seedAnswerKeys = session('importedAnswerKeys') ?? $answerKeys->map(fn ($k) => [
            'question_no' => $k->question_no,
            'question_type' => $k->question_type->value,
            'correct_answer' => $k->correct_answer,
            'points' => $k->points,
        ])->values()->all();

        // SỬA 9/9 — dạng "Câu nhiều ý" lưu dưới dạng cấu trúc từng ý; form sửa tay dùng chuỗi
        // "a:A-b:Đ-c:123" nên chuyển sẵn ở đây (dùng chung hàm với lúc đọc file Excel).
        $seedAnswerKeys = collect($seedAnswerKeys)->map(function (array $row) {
            if (($row['question_type'] ?? null) === \App\Enums\AnswerSheetQuestionType::MultiPart->value
                && is_array($row['correct_answer'] ?? null)) {
                $row['correct_answer_text'] = \App\Support\AnswerKeySheet::multiPartToText($row['correct_answer']);
            } elseif (($row['question_type'] ?? null) === \App\Enums\AnswerSheetQuestionType::MultiPart->value
                && is_string($row['correct_answer'] ?? null)) {
                // Đề lưu trước bản sửa lỗi giữ nguyên chuỗi "a:A-b:Đ-c:123" — hiện lại đúng chuỗi
                // đó để sửa/lưu lại là dữ liệu tự về đúng cấu trúc.
                $row['correct_answer_text'] = $row['correct_answer'];
            }

            return $row;
        })->all();
    @endphp

    <div x-data="answerKeysForm(@js($seedAnswerKeys))" class="bg-white rounded-3xl border border-sky-100 p-6 mb-6">
        <form method="POST" action="{{ route('teacher.papers.pdf.update', $assessment->id) }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span><x-lucide name="file-text" class="h-4 w-4" /></span> File đề</h2>
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="exam_code">Mã đề</label>
                        <input id="exam_code" name="exam_code" type="text" maxlength="60" value="{{ old('exam_code', $assessment->exam_code) }}"
                               class="admin-input">
                    </div>
                    {{-- SỬA 9/9 (2) (khách: "xoá luôn chỗ xem thử từ trang, đến trang, không cần
                         thiết") — bỏ 2 ô nhập khỏi màn hình. Vẫn gửi lại ĐÚNG giá trị đang lưu bằng
                         2 input ẩn: cột preview_page_from/to trong CSDL vẫn dùng để giới hạn số
                         trang học sinh xem thử, không có 2 dòng này thì mỗi lần bấm Lưu là xoá
                         trắng phạm vi xem thử của những đề đã cấu hình từ trước. --}}
                    <input type="hidden" name="preview_page_from" value="{{ old('preview_page_from', $assessment->preview_page_from) }}">
                    <input type="hidden" name="preview_page_to" value="{{ old('preview_page_to', $assessment->preview_page_to) }}">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="pdf">PDF đề {{ $assessment->pdf_path ? '(đã có — chọn tệp mới để thay)' : '' }}</label>
                        <input id="pdf" name="pdf" type="file" accept="application/pdf"
                               class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                        @if ($assessment->pdf_path)
                            <p class="text-xs text-slate-400 mt-1">Hiện tại: {{ $assessment->pdf_original_name }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="solution_pdf">PDF lời giải (nếu có)</label>
                        <input id="solution_pdf" name="solution_pdf" type="file" accept="application/pdf"
                               class="admin-input file:mr-3 file:rounded-xl file:border-0 file:bg-blue-50 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-blue-700">
                        @if ($assessment->solution_pdf_path)
                            <p class="text-xs text-slate-400 mt-1">Đã có PDF lời giải.</p>
                        @endif
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-2">Tối đa {{ number_format(\App\Services\PdfAssessmentEditingService::maxPdfKb() / 1024) }} MB mỗi tệp.</p>
            </div>

            {{-- ═══════════ ĐÁP ÁN ĐÚNG TỪNG CÂU ═══════════
                 SỬA 9/9 — dựng lại khối này (khách: "thiết kế cho đẹp nha hơi xấu á"): thanh công
                 cụ gọn 1 hàng, mỗi câu là 1 thẻ có số câu nổi bật + chip màu theo dạng, ô nhập đáp
                 án đổi theo dạng. Đồng thời hỗ trợ ĐỦ 5 dạng của phiếu đáp án (thêm "Đúng/Sai" cả
                 câu và "Câu nhiều ý" — xem App\Enums\AnswerSheetQuestionType). --}}
            <div class="border-t border-slate-100 pt-5">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold text-slate-800 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center"><x-lucide name="check-circle-2" class="h-4 w-4" /></span>
                            Đáp án đúng từng câu
                        </h2>
                        <span class="px-2.5 py-1 rounded-full bg-blue-50 text-blue-600 text-xs font-bold">
                            Tổng số câu: <span x-text="rows.length"></span>
                        </span>
                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold">
                            Tổng điểm: <span x-text="totalPoints"></span>
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('teacher.papers.answer-keys.template', $assessment->id) }}"
                           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-sky-100 text-slate-600 text-[13px] font-medium hover:border-emerald-300 hover:text-emerald-600 transition">
                            <span><x-lucide name="download" class="h-4 w-4" /></span> Tải file Excel mẫu
                        </a>

                        {{-- Ô chọn tệp + nút nhập thuộc form "answer-sheet-import" đặt NGOÀI form lớn
                             (xem cuối trang): HTML không cho lồng form trong form, nên dùng thuộc
                             tính form="..." để nút đứng ở đây mà vẫn gửi đúng chỗ. --}}
                        <label class="inline-flex items-center gap-2 px-3 py-2 rounded-xl border border-dashed border-slate-300 text-slate-500 text-[13px] cursor-pointer hover:border-emerald-300 hover:text-emerald-600 transition"
                               x-data="{ name: '' }">
                            <span><x-lucide name="scroll-text" class="h-4 w-4" /></span>
                            <span x-text="name || 'Chọn tệp Excel đáp án…'" class="max-w-[180px] truncate"></span>
                            <input type="file" name="answer_sheet" accept=".xlsx" form="answer-sheet-import" class="hidden"
                                   @change="name = $event.target.files[0]?.name || ''">
                        </label>
                        <button type="submit" form="answer-sheet-import"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-emerald-600 text-white text-[13px] font-semibold hover:bg-emerald-700 transition">
                            <span>⬆️</span> Nhập từ Excel
                        </button>

                        {{-- <button type="button" @click="addRow()"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-blue-600 text-white text-[13px] font-semibold hover:bg-blue-700 transition">
                            + Thêm câu
                        </button> --}}
                    </div>
                </div>

                {{-- <template x-if="rows.length === 0">
                    <div class="rounded-3xl border-2 border-dashed border-sky-100 py-10 text-center">
                        <p class="text-3xl mb-2">📝</p>
                        <p class="text-[13px] text-slate-500">Chưa có câu nào.</p>
                        <p class="text-xs text-slate-400 mt-1">Bấm <strong>+ Thêm câu</strong> để nhập tay, hoặc tải file Excel mẫu về điền rồi tải lên.</p>
                    </div>
                </template> --}}

                <div class="space-y-2">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="rounded-3xl border border-sky-100 bg-white hover:border-blue-200 transition p-3">
                            <div class="flex flex-wrap items-center gap-3">
                                {{-- Số câu --}}
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="w-9 h-9 rounded-xl bg-slate-100 text-slate-500 text-xs font-bold flex items-center justify-center">Câu</span>
                                    <input type="number" min="1" :name="`answer_keys[${index}][question_no]`" x-model="row.question_no" required
                                           class="w-16 rounded-xl border border-sky-100 text-[13px] font-semibold text-center p-2">
                                </div>

                                {{-- Dạng câu --}}
                                <select :name="`answer_keys[${index}][question_type]`" x-model="row.question_type" required
                                        class="w-52 shrink-0 rounded-xl border border-sky-100 text-[13px] p-2 bg-white">
                                    @foreach ($answerSheetTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>

                                {{-- Ô đáp án — đổi theo dạng đang chọn --}}
                                <div class="flex-1 min-w-[220px]">
                                    {{-- SỬA 9/9 (2) — LỖI ĐÃ SỬA (khách báo: "lưu đề PDF + đáp án... đáp án
                                         lưu sai"): bản trước dùng <input type="radio"> vừa gắn x-model vừa
                                         gắn :value động. Alpine đọc el.value để tính trạng thái chọn TRƯỚC
                                         khi :value kịp gán, nên ô nào cũng thấy value rỗng: đáp án đã lưu
                                         không hiện đúng ô đang chọn, và giá trị gửi lên bị rỗng/lệch.
                                         Giờ bỏ hẳn radio: 1 input ẩn DUY NHẤT mang giá trị thật của dòng,
                                         các nút chỉ việc gán vào model — gửi lên luôn đúng bằng giá trị
                                         đang thấy trên màn hình. Cùng cách mà "Đúng/Sai 4 ý" bên dưới đã
                                         dùng từ trước và vẫn chạy đúng. --}}
                                    <template x-if="row.question_type === 'single_choice'">
                                        <div class="flex gap-1.5">
                                            <input type="hidden" :name="`answer_keys[${index}][correct_answer]`" :value="row.correct_answer_single">
                                            <template x-for="letter in ['A', 'B', 'C', 'D']" :key="letter">
                                                <button type="button" @click="row.correct_answer_single = letter"
                                                        class="px-3.5 py-2 rounded-xl border text-[13px] font-semibold transition"
                                                        :class="row.correct_answer_single === letter ? 'border-sky-400 bg-sky-50 text-sky-600' : 'border-sky-100 text-slate-500 hover:border-sky-200'"
                                                        x-text="letter"></button>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="row.question_type === 'true_false'">
                                        <div class="flex gap-2">
                                            <input type="hidden" :name="`answer_keys[${index}][correct_answer]`" :value="row.correct_answer_bool">
                                            <button type="button" @click="row.correct_answer_bool = '1'"
                                                    class="px-4 py-2 rounded-xl border text-[13px] font-semibold transition"
                                                    :class="row.correct_answer_bool === '1' ? 'border-emerald-400 bg-emerald-50 text-emerald-600' : 'border-sky-100 text-slate-500 hover:border-emerald-200'">
                                                Đúng
                                            </button>
                                            <button type="button" @click="row.correct_answer_bool = '0'"
                                                    class="px-4 py-2 rounded-xl border text-[13px] font-semibold transition"
                                                    :class="row.correct_answer_bool === '0' ? 'border-blue-400 bg-blue-50 text-blue-600' : 'border-sky-100 text-slate-500 hover:border-blue-200'">
                                                Sai
                                            </button>
                                        </div>
                                    </template>

                                    <template x-if="row.question_type === 'true_false_group'">
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="part in ['a', 'b', 'c', 'd']" :key="part">
                                                <div class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl border border-sky-100">
                                                    <span class="text-xs font-bold text-slate-400 uppercase" x-text="part"></span>
                                                    <input type="hidden" :name="`answer_keys[${index}][correct_answer][${part}]`" :value="row.correct_answer_group[part] ? 1 : 0">
                                                    <button type="button" @click="row.correct_answer_group[part] = !row.correct_answer_group[part]"
                                                            class="px-2 py-0.5 rounded-md text-xs font-bold transition"
                                                            :class="row.correct_answer_group[part] ? 'bg-emerald-100 text-emerald-600' : 'bg-blue-100 text-blue-500'"
                                                            x-text="row.correct_answer_group[part] ? 'Đúng' : 'Sai'"></button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="row.question_type === 'short_answer'">
                                        <input type="text" :name="`answer_keys[${index}][correct_answer]`" x-model="row.correct_answer_short"
                                               placeholder="Ví dụ: 12.5"
                                               class="admin-input">
                                    </template>

                                    {{-- Câu nhiều ý: nhập bằng CHUỖI đúng cú pháp của file Excel để 2
                                         đường nhập (tay/Excel) dùng chung một bộ đọc duy nhất — xem
                                         App\Support\AnswerKeySheet::normalizeFormAnswer(). --}}
                                    <template x-if="row.question_type === 'multi_part'">
                                        <div>
                                            <input type="text" :name="`answer_keys[${index}][correct_answer]`" x-model="row.correct_answer_multi"
                                                   placeholder="a:A-b:Đ-c:123"
                                                   class="admin-input font-mono">
                                            <p class="text-[11px] text-slate-400 mt-1">Mỗi ý ghi <code>tên ý:đáp án</code>, cách nhau bằng “-”. Đ/S = Đúng/Sai · A,B,C,D = trắc nghiệm · số = trả lời ngắn.</p>
                                        </div>
                                    </template>
                                </div>

                                {{-- Điểm --}}
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <input type="number" min="0" :name="`answer_keys[${index}][points]`" x-model="row.points"
                                           class="w-16 rounded-xl border border-sky-100 text-[13px] text-center p-2">
                                    <span class="text-xs text-slate-400">điểm</span>
                                </div>

                                <button type="button" @click="rows.splice(index, 1)"
                                        class="w-9 h-9 shrink-0 rounded-xl text-slate-300 hover:text-blue-600 hover:bg-sky-50 transition" title="Xoá câu này">✕</button>
                            </div>
                        </div>
                    </template>
                </div>

                <p class="text-xs text-slate-400 mt-3 leading-relaxed">
                    Nhập trực tiếp trên form, hoặc tải file Excel mẫu về điền rồi tải lên. File Excel gồm 4 cột
                    <strong>Câu · Dạng · Đáp án · Điểm</strong>, cột "Dạng" ghi một trong:
                    <strong>Trắc nghiệm</strong> (A/B/C/D) ·
                    <strong>Đúng/Sai</strong> (Đ hoặc S) ·
                    <strong>Đúng/Sai 4 ý</strong> ("Đ S Đ S") ·
                    <strong>Trả lời ngắn</strong> (một số) ·
                    <strong>Câu nhiều ý</strong> ("a:A-b:Đ-c:123").
                    Nhập từ Excel sẽ <strong>thay toàn bộ</strong> danh sách bên trên và chỉ thật sự lưu khi bấm "Lưu đề PDF + đáp án".
                </p>
            </div>

            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Lưu đề PDF + đáp án</button>
                <a href="{{ route('teacher.papers.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>

        {{-- SỬA 9/9 — form gửi tệp Excel đáp án. Đặt NGOÀI form lớn phía trên (HTML không cho lồng
             form), ô chọn tệp và nút bấm nằm ở khối "Đáp án đúng từng câu" và trỏ về đây bằng
             thuộc tính form="answer-sheet-import". --}}
        <form id="answer-sheet-import" method="POST" enctype="multipart/form-data"
              action="{{ route('teacher.papers.answer-keys.import', $assessment->id) }}" class="hidden">
            @csrf
        </form>
    </div>

    {{--
      TẠM ẨN 24/8: "Bài lập trình con" (code lẻ nhúng trong đề PDF) — theo quyết định chuyển
      hướng phần lập trình sang Kho câu hỏi (loại Coding, nhập qua gói ZIP chuẩn OT360-QPACK)
      + 1 content_mode riêng cho "đề lập trình" (xem App\Enums\AssessmentContentMode::
      Programming). Tính năng này CHƯA từng được ai dùng (0 bài lúc ẩn), route/controller/
      model (AssessmentCodingItem, AssessmentCodingTestCase) vẫn còn nguyên, KHÔNG xoá — nếu
      sau này có nhu cầu thi giấy (PDF) kèm 1-2 câu code thì bỏ comment lại dùng ngay, không
      cần viết lại.

    <div class="bg-white rounded-3xl border border-sky-100 p-6 space-y-5">
        <h2 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="code-2" class="h-4 w-4" /></span> Bài lập trình con ({{ $codingItems->count() }})</h2>

        @if ($codingItems->isEmpty())
            <p class="text-[13px] text-slate-400">Đề này chưa có bài lập trình nào — thêm nếu đề có phần code.</p>
        @else
            <div class="space-y-4">
                @foreach ($codingItems as $item)
                    <details class="rounded-xl border border-sky-100 p-4" {{ $loop->first ? '' : '' }}>
                        <summary class="cursor-pointer flex items-center justify-between gap-3">
                            <span class="text-[13px] text-slate-700"><strong>{{ $item->code }}</strong> — {{ $item->title }}</span>
                            <span class="text-xs text-slate-400 shrink-0">{{ $item->points }} điểm · {{ $item->testCases->count() }} test case</span>
                        </summary>

                        <div class="mt-4 space-y-4">
                            <form method="POST" action="{{ route('teacher.papers.coding-items.update', $item->id) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                                @csrf
                                @method('PUT')
                                <div class="sm:col-span-1">
                                    <label class="block text-xs text-slate-500 mb-1">Mã bài</label>
                                    <input type="text" name="code" value="{{ $item->code }}" required maxlength="40" class="admin-input">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Tên bài</label>
                                    <input type="text" name="title" value="{{ $item->title }}" required maxlength="255" class="admin-input">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-xs text-slate-500 mb-1">Trang PDF</label>
                                    <input type="number" min="1" name="pdf_page" value="{{ $item->pdf_page }}" class="admin-input">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-xs text-slate-500 mb-1">Điểm</label>
                                    <input type="number" min="0" name="points" value="{{ $item->points }}" class="admin-input">
                                </div>
                                <div class="sm:col-span-1 flex items-end">
                                    <label class="flex items-center gap-1 text-xs text-slate-600 mr-2">
                                        <input type="checkbox" name="allowed_languages[]" value="cpp" @checked(in_array('cpp', $item->allowed_languages ?? []))> C++
                                    </label>
                                    <label class="flex items-center gap-1 text-xs text-slate-600">
                                        <input type="checkbox" name="allowed_languages[]" value="python" @checked(in_array('python', $item->allowed_languages ?? []))> Python
                                    </label>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Giới hạn thời gian (ms)</label>
                                    <input type="number" min="100" name="time_limit_ms" value="{{ $item->time_limit_ms }}" class="admin-input">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Giới hạn bộ nhớ (KB)</label>
                                    <input type="number" min="16384" name="memory_limit_kb" value="{{ $item->memory_limit_kb }}" class="admin-input">
                                </div>
                                <div class="sm:col-span-6 flex gap-2 pt-1">
                                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 text-white text-xs font-medium">Lưu bài này</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('teacher.papers.coding-items.test-cases.import', $item->id) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="file" name="test_cases_zip" accept=".zip" required class="text-xs">
                                <button type="submit" class="px-3 py-1.5 rounded-xl border border-sky-100 text-xs text-slate-600 hover:border-blue-200 hover:text-blue-600">Tải ZIP test case</button>
                                <span class="text-xs text-slate-400">Ghép cặp theo tên gốc: input .in/.inp/.txt — output .out/.ans/.expected.</span>
                            </form>

                            <form method="POST" action="{{ route('teacher.papers.coding-items.destroy', $item->id) }}" onsubmit="return confirm('Xoá bài lập trình này? Không thể hoàn tác.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-blue-600 transition-colors hover:text-blue-700">Xoá bài này</button>
                            </form>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif

        <div class="border-t border-slate-100 pt-4">
            <h3 class="text-[13px] font-medium text-slate-700 mb-2">+ Thêm bài lập trình mới</h3>
            <form method="POST" action="{{ route('teacher.papers.coding-items.store', $assessment->id) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                @csrf
                <div class="sm:col-span-1">
                    <input type="text" name="code" placeholder="Mã bài" required maxlength="40" class="admin-input">
                </div>
                <div class="sm:col-span-2">
                    <input type="text" name="title" placeholder="Tên bài" required maxlength="255" class="admin-input">
                </div>
                <div class="sm:col-span-1">
                    <input type="number" min="1" name="pdf_page" placeholder="Trang PDF" class="admin-input">
                </div>
                <div class="sm:col-span-1">
                    <input type="number" min="0" name="points" placeholder="Điểm" class="admin-input">
                </div>
                <div class="sm:col-span-1 flex items-center gap-2">
                    <label class="flex items-center gap-1 text-xs text-slate-600">
                        <input type="checkbox" name="allowed_languages[]" value="cpp" checked> C++
                    </label>
                    <label class="flex items-center gap-1 text-xs text-slate-600">
                        <input type="checkbox" name="allowed_languages[]" value="python" checked> Python
                    </label>
                </div>
                <div class="sm:col-span-6">
                    <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Thêm bài lập trình</button>
                </div>
            </form>
        </div>
    </div>
    --}}

    @push('scripts')
        <script>
            function answerKeysForm(initialRows) {
                return {
                    rows: (initialRows || []).map((r) => ({
                        question_no: r.question_no,
                        question_type: r.question_type,
                        points: r.points,
                        correct_answer_single: r.question_type === 'single_choice' ? (r.correct_answer || '') : '',
                        correct_answer_short: r.question_type === 'short_answer' ? (r.correct_answer || '') : '',
                        // SỬA 9/9 — Đúng/Sai cả câu: giữ dạng chuỗi '1'/'0' cho khớp value của radio.
                        // Number(): đề lưu trước bản sửa lỗi có giá trị là CHUỖI "1"/"0" — dùng
                        // thẳng r.correct_answer thì chuỗi "0" vẫn là truthy nên mở lại thành "Đúng".
                        correct_answer_bool: r.question_type === 'true_false' ? (Number(r.correct_answer) ? '1' : '0') : '',
                        // SỬA 9/9 — Câu nhiều ý: sửa tay bằng chuỗi "a:A-b:Đ-c:123" (đã được PHP
                        // chuyển sẵn khi dựng form, xem AnswerKeySheet::multiPartToText()).
                        correct_answer_multi: r.question_type === 'multi_part' ? (r.correct_answer_text || '') : '',
                        correct_answer_group: r.question_type === 'true_false_group'
                            ? { a: !!Number(r.correct_answer?.a), b: !!Number(r.correct_answer?.b), c: !!Number(r.correct_answer?.c), d: !!Number(r.correct_answer?.d) }
                            : { a: false, b: false, c: false, d: false },
                    })),
                    // SỬA 9/9 — tổng điểm hiện ngay cạnh tổng số câu; đây cũng chính là cách
                    // PdfAssessmentEditingService::update() cộng total_points khi lưu.
                    get totalPoints() {
                        return this.rows.reduce((sum, r) => sum + (Number(r.points) || 0), 0);
                    },
                    addRow() {
                        const nextNo = this.rows.length ? Math.max(...this.rows.map((r) => Number(r.question_no) || 0)) + 1 : 1;
                        this.rows.push({
                            question_no: nextNo,
                            question_type: 'single_choice',
                            points: 1,
                            correct_answer_single: '',
                            correct_answer_short: '',
                            correct_answer_bool: '',
                            correct_answer_multi: '',
                            correct_answer_group: { a: false, b: false, c: false, d: false },
                        });
                    },
                };
            }
        </script>
    @endpush
@endsection
