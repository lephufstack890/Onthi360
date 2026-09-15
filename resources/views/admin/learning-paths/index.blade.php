@extends('layouts.admin')

@section('title', 'Lộ trình học')
@section('page-title', 'Lộ trình học')

@section('content')
{{-- ═══════ A7 · DANH SÁCH LỘ TRÌNH ═══════
     Lộ trình → nhiều Khoá học (mỗi khoá là một "bậc") → nhiều Lớp học.
     Dữ liệu do App\Services\Admin\LearningPathService::indexData() trả về. --}}
@php
    $paths = $paths ?? [];
    $stats = $stats ?? ['total' => 0, 'published' => 0, 'draft' => 0, 'needsAttention' => 0];
@endphp

@if (session('status') === 'path-deleted')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã xoá lộ trình.'])
@elseif (session('status') === 'path-status-changed')
    @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã đổi trạng thái hiển thị.'])
@endif

@if ($errors->any())
    @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
@endif

<x-ws.page-header title="Lộ trình học" icon="route"
                  subtitle="Mỗi lộ trình gồm nhiều bậc, mỗi bậc là một khoá học, mỗi khoá học có nhiều lớp.">
    <x-slot:actions>
        <x-ws.btn :href="route('admin.learning-paths.create')" variant="onhero" icon="plus">Thêm lộ trình</x-ws.btn>
        <x-ws.btn :href="route('admin.courses.index')" variant="onhero-ghost" icon="graduation-cap">Khoá &amp; Lớp</x-ws.btn>
    </x-slot:actions>
</x-ws.page-header>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
    <x-ws.stat label="Tổng lộ trình" :value="$stats['total']" tone="blue" icon="route" hint="Tính cả bản nháp" />
    <x-ws.stat label="Đang hiển thị" :value="$stats['published']" tone="emerald" icon="check-circle-2" hint="Khách xem được ở ngoài" />
    <x-ws.stat label="Bản nháp" :value="$stats['draft']" tone="amber" icon="pen-line" hint="Chưa hiện ra ngoài" />
    <x-ws.stat label="Cần xem lại" :value="$stats['needsAttention']"
               :tone="$stats['needsAttention'] > 0 ? 'rose' : 'neutral'" icon="alert-triangle"
               :hint="$stats['needsAttention'] > 0 ? 'Thiếu số buổi, mã bậc hoặc lớp' : 'Không có lộ trình nào thiếu'" />
</div>

@if (count($paths) === 0)
    <x-ws.empty-state icon="route" title="Chưa có lộ trình nào"
                      description="Tạo lộ trình đầu tiên, sau đó xếp các khoá học thành từng bậc."
                      action-label="Thêm lộ trình" :action-href="route('admin.learning-paths.create')" />
@else
    {{-- SỬA 15/9 — cột "Ngôn ngữ" ẩn theo yêu cầu khách, xem LearningPathService::SHOW_LANGUAGE. --}}
    <x-ws.table :columns="['Lộ trình', 'Khối lớp', 'Mục tiêu', 'Quy mô', 'Trạng thái', '']" min-width="min-w-[1040px]">
        @foreach ($paths as $p)
            <tr>
                <td class="px-4 py-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        @if ($p['coverUrl'])
                            <img src="{{ $p['coverUrl'] }}" alt="" loading="lazy" decoding="async"
                                 class="h-9 w-14 shrink-0 rounded-lg border border-sky-100 object-cover">
                        @else
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl border border-sky-100 bg-[#F8FBFE] text-blue-600">
                                <x-lucide name="route" class="h-4 w-4" />
                            </span>
                        @endif
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-bold text-slate-800">{{ $p['title'] }}</p>
                            @if ($p['brand'])
                                <p class="mt-0.5 text-[11px] font-bold uppercase tracking-wide text-slate-400">{{ $p['brand'] }}</p>
                            @endif
                        </div>
                    </div>
                </td>

                <td class="px-4 py-3">
                    <span class="inline-flex flex-col gap-1">
                        <x-ws.badge tone="neutral">{{ $p['gradeLabel'] }}</x-ws.badge>
                        @if (\App\Services\Admin\LearningPathService::SHOW_LANGUAGE)
                            <x-ws.badge :tone="$p['languageTone']">{{ $p['language'] }}</x-ws.badge>
                        @endif
                    </span>
                </td>

                <td class="max-w-xs px-4 py-3">
                    <p class="text-[12px] leading-relaxed text-slate-600">{{ $p['goal'] }}</p>
                </td>

                <td class="px-4 py-3">
                    <p class="text-[13px] font-bold text-slate-700">{{ $p['stepCount'] }} bậc</p>
                    {{-- Tổng buổi và tổng tuần tự tính từ các bậc, không lưu cột riêng. --}}
                    <p class="mt-0.5 text-[11px] text-slate-400">{{ number_format($p['totalSessions']) }} buổi · ~{{ $p['totalWeeks'] }} tuần</p>
                </td>

                <td class="px-4 py-3">
                    <x-ws.badge :tone="$p['statusTone']">{{ $p['statusLabel'] }}</x-ws.badge>
                    @if ($p['issueCount'] > 0)
                        <p class="mt-1 inline-flex items-center gap-1 text-[11px] font-bold {{ $p['hasBlocker'] ? 'text-rose-600' : 'text-amber-600' }}">
                            <x-lucide name="alert-triangle" class="h-3 w-3" />{{ $p['issueCount'] }} điểm cần xem
                        </p>
                    @endif
                </td>

                <td class="px-4 py-3">
                    <div class="flex items-center justify-end gap-1.5">
                        <x-ws.btn :href="$p['stepsHref']" size="sm" variant="primary" icon="list">Xếp bậc</x-ws.btn>
                        <x-ws.btn :href="$p['editHref']" size="sm" variant="soft" icon="pencil">Sửa</x-ws.btn>
                    </div>
                </td>
            </tr>
        @endforeach
    </x-ws.table>

    <x-ws.pagination-note :shown="count($paths)" :total="count($paths)" />
@endif
@endsection
