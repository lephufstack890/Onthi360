@extends('layouts.admin')

@section('title', 'Tạo lớp học')
@section('page-title', 'Tạo lớp học')

@section('content')
    @php $teachers = $teachers ?? []; @endphp

    <a href="{{ route('admin.courses.show', $course->id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại {{ $course->title }}</a>

    <div class="rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-5 lg:p-6 mb-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] flex items-center gap-4 flex-wrap">
        <x-admin.icon-tile emoji="🏫" tone="sky" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Tạo lớp thuộc "{{ $course->title }}"</h1>
            <p class="text-[13px] text-slate-500 mt-1">Lớp là nơi tổ chức lịch, giáo viên và học sinh thật — khóa học chỉ là khung nội dung (8.1).</p>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        <form method="POST" action="{{ route('admin.courses.classes.store', $course->id) }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="code">Mã lớp</label>
                    <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="40"
                           placeholder="Ví dụ: 10CT-2026"
                           class="admin-input">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="name">Tên lớp</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255"
                           placeholder="Ví dụ: Lớp Chuyên Tin 10 - Khóa 2026"
                           class="admin-input">
                </div>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="schedule_note">Lịch học (tùy chọn)</label>
                <input id="schedule_note" name="schedule_note" type="text" value="{{ old('schedule_note') }}" maxlength="500"
                       placeholder="Ví dụ: Thứ 3 & 5, 19:00–20:30"
                       class="admin-input">
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="teacher_id">Giáo viên phụ trách</label>
                <x-admin.select id="teacher_id" name="teacher_id" icon="👩‍🏫">
                    <option value="">— Chưa phân công —</option>
                    @foreach ($teachers as $t)
                        <option value="{{ $t['id'] }}" @selected((string) old('teacher_id') === (string) $t['id'])>{{ $t['name'] }}</option>
                    @endforeach
                </x-admin.select>
                <p class="text-xs text-slate-400 mt-1">Chỉ hiện giáo viên đã được Admin duyệt (3.3).</p>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                <x-admin.select id="status" name="status" required>
                    <option value="active" @selected(old('status', 'active') === 'active')>Đang học</option>
                    <option value="archived" @selected(old('status') === 'archived')>Lưu trữ</option>
                </x-admin.select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Tạo lớp</button>
                <a href="{{ route('admin.courses.show', $course->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
