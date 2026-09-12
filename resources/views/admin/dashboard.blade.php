@extends('layouts.admin')

@section('title', 'Tổng quan quản trị')
@section('page-title', 'Tổng quan')

@section('content')
    @php
        $stats = $stats ?? [];
        $activity = $activity ?? [];
    @endphp

    <x-ws.page-header title="Tổng quan" icon="layout-dashboard" subtitle="Số liệu vận hành theo thời gian thực (2.1, 16 mục 9)." />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
        @foreach ($stats as $s)
            <a href="{{ $s['href'] }}" class="block">
                <x-ws.stat :label="$s['label']" :value="$s['value']" :tone="$s['tone']" />
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h2 class="font-medium text-slate-700 mb-4">Hoạt động gần đây</h2>
            <ul class="space-y-3">
                @forelse ($activity as $a)
                    <li class="flex items-start gap-3 text-[13px]">
                        <span class="w-2 h-2 rounded-full bg-blue-500 mt-1.5 shrink-0"></span>
                        <div>
                            <p class="text-slate-700">{{ $a['text'] }}</p>
                            <p class="text-xs text-slate-400">{{ $a['time'] }} · {{ $a['actor'] }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-[13px] text-slate-400">Chưa có hoạt động nào được ghi nhận.</li>
                @endforelse
            </ul>
            {{-- TODO: link "Xem toàn bộ audit log" khi có màn audit log riêng --}}
        </div>

        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <h2 class="font-medium text-slate-700 mb-4">Thao tác nhanh</h2>
            <div class="space-y-2 text-[13px]">
                <a href="{{ route('admin.teacher-approvals.index') }}" class="block px-3 py-2 rounded-xl bg-slate-50 hover:bg-sky-50">Duyệt giáo viên</a>
                <a href="{{ route('admin.orders.index') }}" class="block px-3 py-2 rounded-xl bg-slate-50 hover:bg-sky-50">Duyệt đơn hàng</a>
                <a href="{{ route('admin.reviews.index') }}" class="block px-3 py-2 rounded-xl bg-slate-50 hover:bg-sky-50">Kiểm duyệt review</a>
                <a href="{{ route('admin.content.index') }}" class="block px-3 py-2 rounded-xl bg-slate-50 hover:bg-sky-50">Rà soát câu hỏi OCR</a>
            </div>
        </div>
    </div>
@endsection
