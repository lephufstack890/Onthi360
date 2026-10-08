@extends('layouts.admin')

@section('title', 'Tạo lớp học')
@section('page-title', 'Tạo lớp học')

@section('content')
    @php $teachers = $teachers ?? []; @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminCourses.jsx); tên field, route, validation giữ nguyên. --}}
    @include('partials.admin-courses-ui')

    <div class="acx-wrap">
    <a href="{{ route('admin.courses.show', $course->id) }}" class="acx-back">‹ Quay lại {{ $course->title }}</a>

    <div class="acx-head">
        <div class="acx-head__id">
            <span class="acx-avatar"><x-lucide name="graduation-cap" /></span>
            <div>
                <h1>Tạo lớp thuộc "{{ $course->title }}"</h1>
                <div class="acx-head__meta"><span>Lớp là nơi tổ chức lịch, giáo viên và học sinh thật — khóa học chỉ là khung nội dung (8.1).</span></div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="acx-card acx-card--white">
        <h2><x-lucide name="graduation-cap" /> Thông tin lớp học</h2>
        <form method="POST" action="{{ route('admin.courses.classes.store', $course->id) }}" class="acx-form">
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

                @include('partials.class-display-fields')

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="teacher_id">Giáo viên phụ trách</label>
                <x-ws.select id="teacher_id" name="teacher_id" icon="👩‍🏫">
                    <option value="">— Chưa phân công —</option>
                    @foreach ($teachers as $t)
                        <option value="{{ $t['id'] }}" @selected((string) old('teacher_id') === (string) $t['id'])>{{ $t['name'] }}</option>
                    @endforeach
                </x-ws.select>
                <p class="text-xs text-slate-400 mt-1">Chỉ hiện giáo viên đã được Admin duyệt (3.3).</p>
            </div>

            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                <x-ws.select id="status" name="status" required>
                    <option value="active" @selected(old('status', 'active') === 'active')>Đang học</option>
                    <option value="archived" @selected(old('status') === 'archived')>Lưu trữ</option>
                </x-ws.select>
            </div>

            <div class="acx-footer">
                <small>Thông tin có thể chỉnh lại sau khi tạo lớp.</small>
                <div class="acx-footer__btns">
                    <a href="{{ route('admin.courses.show', $course->id) }}" class="acx-btn">Huỷ</a>
                    <button type="submit" class="acx-btn acx-btn--primary">Tạo lớp</button>
                </div>
            </div>
        </form>
    </div>
    </div>
@endsection
