@extends('layouts.admin')

@section('title', 'Sửa khóa học')
@section('page-title', 'Sửa khóa học')

@section('content')
    @php
        $grades = $grades ?? [];
        $statuses = $statuses ?? [];
    @endphp

    <a href="{{ route('admin.courses.show', $course->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết khóa học</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-center gap-4 flex-wrap">
        <x-icon-tile emoji="✏️" tone="rose" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Sửa khóa học</h1>
            <p class="text-sm text-slate-500 mt-1">Đường dẫn công khai <span class="font-medium">/khoa-hoc/{{ $course->slug }}</span> được giữ nguyên khi sửa (không đổi slug).</p>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.courses.update', $course->id) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên khóa học</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $course->title) }}" required maxlength="255"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="subject">Môn học</label>
                        <input id="subject" name="subject" type="text" value="{{ old('subject', $course->subject) }}" maxlength="60"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="grade">Khối lớp</label>
                        <x-select id="grade" name="grade" icon="🎓">
                            <option value="">— Không chỉ định —</option>
                            @foreach ($grades as $g)
                                <option value="{{ $g }}" @selected(old('grade', $course->grade) === $g)>{{ $g }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="description">Mô tả khóa học</label>
                    <textarea id="description" name="description" rows="5" maxlength="5000" data-rich-editor
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('description', $course->description) }}</textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-select id="status" name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $course->status->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.courses.show', $course->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 p-6 space-y-3" x-data="{ open: false, reason: '' }">
            <h3 class="font-medium text-rose-700 flex items-center gap-2"><span>⚠️</span> Xóa khóa học</h3>
            <p class="text-sm text-slate-500">Xóa mềm — dữ liệu vẫn còn trong hệ thống để tra cứu, chỉ ẩn khỏi danh sách. Bắt buộc nêu lý do (10.4).</p>

            <button type="button" @click="open = !open" class="text-sm font-medium text-rose-600 hover:underline" x-text="open ? 'Đóng' : 'Tôi muốn xóa khóa học này'"></button>

            <form x-show="open" x-cloak method="POST" action="{{ route('admin.courses.destroy', $course->id) }}" class="space-y-3 pt-2" onsubmit="return confirm('Xác nhận xóa khóa học này?');">
                @csrf
                @method('DELETE')
                <div>
                    <label class="block text-sm text-slate-600 mb-1">Lý do xóa (bắt buộc)</label>
                    <textarea name="reason" x-model="reason" rows="3" required class="w-full rounded-lg border border-slate-200 text-sm p-2" placeholder="Nêu rõ lý do..."></textarea>
                </div>
                <button type="submit" :disabled="reason.trim().length === 0"
                        class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                    Xác nhận xóa
                </button>
            </form>
        </div>
    </div>

    @push('scripts')
        @include('partials.rich-editor-assets')
    @endpush
@endsection
