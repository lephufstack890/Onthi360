@extends('layouts.admin')

@section('title', 'Tạo khóa học')
@section('page-title', 'Tạo khóa học')

@section('content')
    @php
        $grades = $grades ?? [];
        $statuses = $statuses ?? [];
    @endphp

    <a href="{{ route('admin.courses.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Khóa & Lớp</a>

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-5 lg:p-6 mb-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] flex items-center gap-4 flex-wrap">
        <x-ws.icon-tile emoji="🏫" tone="rose" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Tạo khóa học mới</h1>
            <p class="text-[13px] text-slate-500 mt-1">Khóa học là "khung" nội dung — lớp học (lịch, giáo viên, học sinh) sẽ được tạo riêng và gắn vào khóa này sau (8.1).</p>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
            <form method="POST" action="{{ route('admin.courses.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên khóa học</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                           placeholder="Ví dụ: Luyện thi vào 10 Chuyên Tin"
                           class="admin-input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject') }}" maxlength="60"
                               placeholder="Ví dụ: Tin học, Toán"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-ws.select id="grade" name="grade" icon="🎓">
                            <option value="">— Không chỉ định —</option>
                            @foreach ($grades as $g)
                                <option value="{{ $g }}" @selected(old('grade') === $g)>{{ $g }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="description">Mô tả khóa học</label>
                    {{-- CKEditor gắn vào đúng textarea này (script dùng chung ở partials.rich-editor-assets,
                         include ở @push('scripts') cuối file) — name="description" giữ nguyên nên vẫn submit
                         đúng field cũ, không đổi backend. --}}
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              placeholder="Giới thiệu ngắn về mục tiêu, đối tượng phù hợp của khóa học..."
                              class="admin-input">{{ old('description') }}</textarea>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-ws.select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Tạo khóa học</button>
                    <a href="{{ route('admin.courses.index') }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-sky-100 p-6 space-y-4">
            <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="sparkles" class="h-4 w-4" /></span> Cần biết</h3>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="🧭" tone="sky" />
                <p class="text-[13px] text-slate-500">Khóa học chỉ là khung nội dung — sau khi tạo, giáo viên (đã được duyệt) sẽ tạo lớp thuộc khóa này để dạy thật (3.3, 8.1).</p>
            </div>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="📝" tone="violet" />
                <p class="text-[13px] text-slate-500">Đường dẫn (slug) hiển thị công khai được tự sinh từ tên khóa học, không cần tự nhập.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="👁️" tone="amber" />
                <p class="text-[13px] text-slate-500">Chọn "Bản nháp" nếu chưa muốn hiển thị công khai — có thể xuất bản sau khi kiểm tra lại nội dung.</p>
            </div>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection