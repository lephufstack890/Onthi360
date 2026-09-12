@extends('layouts.admin')

@section('title', 'Sửa lớp học')
@section('page-title', 'Sửa lớp học')

@section('content')
    @php
        $teachers = $teachers ?? [];
        $scheduleNote = $classRoom->schedule['note'] ?? null;
    @endphp

    <a href="{{ route('admin.courses.show', $classRoom->course_id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại {{ $classRoom->course->title ?? 'khóa học' }}</a>

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-5 lg:p-6 mb-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] flex items-center gap-4 flex-wrap">
        <x-admin.icon-tile emoji="✏️" tone="sky" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Sửa lớp "{{ $classRoom->name }}"</h1>
            <p class="text-[13px] text-slate-500 mt-1">Mã lớp: {{ $classRoom->code }}</p>
        </div>
    </div>

    @if (session('status') === 'class-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
            <form method="POST" action="{{ route('admin.classes.update', $classRoom->id) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="code">Mã lớp</label>
                        <input id="code" name="code" type="text" value="{{ old('code', $classRoom->code) }}" required maxlength="40"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="name">Tên lớp</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $classRoom->name) }}" required maxlength="255"
                               class="admin-input">
                    </div>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="schedule_note">Lịch học</label>
                    <input id="schedule_note" name="schedule_note" type="text" value="{{ old('schedule_note', $scheduleNote) }}" maxlength="500"
                           placeholder="Ví dụ: Thứ 3 & 5, 19:00–20:30"
                           class="admin-input">
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="teacher_id">Giáo viên phụ trách</label>
                    <x-admin.select id="teacher_id" name="teacher_id" icon="👩‍🏫">
                        <option value="">— Chưa phân công —</option>
                        @foreach ($teachers as $t)
                            <option value="{{ $t['id'] }}" @selected((string) old('teacher_id', $currentTeacherId) === (string) $t['id'])>{{ $t['name'] }}</option>
                        @endforeach
                    </x-admin.select>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-admin.select id="status" name="status" required>
                        <option value="active" @selected(old('status', $classRoom->status) === 'active')>Đang học</option>
                        <option value="archived" @selected(old('status', $classRoom->status) === 'archived')>Lưu trữ</option>
                    </x-admin.select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.courses.show', $classRoom->course_id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-3xl border border-blue-200 p-6 space-y-3" x-data="{ open: false, reason: '' }">
            <h3 class="flex items-center gap-2 text-[13px] font-bold text-blue-700"><span><x-lucide name="alert-triangle" class="h-4 w-4" /></span> Xóa lớp học</h3>
            <p class="text-[13px] text-slate-500">Xóa mềm — lịch sử OJ/kết quả cũ của lớp vẫn còn truy vết được. Bắt buộc nêu lý do (10.4).</p>

            <button type="button" @click="open = !open" class="text-xs font-bold text-blue-600 hover:underline" x-text="open ? 'Đóng' : 'Tôi muốn xóa lớp này'"></button>

            <form x-show="open" x-cloak method="POST" action="{{ route('admin.classes.destroy', $classRoom->id) }}" class="space-y-3 pt-2" onsubmit="return confirm('Xác nhận xóa lớp này?');">
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
@endsection
