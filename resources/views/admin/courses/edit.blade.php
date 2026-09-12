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
        <x-admin.icon-tile emoji="✏️" tone="rose" />
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
            <form method="POST" action="{{ route('admin.courses.update', $course->id) }}" class="space-y-4">
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
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-admin.select id="grade" name="grade" icon="🎓">
                            <option value="">— Không chỉ định —</option>
                            @foreach ($grades as $g)
                                <option value="{{ $g }}" @selected(old('grade', $course->grade) === $g)>{{ $g }}</option>
                            @endforeach
                        </x-admin.select>
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="description">Mô tả khóa học</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              class="admin-input">{{ old('description', $course->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-admin.select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $course->status->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.courses.show', $course->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-blue-200 p-6 space-y-3" x-data="{ open: false, reason: '' }">
            <h3 class="flex items-center gap-2 text-[13px] font-bold text-blue-700"><span><x-lucide name="alert-triangle" class="h-4 w-4" /></span> Xóa khóa học</h3>
            <p class="text-[13px] text-slate-500">Xóa mềm — dữ liệu vẫn còn trong hệ thống để tra cứu, chỉ ẩn khỏi danh sách. Bắt buộc nêu lý do (10.4).</p>

            <button type="button" @click="open = !open" class="text-xs font-bold text-blue-600 hover:underline" x-text="open ? 'Đóng' : 'Tôi muốn xóa khóa học này'"></button>

            <form x-show="open" x-cloak method="POST" action="{{ route('admin.courses.destroy', $course->id) }}" class="space-y-3 pt-2" onsubmit="return confirm('Xác nhận xóa khóa học này?');">
                @csrf
                @method('DELETE')
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1">Lý do xóa (bắt buộc)</label>
                    <textarea name="reason" x-model="reason" rows="3" required class="admin-input" placeholder="Nêu rõ lý do..."></textarea>
                </div>
                <button type="submit" :disabled="reason.trim().length === 0"
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
