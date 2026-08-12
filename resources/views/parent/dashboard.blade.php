{{--
  Route: parent.dashboard | Frame: PAR-01
  Spec: 10.3 — dashboard phụ huynh ưu tiên: lịch học gần nhất, điểm danh,
  bài sắp hạn, tiến độ, kết quả mới công bố, nhận xét/thông báo. Không có
  browse theo tên để tìm trẻ em khác.
  TODO controller: truyền $children = auth()->user()->childLinks đã xác minh.
--}}
@extends('layouts.parent')

@section('title', 'Tổng quan')
@section('page-title', 'Tổng quan')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Parent\DashboardController truyền vào. --}}
    @php
        $children = $children ?? [];
        $recentResults = $recentResults ?? [];
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-violet-100 via-white to-rose-50 p-6 lg:p-8 mb-6">
        <p class="text-sm text-violet-600 font-medium">Chào mừng trở lại 👋</p>
        <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">Đồng hành cùng con mỗi ngày</h2>
        <p class="text-sm text-slate-500 mt-1">Chỉ hiển thị dữ liệu của con đã liên kết và xác minh (10.3).</p>
    </div>

    @forelse ($children as $child)
        <div class="rounded-2xl bg-white border border-slate-200 p-5 mb-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-violet-200 to-rose-100 flex items-center justify-center font-medium text-slate-700">
                        {{ mb_substr($child['name'], 0, 1) }}
                    </div>
                    <div>
                        <p class="font-medium text-slate-700">{{ $child['name'] }}</p>
                        <p class="text-xs text-slate-400">Lớp {{ $child['class'] }}</p>
                    </div>
                </div>
                <a href="{{ route('parent.children.show', $child['id']) }}" class="text-sm text-rose-600 font-medium">Xem chi tiết ›</a>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-stat-tile label="Buổi học tới" :value="$child['nextSession']" tone="info" />
                <x-stat-tile label="Điểm danh" :value="$child['attendance']" tone="success" />
                <div class="rounded-2xl bg-white border border-slate-200 p-5">
                    <x-progress-bar :percent="$child['progress']" label="Tiến độ lớp" tone="brand" />
                </div>
            </div>
        </div>
    @empty
        <x-empty-state title="Chưa liên kết con nào" description="Tạo yêu cầu liên kết để bắt đầu theo dõi lịch học, điểm danh và kết quả của con." actionLabel="Liên kết con" :actionHref="route('parent.children.index')" />
    @endforelse

    <div class="bg-white rounded-2xl border border-slate-200 p-5">
        <h3 class="font-medium text-slate-700 mb-4">Kết quả mới công bố</h3>
        <ul class="space-y-3">
            @forelse ($recentResults as $r)
                <li class="flex items-center justify-between text-sm">
                    <div>
                        <p class="text-slate-700">{{ $r['title'] }}</p>
                        <p class="text-xs text-slate-400">{{ $r['child'] }} · {{ $r['time'] }}</p>
                    </div>
                    <x-status-badge :tone="$r['tone']">{{ $r['score'] }}</x-status-badge>
                </li>
            @empty
                <li class="text-sm text-slate-400">Chưa có kết quả nào được công bố.</li>
            @endforelse
        </ul>
    </div>
@endsection
