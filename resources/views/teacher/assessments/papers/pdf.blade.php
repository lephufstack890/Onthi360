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
            default => null,
        };
    @endphp

    <a href="{{ route('teacher.papers.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Đề PDF của tôi</a>

    <x-page-header title="📄 Quản lý đề PDF" :subtitle="$assessment->title" />

    @if ($pdfStatusMessage)
        @include('partials.toast-flash', ['type' => 'success', 'message' => $pdfStatusMessage])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @if ($publishDecision && ! $publishDecision->allowed)
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 mb-5">
            <p class="text-sm font-medium text-amber-800">Chưa thể phát hành: {{ $publishDecision->message }}</p>
        </div>
    @elseif ($publishDecision)
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 mb-5">
            <p class="text-sm font-medium text-emerald-700">Đề đã đủ điều kiện phát hành.</p>
        </div>
    @endif

    <div x-data="answerKeysForm({{ $answerKeys->map(fn ($k) => [
            'question_no' => $k->question_no,
            'question_type' => $k->question_type->value,
            'correct_answer' => $k->correct_answer,
            'points' => $k->points,
        ])->values()->toJson() }})" class="bg-white rounded-2xl border border-slate-200 p-6 mb-6">
        <form method="POST" action="{{ route('teacher.papers.pdf.update', $assessment->id) }}" enctype="multipart/form-data" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📎</span> File đề</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="exam_code">Mã đề</label>
                        <input id="exam_code" name="exam_code" type="text" maxlength="60" value="{{ old('exam_code', $assessment->exam_code) }}"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="preview_page_from">Xem thử từ trang</label>
                            <input id="preview_page_from" name="preview_page_from" type="number" min="1" value="{{ old('preview_page_from', $assessment->preview_page_from) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="preview_page_to">đến trang</label>
                            <input id="preview_page_to" name="preview_page_to" type="number" min="1" value="{{ old('preview_page_to', $assessment->preview_page_to) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="pdf">PDF đề {{ $assessment->pdf_path ? '(đã có — chọn tệp mới để thay)' : '' }}</label>
                        <input id="pdf" name="pdf" type="file" accept="application/pdf"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1.5">
                        @if ($assessment->pdf_path)
                            <p class="text-xs text-slate-400 mt-1">Hiện tại: {{ $assessment->pdf_original_name }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="solution_pdf">PDF lời giải (nếu có)</label>
                        <input id="solution_pdf" name="solution_pdf" type="file" accept="application/pdf"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2 file:mr-3 file:rounded-lg file:border-0 file:bg-rose-50 file:text-rose-600 file:px-3 file:py-1.5">
                        @if ($assessment->solution_pdf_path)
                            <p class="text-xs text-slate-400 mt-1">Đã có PDF lời giải.</p>
                        @endif
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-2">Tối đa {{ number_format(\App\Services\PdfAssessmentEditingService::maxPdfKb() / 1024) }} MB mỗi tệp.</p>
            </div>

            <div class="border-t border-slate-100 pt-5">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>✅</span> Đáp án đúng từng câu</h2>
                    <button type="button" @click="addRow()" class="text-sm text-rose-600 font-medium">+ Thêm câu</button>
                </div>

                <template x-if="rows.length === 0">
                    <p class="text-sm text-slate-400 py-3">Chưa có câu nào — bấm "+ Thêm câu" để nhập đáp án.</p>
                </template>

                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="index">
                        <div class="rounded-xl border border-slate-200 p-3 grid grid-cols-1 sm:grid-cols-12 gap-3 items-start">
                            <div class="sm:col-span-2">
                                <label class="block text-xs text-slate-500 mb-1">Câu số</label>
                                <input type="number" min="1" :name="`answer_keys[${index}][question_no]`" x-model="row.question_no" required
                                       class="w-full rounded-lg border border-slate-200 text-sm p-2">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-xs text-slate-500 mb-1">Dạng câu</label>
                                <select :name="`answer_keys[${index}][question_type]`" x-model="row.question_type" required
                                        class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                    @foreach ($answerSheetTypes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-5">
                                <label class="block text-xs text-slate-500 mb-1">Đáp án đúng</label>

                                <template x-if="row.question_type === 'single_choice'">
                                    <input type="text" maxlength="1" :name="`answer_keys[${index}][correct_answer]`" x-model="row.correct_answer_single"
                                           placeholder="A / B / C / D" class="w-full rounded-lg border border-slate-200 text-sm p-2 uppercase">
                                </template>

                                <template x-if="row.question_type === 'short_answer'">
                                    <input type="text" :name="`answer_keys[${index}][correct_answer]`" x-model="row.correct_answer_short"
                                           placeholder="Ví dụ: 12.5" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </template>

                                <template x-if="row.question_type === 'true_false_group'">
                                    <div class="flex items-center gap-3">
                                        <template x-for="part in ['a', 'b', 'c', 'd']" :key="part">
                                            <label class="flex items-center gap-1 text-xs text-slate-600">
                                                <input type="hidden" :name="`answer_keys[${index}][correct_answer][${part}]`" :value="row.correct_answer_group[part] ? 1 : 0">
                                                <input type="checkbox" x-model="row.correct_answer_group[part]">
                                                <span x-text="part.toUpperCase()"></span>
                                            </label>
                                        </template>
                                    </div>
                                </template>
                            </div>
                            <div class="sm:col-span-1">
                                <label class="block text-xs text-slate-500 mb-1">Điểm</label>
                                <input type="number" min="0" :name="`answer_keys[${index}][points]`" x-model="row.points"
                                       class="w-full rounded-lg border border-slate-200 text-sm p-2">
                            </div>
                            <div class="sm:col-span-1 flex sm:justify-end pt-5">
                                <button type="button" @click="rows.splice(index, 1)" class="text-xs text-rose-500 hover:text-rose-700">Xoá</button>
                            </div>
                        </div>
                    </template>
                </div>
                <p class="text-xs text-slate-400 mt-2">Đáp án nhập trực tiếp trên form — không hỗ trợ nhập bằng Excel/CSV.</p>
            </div>

            <div class="flex gap-3 pt-2 border-t border-slate-100">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu đề PDF + đáp án</button>
                <a href="{{ route('teacher.papers.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-5">
        <h2 class="font-medium text-slate-700 flex items-center gap-2"><span>💻</span> Bài lập trình con ({{ $codingItems->count() }})</h2>

        @if ($codingItems->isEmpty())
            <p class="text-sm text-slate-400">Đề này chưa có bài lập trình nào — thêm nếu đề có phần code.</p>
        @else
            <div class="space-y-4">
                @foreach ($codingItems as $item)
                    <details class="rounded-xl border border-slate-200 p-4" {{ $loop->first ? '' : '' }}>
                        <summary class="cursor-pointer flex items-center justify-between gap-3">
                            <span class="text-sm text-slate-700"><strong>{{ $item->code }}</strong> — {{ $item->title }}</span>
                            <span class="text-xs text-slate-400 shrink-0">{{ $item->points }} điểm · {{ $item->testCases->count() }} test case</span>
                        </summary>

                        <div class="mt-4 space-y-4">
                            <form method="POST" action="{{ route('teacher.papers.coding-items.update', $item->id) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                                @csrf
                                @method('PUT')
                                <div class="sm:col-span-1">
                                    <label class="block text-xs text-slate-500 mb-1">Mã bài</label>
                                    <input type="text" name="code" value="{{ $item->code }}" required maxlength="40" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Tên bài</label>
                                    <input type="text" name="title" value="{{ $item->title }}" required maxlength="255" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-xs text-slate-500 mb-1">Trang PDF</label>
                                    <input type="number" min="1" name="pdf_page" value="{{ $item->pdf_page }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-xs text-slate-500 mb-1">Điểm</label>
                                    <input type="number" min="0" name="points" value="{{ $item->points }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
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
                                    <input type="number" min="100" name="time_limit_ms" value="{{ $item->time_limit_ms }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-xs text-slate-500 mb-1">Giới hạn bộ nhớ (KB)</label>
                                    <input type="number" min="16384" name="memory_limit_kb" value="{{ $item->memory_limit_kb }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div class="sm:col-span-6 flex gap-2 pt-1">
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-800 text-white text-xs font-medium">Lưu bài này</button>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('teacher.papers.coding-items.test-cases.import', $item->id) }}" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                @csrf
                                <input type="file" name="test_cases_zip" accept=".zip" required class="text-xs">
                                <button type="submit" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs text-slate-600 hover:border-rose-200 hover:text-rose-600">Tải ZIP test case</button>
                                <span class="text-xs text-slate-400">Ghép cặp theo tên gốc: input .in/.inp/.txt — output .out/.ans/.expected.</span>
                            </form>

                            <form method="POST" action="{{ route('teacher.papers.coding-items.destroy', $item->id) }}" onsubmit="return confirm('Xoá bài lập trình này? Không thể hoàn tác.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs text-rose-500 hover:text-rose-700">Xoá bài này</button>
                            </form>
                        </div>
                    </details>
                @endforeach
            </div>
        @endif

        <div class="border-t border-slate-100 pt-4">
            <h3 class="text-sm font-medium text-slate-700 mb-2">+ Thêm bài lập trình mới</h3>
            <form method="POST" action="{{ route('teacher.papers.coding-items.store', $assessment->id) }}" class="grid grid-cols-1 sm:grid-cols-6 gap-3">
                @csrf
                <div class="sm:col-span-1">
                    <input type="text" name="code" placeholder="Mã bài" required maxlength="40" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>
                <div class="sm:col-span-2">
                    <input type="text" name="title" placeholder="Tên bài" required maxlength="255" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>
                <div class="sm:col-span-1">
                    <input type="number" min="1" name="pdf_page" placeholder="Trang PDF" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>
                <div class="sm:col-span-1">
                    <input type="number" min="0" name="points" placeholder="Điểm" class="w-full rounded-lg border border-slate-200 text-sm p-2">
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
                    <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-xs font-medium shadow-sm hover:bg-rose-700 transition">Thêm bài lập trình</button>
                </div>
            </form>
        </div>
    </div>

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
                        correct_answer_group: r.question_type === 'true_false_group'
                            ? { a: !!r.correct_answer?.a, b: !!r.correct_answer?.b, c: !!r.correct_answer?.c, d: !!r.correct_answer?.d }
                            : { a: false, b: false, c: false, d: false },
                    })),
                    addRow() {
                        const nextNo = this.rows.length ? Math.max(...this.rows.map((r) => Number(r.question_no) || 0)) + 1 : 1;
                        this.rows.push({
                            question_no: nextNo,
                            question_type: 'single_choice',
                            points: 0,
                            correct_answer_single: '',
                            correct_answer_short: '',
                            correct_answer_group: { a: false, b: false, c: false, d: false },
                        });
                    },
                };
            }
        </script>
    @endpush
@endsection
