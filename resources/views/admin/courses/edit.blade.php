@extends('layouts.admin')

@section('title', 'Sửa khóa học')
@section('page-title', 'Sửa khóa học')

@section('content')
    @php
        $grades = $grades ?? [];
        $statuses = $statuses ?? [];
    @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminCourses.jsx); tên field, route, validation giữ nguyên. --}}
    @include('partials.admin-courses-ui')

    <div class="acx-wrap">
    <a href="{{ route('admin.courses.show', $course->id) }}" class="acx-back">‹ Quay lại chi tiết khóa học</a>

    <div class="acx-head">
        <div class="acx-head__id">
            <span class="acx-avatar"><x-lucide name="pen-line" /></span>
            <div>
                <h1>Sửa khóa học</h1>
                <div class="acx-head__meta"><span>Đường dẫn công khai <strong>/khoa-hoc/{{ $course->slug }}</strong> được giữ nguyên khi sửa (không đổi slug).</span></div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="acx-grid acx-grid--main">
        <div class="acx-card acx-card--white">
            <h2><x-lucide name="book-open" /> Thông tin khóa học</h2>
            <form method="POST" action="{{ route('admin.courses.update', $course->id) }}" enctype="multipart/form-data" class="acx-form">
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

                <div class="acx-footer">
                    <small>Các thay đổi chỉ được lưu sau khi bấm "Lưu thay đổi".</small>
                    <div class="acx-footer__btns">
                        <a href="{{ route('admin.courses.show', $course->id) }}" class="acx-btn">Huỷ</a>
                        <button type="submit" class="acx-btn acx-btn--primary">Lưu thay đổi</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="acx-danger" x-data="{ open: false, reason: '' }">
            <h3><x-lucide name="alert-triangle" /> Xóa khóa học</h3>
            <p>Xóa VĨNH VIỄN khóa học cùng toàn bộ lớp thuộc khóa và mọi dữ liệu liên quan (học viên ghi danh, buổi học, điểm danh, bài giao, bài làm của học sinh, đánh giá, ảnh bìa). Không thể khôi phục. Lý do là tùy chọn, nếu nhập sẽ được ghi vào nhật ký hệ thống.</p>

            <button type="button" @click="open = !open" class="acx-danger__toggle" x-text="open ? 'Đóng' : 'Tôi muốn xóa khóa học này'"></button>

            <form x-show="open" x-cloak method="POST" action="{{ route('admin.courses.destroy', $course->id) }}" class="" onsubmit="return confirm('Xóa VĨNH VIỄN khóa học này cùng toàn bộ lớp và dữ liệu liên quan? Không thể khôi phục.');">
                @csrf
                @method('DELETE')
                <div>
                    <label style="display:block;font-size:13px;color:#475569">Lý do xóa (không bắt buộc)</label>
                    <textarea name="reason" x-model="reason" rows="3" class="admin-input" placeholder="Nêu lý do nếu cần..."></textarea>
                </div>
                <button type="submit" class="acx-btn acx-btn--danger" style="width:100%;margin-top:10px">Xác nhận xóa</button>
            </form>
        </div>
    </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
