@extends('layouts.admin')

@section('title', 'Thêm bài tập thủ công')
@section('page-title', 'Thêm bài tập thủ công')

{{--
    SỬA 4/9 (khách yêu cầu "vừa thêm được từ ZIP và thêm thủ công nữa") — form nhập tay CHO
    TÀI LIỆU này, phỏng theo đúng cấu trúc form "Tạo câu hỏi" ở Kho câu hỏi chung (admin/
    content/questions/create.blade.php) — chỉ hỗ trợ 3 dạng đã có sẵn ô nhập tay (Trắc
    nghiệm/Điền khuyết/Lập trình), KHÔNG có "Nhiều phần" (composite, chỉ tạo được qua ZIP,
    xem ContentService::productExerciseManualCreateFormData()). Lưu xong -> Published NGAY
    (khác luồng ZIP phải qua bước Nháp -> Sửa -> Lưu), vì mọi thứ đã nhập đủ trong 1 form.
--}}
@section('content')
    @php
        $types = $types ?? [];
        $allTags = $allTags ?? collect();
        $chapters = $chapters ?? [];
        $chapterLabel = $product->chapterLabel();
    @endphp

    <a href="{{ route('admin.products.show', $product->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại {{ $product->title }}</a>

    <x-page-header title="✏️ Thêm bài tập thủ công" :subtitle="$product->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div x-data="{ type: '{{ old('type', 'mcq') }}' }">
        <form method="POST" action="{{ route('admin.products.exercises.storeManual', $product->id) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            @csrf

            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã bài tập</label>
                        <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="40"
                               placeholder="Ví dụ: TIN10-BT014"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại bài tập</label>
                        <x-select id="type" name="type" x-model="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', 'mcq') === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên bài tập</label>
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
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>🎯</span> Điểm & phân loại</h3>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="points">Điểm</label>
                    <input id="points" name="points" type="number" min="0" value="{{ old('points', 10) }}" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>

                @if ($chapterLabel && ! empty($chapters))
                    <div>
                        <label class="block text-sm text-slate-600 mb-1" for="material_id">Thuộc {{ mb_strtolower($chapterLabel) }}</label>
                        <x-select id="material_id" name="material_id">
                            <option value="">— Chưa gắn {{ mb_strtolower($chapterLabel) }} —</option>
                            @foreach ($chapters as $c)
                                <option value="{{ $c['id'] }}" @selected((string) old('material_id') === (string) $c['id'])>{{ $c['title'] }}</option>
                            @endforeach
                        </x-select>
                    </div>
                @endif

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
                    Bài tập lưu xong sẽ <span class="font-medium">hiện ngay</span> ở mục "🧪 Bài tập đính kèm" của tài liệu này.
                </div>
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 transition-colors text-white text-sm font-semibold shadow-sm">Lưu bài tập</button>
            </div>
        </form>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
