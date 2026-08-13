{{--
  Route: admin.courses.classes.create / admin.courses.classes.store
  Spec: 8.1 (lớp thuộc khóa) + 7.2 (giáo viên phụ trách).
  Dữ liệu thật ($course, $teachers) do CourseController::classesCreate() truyền vào
  qua App\Services\Admin\CourseService::classCreateFormData(). $teachers chỉ gồm
  giáo viên ĐÃ ĐƯỢC DUYỆT (3.3) — chưa duyệt thì chưa thể đứng lớp thật.
--}}
@extends('layouts.admin')

@section('title', 'Tạo lớp học')
@section('page-title', 'Tạo lớp học')

@section('content')
    @php $teachers = $teachers ?? []; @endphp

    <a href="{{ route('admin.courses.show', $course->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại {{ $course->title }}</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 p-6 lg:p-8 mb-6 flex items-center gap-4 flex-wrap">
        <x-icon-tile emoji="🏫" tone="sky" />
        <div>
            <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Tạo lớp thuộc "{{ $course->title }}"</h1>
            <p class="text-sm text-slate-500 mt-1">Lớp là nơi tổ chức lịch, giáo viên và học sinh thật — khóa học chỉ là khung nội dung (8.1).</p>
        </div>
    </div>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <form method="POST" action="{{ route('admin.courses.classes.store', $course->id) }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="code">Mã lớp</label>
                    <input id="code" name="code" type="text" value="{{ old('code') }}" required maxlength="40"
                           placeholder="Ví dụ: 10CT-2026"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="name">Tên lớp</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required maxlength="255"
                           placeholder="Ví dụ: Lớp Chuyên Tin 10 - Khóa 2026"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="schedule_note">Lịch học (tùy chọn)</label>
                <input id="schedule_note" name="schedule_note" type="text" value="{{ old('schedule_note') }}" maxlength="500"
                       placeholder="Ví dụ: Thứ 3 & 5, 19:00–20:30"
                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="teacher_id">Giáo viên phụ trách</label>
                <x-select id="teacher_id" name="teacher_id" icon="👩‍🏫">
                    <option value="">— Chưa phân công —</option>
                    @foreach ($teachers as $t)
                        <option value="{{ $t['id'] }}" @selected((string) old('teacher_id') === (string) $t['id'])>{{ $t['name'] }}</option>
                    @endforeach
                </x-select>
                <p class="text-xs text-slate-400 mt-1">Chỉ hiện giáo viên đã được Admin duyệt (3.3).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="status">Trạng thái</label>
                <x-select id="status" name="status" required>
                    <option value="active" @selected(old('status', 'active') === 'active')>Đang học</option>
                    <option value="archived" @selected(old('status') === 'archived')>Lưu trữ</option>
                </x-select>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo lớp</button>
                <a href="{{ route('admin.courses.show', $course->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
