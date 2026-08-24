@extends('layouts.admin')

@section('title', 'Tạo câu hỏi')
@section('page-title', 'Tạo câu hỏi (Kho chung)')

@section('content')
    @php $types = $types ?? []; $visibilities = $visibilities ?? []; $allTags = $allTags ?? collect(); @endphp

    <a href="{{ route('admin.content.index', ['tab' => 'questions']) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Nội dung</a>

    <x-page-header title="❓ Tạo câu hỏi" subtitle="Câu hỏi tạo ở đây thuộc Kho chung — Editor/Admin/Super Admin quản lý (6.5)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    {{-- SỬA 24/8 — nếu quay lại đây do lỗi từ chính form "Nhập từ gói ZIP" (zip_package), giữ
         nguyên loại "Lập trình" đang chọn thay vì rơi về mặc định "mcq" khiến form ZIP bị ẩn
         mất ngay lúc đang cần sửa lỗi. --}}
    <div x-data="{ type: '{{ old('type', $errors->has('zip_package') ? 'coding' : 'mcq') }}' }">
        {{-- SỬA 24/8 ("Nhập từ gói ZIP"): CHỈ hiện khi đang chọn loại "Lập trình" (x-show theo
             đúng $type Alpine ở trên) — tải lên 1 gói ZIP đóng gói sẵn (question.json + đề/lời
             giải PDF + test case, định dạng "OT360-QPACK") để hệ thống tự điền toàn bộ thông tin
             câu hỏi lập trình bên dưới, chỉ cần vào trang Sửa kiểm tra rồi bấm Lưu. Đây là FORM
             RIÊNG (enctype multipart riêng), KHÔNG liên quan tới form tạo tay bên dưới — xem
             App\Services\Admin\ContentService::questionStoreFromZipPackage(). --}}
        <div x-show="type === 'coding'" x-cloak class="mb-6">
            {{-- SỬA 24/8 (2) — khách yêu cầu: chọn xong tệp ZIP là TỰ ĐỘNG nhập ngay, không bắt
                 bấm thêm nút. @change ở input tự gọi requestSubmit() (Alpine — cùng cách dùng
                 @click/@change đã có sẵn ở nơi khác trong dự án, ví dụ student/assessment/
                 take.blade.php). Nút "Nhập từ ZIP" vẫn giữ lại làm phương án dự phòng (JS lỗi/
                 tắt) + hiện trạng thái "Đang xử lý..." ngay khi vừa chọn tệp. --}}
            <form method="POST" action="{{ route('admin.content.questions.zipImport') }}" enctype="multipart/form-data"
                  x-data="{ submitting: false }"
                  class="bg-indigo-50 border border-indigo-100 rounded-2xl p-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="flex-1 min-w-[240px]">
                    <label class="block text-sm font-medium text-indigo-700 mb-1" for="zip_package">📦 Nhập câu hỏi lập trình từ gói ZIP</label>
                    <input id="zip_package" name="zip_package" type="file" accept=".zip" required
                           @change="submitting = true; $el.form.requestSubmit()" :disabled="submitting"
                           class="w-full text-sm text-indigo-900 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:text-white file:text-sm disabled:opacity-60">
                    <p class="text-xs text-indigo-500 mt-1">Gói định dạng OT360-QPACK (question.json + đề/lời giải PDF + test case) — <strong>chọn tệp xong hệ thống tự động nhập ngay</strong>, không cần bấm nút. Xong sẽ chuyển sang trang Sửa để kiểm tra và Lưu.</p>
                </div>
                <button type="submit" :disabled="submitting" x-text="submitting ? 'Đang xử lý…' : 'Nhập từ ZIP'"
                        class="px-4 py-2.5 rounded-lg bg-indigo-600 text-white text-sm font-medium shrink-0 disabled:opacity-60">Nhập từ ZIP</button>
            </form>
        </div>

        <form method="POST" action="{{ route('admin.content.questions.store') }}" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @csrf

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã câu hỏi</label>
                        <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="40"
                               placeholder="Ví dụ: TIN10-CH014"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại câu hỏi</label>
                        <x-select id="type" name="type" x-model="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $errors->has('zip_package') ? 'coding' : 'mcq') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên câu hỏi</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                           placeholder="Ví dụ: Bài 14 - Quy hoạch động cơ bản"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="body">Nội dung đề bài</label>
                    <textarea id="body" name="body" rows="5" data-rich-editor
                              placeholder="Nhập đề bài..."
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('body') }}</textarea>
                </div>

                {{-- MCQ --}}
                <div x-show="type === 'mcq'" x-cloak>
                    <label class="block text-sm font-medium text-slate-600 mb-2">Phương án trả lời</label>
                    <div class="space-y-2">
                        @foreach (['A', 'B', 'C', 'D'] as $i => $opt)
                            <div class="flex items-center gap-2">
                                <input type="radio" name="correct_option" value="{{ $i }}" @checked((string) old('correct_option') === (string) $i)>
                                <input type="text" name="options[]" value="{{ old('options.'.$i) }}" maxlength="255"
                                       class="flex-1 rounded-lg border border-slate-200 text-sm p-2" placeholder="Phương án {{ $opt }}">
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Chọn radio ở phương án đúng. Chưa chọn = chặn phát hành (6.2).</p>
                </div>

                {{-- Fill blank --}}
                <div x-show="type === 'fill_blank'" x-cloak class="space-y-2">
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="accepted_answers">Đáp án được chấp nhận</label>
                    <textarea id="accepted_answers" name="accepted_answers" rows="3"
                              placeholder="Mỗi đáp án 1 dòng, ví dụ:&#10;Hà Nội&#10;Ha Noi"
                              class="w-full rounded-lg border border-slate-200 text-sm p-2">{{ old('accepted_answers') }}</textarea>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" name="case_sensitive" value="1" @checked(old('case_sensitive'))> Phân biệt hoa/thường
                    </label>
                </div>

                {{-- Coding --}}
                <div x-show="type === 'coding'" x-cloak class="space-y-3">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="time_limit_ms">Time limit (ms)</label>
                            <input id="time_limit_ms" name="time_limit_ms" type="number" min="1" value="{{ old('time_limit_ms', 1000) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="memory_limit_mb">Memory limit (MB)</label>
                            <input id="memory_limit_mb" name="memory_limit_mb" type="number" min="1" value="{{ old('memory_limit_mb', 256) }}"
                                   class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="test_cases_raw">Test cases</label>
                        <textarea id="test_cases_raw" name="test_cases_raw" rows="4"
                                  placeholder="Mỗi dòng 1 test: input|||output&#10;Ví dụ: 3 5|||8"
                                  class="w-full rounded-lg border border-slate-200 text-sm p-2 font-mono">{{ old('test_cases_raw') }}</textarea>
                        <p class="text-xs text-slate-400 mt-1">Định dạng đơn giản để nhập nhanh — chưa hỗ trợ tải file test hàng loạt.</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 h-fit space-y-4">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>🎯</span> Điểm & hiển thị</h3>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="points">Điểm</label>
                    <input id="points" name="points" type="number" min="0" value="{{ old('points', 10) }}" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="visibility">Hiển thị</label>
                    <x-select id="visibility" name="visibility" required>
                        @foreach ($visibilities as $value => $label)
                            <option value="{{ $value }}" @selected(old('visibility', 'public') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                {{-- SỬA 19/8 (Giai đoạn 6 — "Gắn tag/chủ đề cho câu hỏi"): tick tag có sẵn
                     hoặc gõ tag mới ngay ở đây (cách nhau bằng dấu phẩy) — xem
                     ContentService::resolveTagIds(). Dùng để lọc ở "Luyện tập theo câu". --}}
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Tag/Chuyên đề</label>
                    @if ($allTags->isNotEmpty())
                        <div class="flex flex-wrap gap-2 mb-2">
                            @foreach ($allTags as $tagOption)
                                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-slate-200 text-xs text-slate-600 has-[:checked]:bg-rose-50 has-[:checked]:border-rose-300 has-[:checked]:text-rose-600">
                                    <input type="checkbox" name="tag_ids[]" value="{{ $tagOption->id }}"
                                           @checked(collect(old('tag_ids', []))->contains((string) $tagOption->id))>
                                    {{ $tagOption->name }}
                                </label>
                            @endforeach
                        </div>
                    @endif
                    <input type="text" name="new_tags" value="{{ old('new_tags') }}" maxlength="500" placeholder="Tag mới, cách nhau bằng dấu phẩy"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2">
                </div>

                <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                    Câu hỏi luôn tạo ở trạng thái <span class="font-medium">Nháp</span> — vào trang chi tiết để phát hành sau khi đủ điều kiện (6.2).
                </div>
                <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Tạo câu hỏi (Nháp)</button>
            </div>
        </form>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
