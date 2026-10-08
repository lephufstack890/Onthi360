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

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="acx-grid acx-grid--main">
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

        <div class="acx-danger" x-data="{ open: false, reason: '' }">
            <h3><x-lucide name="alert-triangle" /> Xóa lớp học</h3>
            <p>Xóa VĨNH VIỄN lớp cùng mọi dữ liệu của lớp (học viên ghi danh, buổi học, điểm danh, bài giao, bài làm của học sinh, đánh giá). Không thể khôi phục. Lý do là tùy chọn, nếu nhập sẽ được ghi vào nhật ký hệ thống.</p>

            <button type="button" @click="open = !open" class="acx-danger__toggle" x-text="open ? 'Đóng' : 'Tôi muốn xóa lớp này'"></button>

            <form x-show="open" x-cloak method="POST" action="{{ route('admin.classes.destroy', $classRoom->id) }}" class="" onsubmit="return confirm('Xóa VĨNH VIỄN lớp này cùng mọi dữ liệu của lớp? Không thể khôi phục.');">
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
@endsection
