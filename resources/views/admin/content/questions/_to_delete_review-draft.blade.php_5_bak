{{--
  Route: admin.content.questions.reviewDraft | Frame: ADM-03 (rà soát)
  Spec: 6.4 — xem song song tệp gốc/câu tách, gắn cờ vùng nhận dạng kém,
  thêm-xóa-gộp-tách-đổi thứ tự câu, sửa nội dung/đáp án/điểm, chọn dạng,
  thêm metadata/cấu hình OJ. Không cho xuất bản khi còn thiếu điều kiện.
  Khác Teacher: tài liệu/câu nháp thuộc "Kho chung" (6.5) — bất kỳ Admin/
  Editor nào cũng rà soát/sửa/gộp/xóa/chuyển vào kho được, không giới hạn
  theo đúng người đã tải lên. Dữ liệu thật ($document, $drafts) do
  App\Http\Controllers\Admin\ContentController truyền vào qua
  App\Services\Admin\ContentService::reviewDraftFor(). Mỗi câu có form riêng
  lưu ngay khi bấm "Lưu câu này" (POST admin.content.drafts.update).
--}}
@extends('layouts.admin')

@section('title', 'Rà soát đề nhập')
@section('page-title', 'Rà soát đề nhập — Kho chung')

@section('content')
    @php
        $document = $document ?? null;
        $drafts = $drafts ?? [];
        $documentLabel = $document->original_filename ?? 'chưa chọn tệp';
        $isPdf = $document && str_ends_with(strtolower($document->original_filename), '.pdf');
    @endphp

    <a href="{{ route('admin.content.questions.import') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Nhập đề</a>

    <x-page-header title="Rà soát: {{ $documentLabel }}" subtitle="Kết quả trích xuất/OCR là bản nháp — phải rà soát và xác nhận trước khi chuyển vào Kho chung (6.4, 6.5)." />

    @if (session('status') === 'draft-added')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã thêm câu thủ công — điền nội dung bên dưới.'])
    @endif
    @if (session('status') === 'draft-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu câu.'])
    @endif
    @if (session('status') === 'draft-merged')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gộp 2 câu — kiểm tra lại nội dung trước khi lưu.'])
    @endif
    @if (session('status') === 'draft-discarded')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã bỏ câu này.'])
    @endif
    @if ($errors->any() && !$errors->has('promote'))
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Tệp gốc --}}
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <h3 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📄</span> Tệp gốc</h3>
            @if ($document)
                @if ($isPdf)
                    <iframe src="{{ route('admin.content.documents.download', $document->id) }}"
                            class="w-full aspect-[3/4] rounded-xl border border-slate-100"></iframe>
                @else
                    <div class="aspect-[3/4] bg-slate-50 rounded-xl flex flex-col items-center justify-center text-slate-400 text-sm gap-3">
                        <span class="text-4xl">📝</span>
                        <p>Không xem trước được file Word trực tiếp trên trình duyệt.</p>
                    </div>
                @endif
                <a href="{{ route('admin.content.documents.download', $document->id) }}"
                   class="mt-3 inline-flex items-center gap-1.5 text-sm text-rose-600 font-medium">⬇ Tải xuống tệp gốc để đối chiếu</a>
            @else
                <div class="aspect-[3/4] bg-slate-50 rounded-xl flex items-center justify-center text-slate-300 text-sm">
                    Chưa chọn tệp — vào "Nhập đề" và chọn 1 tệp "Cần rà soát".
                </div>
            @endif
        </div>

        {{-- Danh sách câu tách --}}
        <div class="space-y-3">
            @forelse ($drafts as $d)
                <div class="bg-white rounded-2xl border {{ $d['flagged'] ? 'border-amber-200' : 'border-slate-200' }} p-4"
                     x-data="{ type: '{{ old('type_guess', $d['type'] ?? 'mcq') }}' }">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-medium text-slate-500">Câu {{ $d['no'] }}</span>
                        <x-status-badge :tone="$d['tone']">Độ tin cậy: {{ $d['confidence'] }}</x-status-badge>
                    </div>

                    @if ($d['flagged'])
                        <p class="text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2 mb-3 flex items-start gap-1.5">
                            <span>⚠</span> Vùng nhận dạng kém hoặc chưa xác định dạng câu — vui lòng kiểm tra kỹ nội dung/đáp án trước khi lưu.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('admin.content.drafts.update', $d['id']) }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Dạng câu hỏi</label>
                            <select name="type_guess" x-model="type" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                <option value="mcq">Trắc nghiệm</option>
                                <option value="fill_blank">Điền đáp án</option>
                                <option value="coding">Lập trình</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Tiêu đề</label>
                            <input type="text" name="title" maxlength="255" value="{{ old('title', $d['title']) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Nội dung đề bài</label>
                            <textarea name="body" rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2" required>{{ old('body', $d['body']) }}</textarea>
                        </div>

                        <div x-show="type === 'mcq'" x-cloak class="space-y-2">
                            @foreach (['A', 'B', 'C', 'D'] as $i => $letter)
                                <div class="flex items-center gap-2">
                                    <input type="radio" name="correct_option" value="{{ $letter }}"
                                           {{ old('correct_option', $d['correctOption']) === $letter ? 'checked' : '' }}
                                           class="shrink-0">
                                    <span class="text-xs font-medium text-slate-400 w-4">{{ $letter }}</span>
                                    <input type="text" name="options[{{ $i }}]" maxlength="500"
                                           value="{{ old('options.'.$i, $d['options'][$i] ?? '') }}"
                                           class="flex-1 rounded-lg border border-slate-200 text-sm p-2" placeholder="Phương án {{ $letter }}">
                                </div>
                            @endforeach
                        </div>

                        <div x-show="type === 'fill_blank'" x-cloak class="space-y-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Đáp án chấp nhận (cách nhau dấu phẩy)</label>
                                <input type="text" name="accepted_answers" maxlength="1000"
                                       value="{{ old('accepted_answers', $d['acceptedAnswers']) }}"
                                       class="w-full rounded-lg border border-slate-200 text-sm p-2">
                            </div>
                            <label class="flex items-center gap-2 text-xs text-slate-500">
                                <input type="checkbox" name="case_sensitive" value="1" {{ old('case_sensitive', $d['caseSensitive']) ? 'checked' : '' }}>
                                Phân biệt hoa/thường
                            </label>
                        </div>

                        <div x-show="type === 'coding'" x-cloak class="space-y-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500 mb-1">Test case (mỗi dòng: input=>output)</label>
                                <textarea name="test_cases" rows="3" class="w-full rounded-lg border border-slate-200 text-sm p-2 font-mono">{{ old('test_cases', $d['testCases']) }}</textarea>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Giới hạn thời gian (ms)</label>
                                    <input type="number" name="time_limit_ms" min="100" max="60000"
                                           value="{{ old('time_limit_ms', $d['timeLimitMs']) }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-500 mb-1">Giới hạn bộ nhớ (MB)</label>
                                    <input type="number" name="memory_limit_mb" min="16" max="2048"
                                           value="{{ old('memory_limit_mb', $d['memoryLimitMb']) }}" class="w-full rounded-lg border border-slate-200 text-sm p-2">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Điểm</label>
                            <input type="number" name="points" min="1" max="100" value="{{ old('points', $d['points']) }}"
                                   class="w-32 rounded-lg border border-slate-200 text-sm p-2">
                        </div>

                        <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu câu này</button>
                    </form>

                    <div class="flex items-center gap-3 text-sm mt-3 pt-3 border-t border-slate-100">
                        @if (count($d['otherDrafts']) > 0)
                            <form method="POST" action="{{ route('admin.content.drafts.merge', $d['id']) }}" class="flex items-center gap-2">
                                @csrf
                                <select name="merge_with_id" class="rounded-lg border border-slate-200 text-xs p-1.5">
                                    @foreach ($d['otherDrafts'] as $other)
                                        <option value="{{ $other['id'] }}">Gộp với {{ $other['label'] }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-slate-600 text-sm">Gộp</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.content.drafts.discard', $d['id']) }}"
                              onsubmit="return confirm('Bỏ câu này khỏi danh sách rà soát?');">
                            @csrf
                            <button type="submit" class="text-rose-500 text-sm">Xóa</button>
                        </form>
                    </div>
                </div>
            @empty
                <x-empty-state title="Không có câu nào cần rà soát" description="Chọn một tệp đang 'Cần rà soát' từ trang Nhập đề." />
            @endforelse

            @if ($document)
                <form method="POST" action="{{ route('admin.content.drafts.store', $document->id) }}">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-3 hover:border-rose-300 hover:text-rose-500">
                        + Thêm câu thủ công
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if ($document)
        <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4 mt-6 flex items-center justify-between flex-wrap gap-3">
            <p class="text-sm text-amber-800 flex items-center gap-2">
                <span>⚠️</span>
                @if ($errors->has('promote'))
                    {{ $errors->first('promote') }}
                @else
                    Mỗi câu tự lưu riêng khi bấm "Lưu câu này" — khi tất cả câu đã đủ điều kiện, bấm "Chuyển vào Kho chung".
                @endif
            </p>
            <form method="POST" action="{{ route('admin.content.documents.promote', $document->id) }}">
                @csrf
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Chuyển vào Kho chung</button>
            </form>
        </div>
    @endif
@endsection
