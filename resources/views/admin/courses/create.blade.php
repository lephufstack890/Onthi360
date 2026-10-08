@extends('layouts.admin')

@section('title', 'Tạo khóa học')
@section('page-title', 'Tạo khóa học')

@section('content')
    @php
        $grades = $grades ?? [];
        $statuses = $statuses ?? [];
    @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminCourses.jsx); tên field, route, validation giữ nguyên. --}}
    @include('partials.admin-courses-ui')

    <div class="acx-wrap">
    <a href="{{ route('admin.courses.index') }}" class="acx-back">‹ Quay lại Khóa & Lớp</a>

    <div class="acx-head">
        <div class="acx-head__id">
            <span class="acx-avatar"><x-lucide name="book-open" /></span>
            <div>
                <h1>Tạo khóa học mới</h1>
                <div class="acx-head__meta"><span>Khóa học là "khung" nội dung — lớp học (lịch, giáo viên, học sinh) sẽ được tạo riêng và gắn vào khóa này sau (8.1).</span></div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="acx-grid acx-grid--main">
        <div class="acx-card acx-card--white">
            <h2><x-lucide name="book-open" /> Thông tin khóa học</h2>
            <form method="POST" action="{{ route('admin.courses.store') }}" enctype="multipart/form-data" class="acx-form">
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
                    @include('partials.course-grade-field')
                </div>

                @include('partials.course-cover-field')

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="description">Mô tả khóa học</label>
                    {{-- CKEditor gắn vào đúng textarea này (script dùng chung ở partials.rich-editor-assets,
                         include ở @push('scripts') cuối file) — name="description" giữ nguyên nên vẫn submit
                         đúng field cũ, không đổi backend. --}}
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              placeholder="1-2 câu tóm tắt, hiện ngay dưới tên khoá ở trang công khai..."
                              class="admin-input">{{ old('description') }}</textarea>
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
                              placeholder="Bài giới thiệu đầy đủ: nội dung học, lộ trình, con sẽ làm được gì sau khoá..."
                              class="admin-input">{{ old('intro') }}</textarea>
                    <p class="text-xs text-slate-400 mt-1">Hiện ở mục "Giới thiệu khoá học" giữa trang công khai. Để trống thì mục đó lấy lại Mô tả ở trên.</p>
                </div>

                @include('partials.course-level-fields')

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-ws.select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>

                <div class="acx-footer">
                    <small>Thông tin có thể chỉnh lại sau khi tạo.</small>
                    <div class="acx-footer__btns">
                        <a href="{{ route('admin.courses.index') }}" class="acx-btn">Huỷ</a>
                        <button type="submit" class="acx-btn acx-btn--primary">Tạo khóa học</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="acx-card acx-card--mint">
            <h3><x-lucide name="sparkles" /> Cần biết</h3>
            <div class="acx-tips">
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="🧭" tone="sky" />
                <p style="margin:0">Khóa học chỉ là khung nội dung — sau khi tạo, giáo viên (đã được duyệt) sẽ tạo lớp thuộc khóa này để dạy thật (3.3, 8.1).</p>
            </div>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="📝" tone="violet" />
                <p style="margin:0">Đường dẫn (slug) hiển thị công khai được tự sinh từ tên khóa học, không cần tự nhập.</p>
            </div>
            <div class="flex items-start gap-3">
                <x-ws.icon-tile emoji="👁️" tone="amber" />
                <p style="margin:0">Chọn "Bản nháp" nếu chưa muốn hiển thị công khai — có thể xuất bản sau khi kiểm tra lại nội dung.</p>
            </div>
            </div>
        </div>
    </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection