{{--
  Route: teacher.classes.create | Frame: TEA-02
  Spec: 8.1 (một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên, tiến độ).
  Dữ liệu thật ($courses) do App\Http\Controllers\Teacher\ClassRoomController truyền vào.
  "Lịch học" ở đây chỉ là ghi chú hiển thị (MVP) — lịch buổi học thật quản lý qua
  ClassSession riêng (tab Lịch/Điểm danh), chưa có UI tạo buổi học theo lịch định kỳ.
--}}
@extends('layouts.teacher')

@section('title', 'Tạo lớp mới')
@section('page-title', 'Tạo lớp mới')

@section('content')
    @php
        $courses = $courses ?? [];
    @endphp

    <a href="{{ route('teacher.classes.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Lớp học</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-center gap-4 flex-wrap">
        <x-icon-tile emoji="🏫" tone="rose" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Tạo lớp mới</h1>
            <p class="text-sm text-slate-500 mt-1">Bạn sẽ tự động là giáo viên chính của lớp này ngay sau khi tạo (7.2).</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 mb-6 text-sm text-rose-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if (empty($courses))
        <x-empty-state title="Chưa có khóa học nào để chọn"
                        description="Cần Admin tạo khóa học (đã phát hành) trước khi giáo viên tạo lớp thuộc khóa đó." />
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
                <form method="POST" action="{{ route('teacher.classes.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="course_id">Khóa học</label>
                        <x-select id="course_id" name="course_id" required icon="📚">
                            <option value="">— Chọn khóa học —</option>
                            @foreach ($courses as $course)
                                <option value="{{ $course['id'] }}" @selected(old('course_id') == $course['id'])>{{ $course['title'] }}</option>
                            @endforeach
                        </x-select>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã lớp</label>
                            <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="40"
                                   placeholder="Ví dụ: 10CT-2026" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                            <p class="text-xs text-slate-400 mt-1">Mã lớp phải là duy nhất trong toàn hệ thống.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Tên lớp</label>
                            <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255"
                                   placeholder="Ví dụ: Luyện thi vào 10 Chuyên Tin — Ca tối" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="schedule_note">Lịch học (ghi chú hiển thị, không bắt buộc)</label>
                        <input id="schedule_note" name="schedule_note" type="text" value="{{ old('schedule_note') }}" maxlength="500"
                               placeholder="Ví dụ: Thứ 3 & Thứ 5, 18h00–19h30" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                        <x-select id="status" name="status" required>
                            <option value="active" @selected(old('status', 'active') === 'active')>Đang hoạt động</option>
                            <option value="archived" @selected(old('status') === 'archived')>Lưu trữ</option>
                        </x-select>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Tạo lớp</button>
                        <a href="{{ route('teacher.classes.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Huỷ</a>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 space-y-4">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>💡</span> Cần biết</h3>
                <div class="flex items-start gap-3">
                    <x-icon-tile emoji="🔑" tone="sky" />
                    <p class="text-sm text-slate-500">Bạn tự động là giáo viên chính của lớp — có thể mời thêm giáo viên đồng phụ trách sau khi tạo.</p>
                </div>
                <div class="flex items-start gap-3">
                    <x-icon-tile emoji="🔤" tone="violet" />
                    <p class="text-sm text-slate-500">Mã lớp là duy nhất toàn hệ thống, nên dùng quy ước dễ nhận (VD: khối-năm học).</p>
                </div>
                <div class="flex items-start gap-3">
                    <x-icon-tile emoji="📅" tone="amber" />
                    <p class="text-sm text-slate-500">Lịch học ở đây chỉ là ghi chú hiển thị — lịch buổi học chi tiết quản lý ở tab Lịch/Điểm danh sau khi tạo lớp.</p>
                </div>
            </div>
        </div>
    @endif
@endsection
