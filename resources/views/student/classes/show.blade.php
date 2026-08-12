{{--
  Route: student.classes.show | Frame: STU-03
  Spec: 8.3 (header + tabs: Tổng quan · Lộ trình & Bài tập · Lịch học ·
  Tài liệu · Đánh giá · Thông báo · Thành viên). Mỗi bài có nguồn, loại,
  thời điểm mở, hạn nếu có, trạng thái cá nhân, kết quả gần nhất, lý do khóa.
  TODO controller: truyền $classRoom thật + $roadmap items với AccessDecision
  từ App\Services\AccessGateService cho mỗi bài (7.3).
--}}
@extends('layouts.student')

@section('title', 'Chi tiết lớp')
@section('page-title', 'Chi tiết lớp')

@section('content')
    @php
        $tab = request('tab', 'overview');
        $tabs = [
            ['label' => 'Tổng quan', 'key' => 'overview'],
            ['label' => 'Lộ trình & Bài tập', 'key' => 'roadmap'],
            ['label' => 'Lịch học', 'key' => 'schedule'],
            ['label' => 'Tài liệu', 'key' => 'materials'],
            ['label' => 'Đánh giá', 'key' => 'reviews'],
            ['label' => 'Thông báo', 'key' => 'notifications'],
            ['label' => 'Thành viên', 'key' => 'members'],
        ];
        $tabsData = array_map(fn ($t) => [
            'label' => $t['label'],
            'href' => route('student.classes.show', ['class' => request()->route('class', 10), 'tab' => $t['key']]),
            'active' => $tab === $t['key'],
        ], $tabs);

        $roadmap = [
            ['chapter' => 'Chương 1: Nhập môn', 'items' => [
                ['title' => 'Bài 1: Biến và kiểu dữ liệu', 'type' => 'Lập trình', 'status' => 'Đã mở', 'tone' => 'success', 'result' => 'Accepted'],
                ['title' => 'Bài 2: Cấu trúc điều khiển', 'type' => 'Trắc nghiệm', 'status' => 'Đã mở', 'tone' => 'success', 'result' => '9/10'],
            ]],
            ['chapter' => 'Chương 2: Hàm và đệ quy', 'items' => [
                ['title' => 'Bài 3: Hàm cơ bản', 'type' => 'Lập trình', 'status' => 'Đã mở', 'tone' => 'success', 'result' => 'Chưa làm'],
                ['title' => 'Bài 12: Đệ quy cơ bản', 'type' => 'Lập trình', 'status' => 'Vừa mở', 'tone' => 'info', 'result' => 'Chưa làm'],
                ['title' => 'Bài 13: Đệ quy nâng cao', 'type' => 'Lập trình', 'status' => 'Giáo viên chưa mở', 'tone' => 'neutral', 'result' => null],
            ]],
        ];
    @endphp

    <a href="{{ route('student.courses.index') }}" class="text-sm text-slate-500 mb-4 inline-block">‹ Quay lại Khóa học của tôi</a>

    {{-- Header lớp --}}
    <div class="rounded-3xl bg-gradient-to-br from-sky-100 via-white to-rose-50 border border-slate-200 p-6 mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-medium text-sky-600 uppercase tracking-wide">Luyện thi vào 10 Chuyên Tin</p>
                <h1 class="text-xl font-semibold text-slate-800 mt-1">10CT-2026</h1>
                <p class="text-sm text-slate-500 mt-1">GV Nguyễn Văn A · Buổi tới: Hôm nay 19:00</p>
                <div class="mt-2"><x-rating-summary :average="4.8" :count="21" /></div>
            </div>
            <div class="w-40">
                <x-progress-bar :percent="62" label="Tiến độ chung" tone="brand" />
            </div>
        </div>
    </div>

    <x-tabs :tabs="$tabsData" />

    @if ($tab === 'roadmap')
        <div class="space-y-6">
            @foreach ($roadmap as $chap)
                <div>
                    <h3 class="font-medium text-slate-700 mb-3">{{ $chap['chapter'] }}</h3>
                    <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
                        @foreach ($chap['items'] as $item)
                            <div class="flex items-center justify-between p-4">
                                <div class="flex items-center gap-3">
                                    <x-icon-tile emoji="{{ $item['type'] === 'Lập trình' ? '💻' : '📝' }}" tone="{{ $item['tone'] === 'neutral' ? 'amber' : 'sky' }}" />
                                    <div>
                                        <p class="text-sm font-medium text-slate-700">{{ $item['title'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $item['type'] }} · <x-status-badge :tone="$item['tone']">{{ $item['status'] }}</x-status-badge></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    @if ($item['status'] === 'Giáo viên chưa mở')
                                        <span class="text-xs text-slate-400">🔒 Giáo viên chưa mở nội dung này</span>
                                    @else
                                        <a href="{{ route('student.assessment.take', 1) }}" class="text-sm font-medium text-rose-600">
                                            {{ $item['result'] === 'Chưa làm' ? 'Làm bài ›' : 'Xem lại ›' }}
                                        </a>
                                        @if ($item['result'] && $item['result'] !== 'Chưa làm')
                                            <p class="text-xs text-slate-400 mt-0.5">{{ $item['result'] }}</p>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @elseif ($tab === 'schedule')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">TODO: lịch buổi học dạng calendar/list + trạng thái điểm danh.</p>
        </div>
    @elseif ($tab === 'materials')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">TODO: danh sách học liệu đã gắn lớp (sách/chuyên đề), trạng thái quyền cá nhân (7.3).</p>
        </div>
    @elseif ($tab === 'reviews')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500 mb-3">Bạn đủ điều kiện đánh giá lớp này sau khi tham gia 2 buổi (9.2).</p>
            <a href="{{ route('reviews.form', ['type' => 'class', 'id' => 10]) }}" class="text-sm text-rose-600 font-medium">Viết đánh giá ›</a>
        </div>
    @elseif ($tab === 'notifications')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">TODO: thông báo riêng của lớp (bài mới mở, lịch đổi, thông báo giáo viên).</p>
        </div>
    @elseif ($tab === 'members')
        <div class="bg-white rounded-2xl border border-slate-200 p-5">
            <p class="text-sm text-slate-500">TODO: danh sách thành viên — học sinh không thấy toàn bộ nếu không cần (8.3).</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Buổi học gần nhất</h3>
                <p class="text-sm text-slate-500">Hôm nay 19:00 — Cấu trúc dữ liệu nâng cao</p>
            </div>
            <div class="bg-white rounded-2xl border border-slate-200 p-5">
                <h3 class="font-medium text-slate-700 mb-2">Tiến độ tổng quan</h3>
                <x-progress-bar :percent="62" tone="brand" />
            </div>
        </div>
    @endif
@endsection
