@extends('layouts.admin')

@section('title', 'Sửa lớp học')
@section('page-title', 'Sửa lớp học')

@section('content')
    @php
        $teachers = $teachers ?? [];
        $scheduleNote = $classRoom->schedule['note'] ?? null;
    @endphp

    <a href="{{ route('admin.courses.show', $classRoom->course_id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại {{ $classRoom->course->title ?? 'khóa học' }}</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-center gap-4 flex-wrap">
        <x-icon-tile emoji="✏️" tone="sky" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Sửa lớp "{{ $classRoom->name }}"</h1>
            <p class="text-sm text-slate-500 mt-1">Mã lớp: {{ $classRoom->code }}</p>
        </div>
    </div>

    @if (session('status') === 'class-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.classes.update', $classRoom->id) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã lớp</label>
                        <input id="code" name="code" type="text" value="{{ old('code', $classRoom->code) }}" required maxlength="40"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Tên lớp</label>
                        <input id="name" name="name" type="text" value="{{ old('name', $classRoom->name) }}" required maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="schedule_note">Lịch học</label>
                    <input id="schedule_note" name="schedule_note" type="text" value="{{ old('schedule_note', $scheduleNote) }}" maxlength="500"
                           placeholder="Ví dụ: Thứ 3 & 5, 19:00–20:30"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="teacher_id">Giáo viên phụ trách</label>
                    <x-select id="teacher_id" name="teacher_id" icon="👩‍🏫">
                        <option value="">— Chưa phân công —</option>
                        @foreach ($teachers as $t)
                            <option value="{{ $t['id'] }}" @selected((string) old('teacher_id', $currentTeacherId) === (string) $t['id'])>{{ $t['name'] }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-select id="status" name="status" required>
                        <option value="active" @selected(old('status', $classRoom->status) === 'active')>Đang học</option>
                        <option value="archived" @selected(old('status', $classRoom->status) === 'archived')>Lưu trữ</option>
                    </x-select>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.courses.show', $classRoom->course_id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 p-6 space-y-3" x-data="{ open: false, reason: '' }">
            <h3 class="font-medium text-rose-700 flex items-center gap-2"><span>⚠️</span> Xóa lớp học</h3>
            <p class="text-sm text-slate-500">Xóa mềm — lịch sử OJ/kết quả cũ của lớp vẫn còn truy vết được. Bắt buộc nêu lý do (10.4).</p>

            <button type="button" @click="open = !open" class="text-sm font-medium text-rose-600 hover:underline" x-text="open ? 'Đóng' : 'Tôi muốn xóa lớp này'"></button>

            <form x-show="open" x-cloak method="POST" action="{{ route('admin.classes.destroy', $classRoom->id) }}" class="space-y-3 pt-2" onsubmit="return confirm('Xác nhận xóa lớp này?');">
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
@endsection
