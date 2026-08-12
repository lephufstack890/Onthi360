{{--
  Route: teacher.classes.show | Frame: TEA-02 (chi tiết) + TEA-06 (học liệu)
  Spec: 8.2/8.3 (gắn học liệu, lộ trình mở theo chương/mục/mã bài) +
  7.2 (quyền dạy đa lớp — nhãn "Dùng được ở mọi lớp phụ trách đến DD/MM/YYYY").
  TODO controller: truyền $classRoom thật; tab "materials" cần
  App\Services\AccessGateService/TeacherAttachMaterialAction để biết học
  liệu nào giáo viên còn quyền dạy.
--}}
@extends('layouts.teacher')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Chi tiết lớp')

@section('content')
    @php
        $classId = request()->route('class', 10);
        $tab = request('tab', 'overview');
        $tabDefs = ['overview' => 'Tổng quan', 'materials' => 'Học liệu', 'schedule' => 'Lịch/Điểm danh', 'assign' => 'Giao đề', 'results' => 'Kết quả', 'members' => 'Thành viên'];
        $tabsData = [];
        foreach ($tabDefs as $key => $label) {
            $tabsData[] = ['label' => $label, 'href' => route('teacher.classes.show', ['class' => $classId, 'tab' => $key]), 'active' => $tab === $key];
        }

        $materials = [
            ['title' => 'Sách: Ôn thi Tin học 10', 'scope' => 'Dùng được ở mọi lớp phụ trách đến 18/08/2026', 'tone' => 'warning', 'linkedStatus' => 'Đang dùng'],
            ['title' => 'Chuyên đề: Cấu trúc dữ liệu nâng cao', 'scope' => 'Dùng được ở mọi lớp phụ trách đến 30/06/2027', 'tone' => 'success', 'linkedStatus' => 'Đang dùng'],
        ];
    @endphp

    <a href="{{ route('teacher.classes.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Lớp học</a>

    <div class="rounded-3xl bg-gradient-to-br from-sky-50 via-white to-emerald-50 border border-slate-200 p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-sky-600 uppercase tracking-wide">Luyện thi vào 10 Chuyên Tin</p>
                <h1 class="text-xl font-semibold text-slate-800 mt-1">10CT-2026</h1>
                <p class="text-sm text-slate-500 mt-1">32 học sinh · Buổi tới: Hôm nay 19:00</p>
            </div>
            <div class="w-40"><x-progress-bar :percent="62" label="Hoàn thành chung" tone="info" /></div>
        </div>
    </div>

    <x-tabs :tabs="$tabsData" />

    @if ($tab === 'materials')
        <div class="flex flex-wrap gap-2 mb-4 text-sm">
            <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Có thể dùng ở mọi lớp phụ trách</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Sắp hết hạn</button>
            <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Đã hết hạn</button>
        </div>

        <div class="space-y-3">
            @foreach ($materials as $m)
                <div class="bg-white rounded-2xl border border-slate-200 p-4 flex items-center justify-between flex-wrap gap-3">
                    <div>
                        <p class="font-medium text-slate-700">{{ $m['title'] }}</p>
                        <p class="text-xs mt-1"><x-status-badge :tone="$m['tone']">{{ $m['scope'] }}</x-status-badge></p>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <x-status-badge tone="success">{{ $m['linkedStatus'] }}</x-status-badge>
                        <button type="button" class="text-slate-500">Xem trước như học sinh</button>
                        <button type="button" class="text-slate-500">Mở theo chương/mục ▾</button>
                        <button type="button" class="text-rose-500">Gỡ</button>
                    </div>
                </div>
            @endforeach

            <button type="button" class="w-full rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-4 hover:border-rose-300 hover:text-rose-500">
                + Thêm học liệu vào lớp (chỉ hiện học liệu bạn còn quyền dạy)
            </button>
        </div>
    @elseif ($tab === 'schedule')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">TODO: lịch buổi học + bảng điểm danh từng buổi (có/vắng/xin phép).</p>
        </div>
    @elseif ($tab === 'assign')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500 mb-3">Giao đề dùng cho kiểm tra có thời điểm mở-đóng, hạn nộp riêng (8.4) — không phải cách hiển thị bài thường nhật.</p>
            <a href="{{ route('teacher.assessments.create') }}" class="text-sm text-rose-600 font-medium">+ Tạo bài giao đánh giá mới ›</a>
        </div>
    @elseif ($tab === 'results')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <a href="{{ route('teacher.results.index') }}" class="text-sm text-rose-600 font-medium">Xem kết quả chi tiết theo lớp này ›</a>
        </div>
    @elseif ($tab === 'members')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">TODO: danh sách 32 học sinh + trạng thái quyền cá nhân từng em.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Rating summary nội bộ</h3>
                <x-rating-summary :average="4.8" :count="21" />
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Buổi học gần nhất</h3>
                <p class="text-sm text-slate-500">Hôm nay 19:00 — Cấu trúc dữ liệu nâng cao</p>
            </div>
        </div>
    @endif
@endsection
