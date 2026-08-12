{{--
  Route: admin.courses.index | "Khóa & Lớp" trong sidebar (4.2)
  Spec: 8.1 (Khóa học khác Lớp học).
  TODO controller: truyền $courses / $classes theo tab.
--}}
@extends('layouts.admin')

@section('title', 'Khóa & Lớp')
@section('page-title', 'Khóa & Lớp')

@section('content')
    {{-- Dữ liệu thật do App\Http\Controllers\Admin\CourseController truyền vào. --}}
    @php
        $tab = $tab ?? 'courses';
        $tabs = $tabs ?? [];
        $rows = $rows ?? [];
    @endphp

    <x-page-header title="🏫 Khóa & Lớp" subtitle="Một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên và tiến độ (8.1).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo khóa học</button>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên', 'Thông tin', 'Trạng thái', '']">
        @forelse ($rows as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['meta'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="#" class="text-rose-600 font-medium">Xem</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Chưa có dữ liệu.</td></tr>
        @endforelse
    </x-data-table>
@endsection
