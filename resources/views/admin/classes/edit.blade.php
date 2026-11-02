@extends('layouts.admin')

@section('title', 'Sửa lớp học')
@section('page-title', 'Sửa lớp học')

@section('content')
    @php
        $teachers = $teachers ?? [];
        $scheduleNote = $classRoom->schedule['note'] ?? null;
    @endphp

    {{-- SỬA 8/10 — đổi giao diện theo source mới (AdminCourses.jsx); tên field, route, validation giữ nguyên. --}}
    @include('partials.admin-courses-ui')

    <div class="acx-wrap">
    <a href="{{ route('admin.courses.show', $classRoom->course_id) }}" class="acx-back">‹ Quay lại {{ $classRoom->course->title ?? 'khóa học' }}</a>

    <div class="acx-head">
        <div class="acx-head__id">
            <span class="acx-avatar"><x-lucide name="graduation-cap" /></span>
            <div>
                <h1>Sửa lớp "{{ $classRoom->name }}"</h1>
                <div class="acx-head__meta">
                    <span class="acx-badge {{ $classRoom->status === 'active' ? 'acx-badge--ok' : '' }}">{{ $classRoom->status === 'active' ? 'Đang học' : 'Lưu trữ' }}</span>
                    <span>Mã lớp: {{ $classRoom->code }}</span>
                </div>
            </div>
        </div>
    </div>

    @if (session('status') === 'class-updated')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu thay đổi.'])
    @endif

    @if (in_array(session('status'), ['class-material-added', 'class-material-removed'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => (session('status') === 'class-material-added' ? 'Đã thêm tài liệu "' : 'Đã gỡ tài liệu "').session('class_material_title').(session('status') === 'class-material-added' ? '" vào lớp.' : '" khỏi lớp.')])
    @endif

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="acx-grid">
        <div class="acx-card acx-card--white">
            <h2><x-lucide name="graduation-cap" /> Thông tin lớp học</h2>
            <form method="POST" action="{{ route('admin.classes.update', $classRoom->id) }}" class="acx-form">
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

                @include('partials.class-display-fields')

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="teacher_id">Giáo viên phụ trách</label>
                    <x-ws.select id="teacher_id" name="teacher_id" icon="👩‍🏫">
                        <option value="">— Chưa phân công —</option>
                        @foreach ($teachers as $t)
                            <option value="{{ $t['id'] }}" @selected((string) old('teacher_id', $currentTeacherId) === (string) $t['id'])>{{ $t['name'] }}</option>
                        @endforeach
                    </x-ws.select>
                </div>

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                    <x-ws.select id="status" name="status" required>
                        <option value="active" @selected(old('status', $classRoom->status) === 'active')>Đang học</option>
                        <option value="archived" @selected(old('status', $classRoom->status) === 'archived')>Lưu trữ</option>
                    </x-ws.select>
                </div>

                <div class="acx-footer">
                    <small>Các thay đổi chỉ được lưu sau khi bấm "Lưu thay đổi".</small>
                    <div class="acx-footer__btns">
                        <a href="{{ route('admin.courses.show', $classRoom->course_id) }}" class="acx-btn">Huỷ</a>
                        <button type="submit" class="acx-btn acx-btn--primary">Lưu thay đổi</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- SỬA 3/11 (khách: "bỏ cái này đi") — bỏ khối "Xóa lớp học" khỏi trang sửa lớp (route admin.classes.destroy vẫn giữ). --}}
    </div>

    {{-- SỬA 3/11 (khách: "admin có thể add tài liệu vào lớp giùm giáo viên nếu giáo viên chưa mua") — partials/class-documents. --}}
    @include('admin.classes.partials.class-documents', ['cmx' => $classMaterials ?? null])
    </div>
@endsection
