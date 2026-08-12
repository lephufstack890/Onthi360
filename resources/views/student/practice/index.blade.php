{{--
  Route: student.practice.index | Frame: STU-04
  Spec: 10.1 — Tabs Tự luyện · Theo lớp · Bài được giao · Đã lưu · Lịch sử.
  Bộ lọc: môn, khối, chuyên đề, độ khó, loại câu/đề, trạng thái, quyền.
  TODO controller: truyền $items theo tab + filter thật.
--}}
@extends('layouts.student')

@section('title', 'Luyện tập')
@section('page-title', 'Luyện tập')

@section('content')
    @php
        $tab = request('tab', 'self');
        $tabs = [
            ['label' => 'Tự luyện', 'href' => route('student.practice.index'), 'active' => $tab === 'self', 'count' => 240],
            ['label' => 'Theo lớp', 'href' => route('student.practice.index', ['tab' => 'class']), 'active' => $tab === 'class', 'count' => 12],
            ['label' => 'Bài được giao', 'href' => route('student.practice.index', ['tab' => 'assigned']), 'active' => $tab === 'assigned', 'count' => 3],
            ['label' => 'Đã lưu', 'href' => route('student.practice.index', ['tab' => 'saved']), 'active' => $tab === 'saved', 'count' => 5],
            ['label' => 'Lịch sử', 'href' => route('student.practice.index', ['tab' => 'history']), 'active' => $tab === 'history', 'count' => 58],
        ];

        $items = [
            ['title' => 'Bài 12: Đệ quy cơ bản', 'type' => 'Lập trình', 'source' => 'Lớp 10CT-2026', 'difficulty' => 'Trung bình', 'status' => 'Chưa làm', 'tone' => 'info'],
            ['title' => 'Trắc nghiệm chương 2', 'type' => 'Trắc nghiệm', 'source' => 'Tự luyện', 'difficulty' => 'Dễ', 'status' => 'Đang làm dở', 'tone' => 'warning'],
            ['title' => 'Đề ôn chương 3', 'type' => 'Hỗn hợp', 'source' => 'Lớp 10CT-2026', 'difficulty' => 'Khó', 'status' => 'Đã nộp — 9/10', 'tone' => 'success'],
        ];
    @endphp

    <x-page-header title="Luyện tập" subtitle="Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3)." />

    <x-tabs :tabs="$tabs" />

    <div class="flex flex-wrap gap-2 mb-6 text-sm">
        <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Lập trình</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Trắc nghiệm</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Điền đáp án</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Độ khó</button>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($items as $it)
            <div class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md transition">
                <x-status-badge tone="info">{{ $it['type'] }}</x-status-badge>
                <h3 class="font-medium text-slate-800 mt-2">{{ $it['title'] }}</h3>
                <p class="text-xs text-slate-400 mt-1">{{ $it['source'] }} · Độ khó: {{ $it['difficulty'] }}</p>
                <div class="mt-3 flex items-center justify-between">
                    <x-status-badge :tone="$it['tone']">{{ $it['status'] }}</x-status-badge>
                    <a href="{{ route('student.assessment.take', 1) }}" class="text-sm font-medium text-rose-600">Mở ›</a>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <x-empty-state title="Chưa có bài phù hợp bộ lọc" description="Thử bỏ bộ lọc hoặc khám phá thêm bài luyện tập công khai." actionLabel="Khám phá Luyện tập công khai" :actionHref="route('practice.index')" />
            </div>
        @endforelse
    </div>
@endsection
