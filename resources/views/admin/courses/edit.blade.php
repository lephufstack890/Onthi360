@extends('layouts.admin')

@section('title', 'Sửa khóa học')
@section('page-title', 'Sửa khóa học')

@section('content')
    @php
        $grades = $grades ?? [];
        $statuses = $statuses ?? [];
    @endphp

    <a href="{{ route('admin.courses.show', $course->id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết khóa học</a>

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-5 lg:p-6 mb-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] flex items-center gap-4 flex-wrap">
        <x-ws.icon-tile emoji="✏️" tone="rose" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Sửa khóa học</h1>
            <p class="text-[13px] text-slate-500 mt-1">Đường dẫn công khai <span class="font-medium">/khoa-hoc/{{ $course->slug }}</span> được giữ nguyên khi sửa (không đổi slug).</p>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
            <form method="POST" action="{{ route('admin.courses.update', $course->id) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên khóa học</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $course->title) }}" required maxlength="255"
                           class="admin-input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject', $course->subject) }}" maxlength="60"
                               class="admin-input">
                    </div>
                    @include('partials.course-grade-field')
                </div>

                @include('partials.course-cover-field')

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="description">Mô tả khóa học</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              class="admin-input">{{ old('description', $course->description) }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">Ngắn gọn 1-2 câu — đây là dòng tóm tắt dưới tên khoá, dài quá sẽ bị cắt.</p>
                </div>

                {{-- SỬA 1/10 (khách: "thêm hộ tôi 1 field giới thiệu nữa nhé dạng ckeditor như
                     mô tả nhé") — HAI ô khác việc nhau, đừng nhập trùng nội dung:
                       · "Mô tả khoá học" (ô trên) = 1-2 câu tóm tắt, hiện ngay DƯỚI TÊN KHOÁ ở
                         trang công khai và dùng làm mô tả cho Google/Facebook. Dài quá sẽ bị cắt.
                       · "Giới thiệu khoá học" (ô này) = bài giới thiệu đầy đủ, hiện ở mục
                         "Giới thiệu khoá học" giữa trang, không bị cắt.
                     Để trống ô này thì mục giới thiệu ngoài trang tự lấy lại Mô tả như trước,
                     nên khoá cũ không bị trống chỗ đó. --}}
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="intro">Giới thiệu khoá học</label>
                    <textarea id="intro" name="intro" rows="10" data-rich-editor
                              class="admin-input">{{ old('intro', $course->intro) }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">Hiện ở mục "Giới thiệu khoá học" giữa trang công khai. Để trống thì mục đó lấy lại Mô tả ở trên.</p>
                </div>

                @include('partials.course-level-fields')

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-ws.select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $course->status->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.courses.show', $course->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-blue-200 p-6 space-y-3" x-data="{ open: false, reason: '' }">
            <h3 class="flex items-center gap-2 text-[13px] font-bold text-blue-700"><span><x-lucide name="alert-triangle" class="h-4 w-4" /></span> Xóa khóa học</h3>
            <p class="text-[13px] text-slate-500">Xóa VĨNH VIỄN khóa học cùng toàn bộ lớp thuộc khóa và mọi dữ liệu liên quan (học viên ghi danh, buổi học, điểm danh, bài giao, bài làm của học sinh, đánh giá, ảnh bìa). Không thể khôi phục. Lý do là tùy chọn, nếu nhập sẽ được ghi vào nhật ký hệ thống.</p>

            <button type="button" @click="open = !open" class="text-xs font-bold text-blue-600 hover:underline" x-text="open ? 'Đóng' : 'Tôi muốn xóa khóa học này'"></button>

            <form x-show="open" x-cloak method="POST" action="{{ route('admin.courses.destroy', $course->id) }}" class="space-y-3 pt-2" onsubmit="return confirm('Xóa VĨNH VIỄN khóa học này cùng toàn bộ lớp và dữ liệu liên quan? Không thể khôi phục.');">
                @csrf
                @method('DELETE')
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1">Lý do xóa (không bắt buộc)</label>
                    <textarea name="reason" x-model="reason" rows="3" class="admin-input" placeholder="Nêu lý do nếu cần..."></textarea>
                </div>
                <button type="submit"
                        class="inline-flex min-h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-rose-100 transition-colors hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-40">
                    Xác nhận xóa
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
