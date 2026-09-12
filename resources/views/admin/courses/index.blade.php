@extends('layouts.admin')

@section('title', 'Khóa & Lớp')
@section('page-title', 'Khóa & Lớp')

@section('content')
    @php
        $tab = $tab ?? 'courses';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
    @endphp

    @if (in_array(session('status'), ['course-created', 'course-deleted'], true))
        @include('partials.toast-flash', ['type' => 'success', 'message' => session('status') === 'course-created' ? 'Đã tạo khóa học mới.' : 'Đã xóa khóa học (xóa mềm, đã ghi lý do).'])
    @endif

    <x-admin.page-header title="Khóa & Lớp" icon="graduation-cap" subtitle="Một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên và tiến độ (8.1).">
        @if ($tab === 'courses')
            <x-slot:actions>
                <a href="{{ route('admin.courses.create') }}" class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-white px-4 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50 shadow-sm hover:bg-blue-700 transition">+ Tạo khóa học</a>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <x-admin.tabs :tabs="$tabs" />

    <x-admin.table :columns="['Tên', 'Thông tin', 'Trạng thái', '']">
        @forelse ($rows as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['meta'] }}</td>
                <td class="px-4 py-3"><x-admin.badge :tone="$r['tone']">{{ $r['status'] }}</x-admin.badge></td>
                <td class="px-4 py-3 text-right">
                    @if ($tab === 'courses')
                        <a href="{{ route('admin.courses.show', $r['id']) }}" class="text-blue-600 font-medium">Xem</a>
                    @else
                        <a href="{{ route('admin.classes.edit', $r['id']) }}" class="text-blue-600 font-medium">Sửa</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu.</td></tr>
        @endforelse
    </x-admin.table>
@endsection
