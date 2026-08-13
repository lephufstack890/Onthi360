{{--
  Route: teacher.assessments.index | Frame: TEA-04
  Spec: 6.3 (đề trộn nhiều kiểu câu) + 8.4 (Giao đề: state Nháp → Đã lên lịch →
  Đang mở → Đã đóng → Đã lưu trữ, ở cấp Assignment sau khi giao cho lớp).
  Dữ liệu thật do App\Services\Teacher\AssessmentService::listForTeacher() truyền vào.
--}}
@extends('layouts.teacher')

@section('title', 'Bài tập & Đề')
@section('page-title', 'Bài tập & Đề')

@section('content')
    @php
        $assessments = $assessments ?? [];
        $classRooms = $classRooms ?? [];
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-violet-100 via-white to-sky-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shrink-0 shadow-sm">🧾</div>
            <div>
                <h1 class="text-xl lg:text-2xl font-semibold text-slate-800">Bài tập & Đề</h1>
                <p class="text-sm text-slate-500 mt-1">Đề do bạn tự tạo từ kho câu hỏi riêng — trộn được nhiều kiểu câu (6.3).</p>
            </div>
        </div>
        <a href="{{ route('teacher.assessments.create') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm shrink-0">+ Tạo đề mới</a>
    </div>

    @if (session('status') === 'assessment-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu nháp đề.'])
    @elseif (session('status') === 'assessment-published')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã phát hành đề.'])
    @elseif (session('status') === 'assessment-assigned')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã giao đề cho lớp.'])
    @elseif (session('status') === 'assessment-created-not-assigned')
        @include('partials.toast-flash', ['type' => 'warning', 'message' => 'Đã lưu đề nhưng chưa giao được cho lớp — xem lỗi bên dưới.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <x-data-table :columns="['Tên đề', 'Số câu', 'Tổng điểm', 'Đã giao', 'Trạng thái', '']">
        @forelse ($assessments as $a)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $a['title'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $a['itemsCount'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $a['totalPoints'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $a['assignmentsCount'] }} lớp</td>
                <td class="px-4 py-3"><x-status-badge :tone="$a['tone']">{{ $a['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right">
                    <div x-data="{ open: false }" class="inline-block text-left">
                        <div class="space-x-3">
                            @if ($a['canPublish'])
                                <form method="POST" action="{{ route('teacher.assessments.publish', $a['id']) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-emerald-600 font-medium">Phát hành</button>
                                </form>
                            @endif
                            @if (! empty($classRooms))
                                <button type="button" @click="open = !open" class="text-rose-600 font-medium" x-text="open ? 'Đóng' : 'Giao cho lớp'"></button>
                            @endif
                        </div>
                        <form x-show="open" x-cloak method="POST" action="{{ route('teacher.assessments.assign', $a['id']) }}" class="mt-2 space-y-2 text-left bg-slate-50 border border-slate-200 rounded-lg p-3 w-72">
                            @csrf
                            <x-select name="class_room_id" required>
                                <option value="">— Chọn lớp —</option>
                                @foreach ($classRooms as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </x-select>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="datetime-local" name="opens_at" class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Mở lúc">
                                <input type="datetime-local" name="closes_at" class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Đóng lúc">
                            </div>
                            <textarea name="instructions" rows="2" class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Hướng dẫn làm bài (tùy chọn)..."></textarea>
                            <button type="submit" class="w-full px-3 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-medium">Xác nhận giao đề (8.4)</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có đề nào — bấm "+ Tạo đề mới" để bắt đầu.</td></tr>
        @endforelse
    </x-data-table>
@endsection
