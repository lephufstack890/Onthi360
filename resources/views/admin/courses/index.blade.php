{{--
  Route: admin.courses.index | "Khóa & Lớp" trong sidebar (4.2)
  Spec: 8.1 (Khóa học khác Lớp học).
  TODO controller: truyền $courses / $classes theo tab.
--}}
@extends('layouts.admin')

@section('title', 'Khóa & Lớp')
@section('page-title', 'Khóa & Lớp')

@section('content')
    @php
        $tab = request('tab', 'courses');
        $tabs = [
            ['label' => 'Khóa học', 'href' => route('admin.courses.index'), 'active' => $tab === 'courses', 'count' => 28],
            ['label' => 'Lớp học', 'href' => route('admin.courses.index', ['tab' => 'classes']), 'active' => $tab === 'classes', 'count' => 64],
        ];
        $rows = $tab === 'courses'
            ? [
                ['id' => 1, 'name' => 'Luyện thi vào 10 Chuyên Tin', 'meta' => '5 lớp đang triển khai', 'status' => 'Đang mở', 'tone' => 'success'],
                ['id' => 2, 'name' => 'Ôn thi HSG Tin 11', 'meta' => '2 lớp đang triển khai', 'status' => 'Đang mở', 'tone' => 'success'],
            ]
            : [
                ['id' => 10, 'name' => '10CT-2026 (Luyện thi vào 10 Chuyên Tin)', 'meta' => 'GV Nguyễn Văn A · 32 học sinh', 'status' => 'Đang học', 'tone' => 'success'],
                ['id' => 11, 'name' => '11HSG-2026 (Ôn thi HSG Tin 11)', 'meta' => 'GV Lê Văn C · 18 học sinh', 'status' => 'Đang học', 'tone' => 'success'],
            ];
    @endphp

    <x-page-header title="Khóa & Lớp" subtitle="Một khóa học có thể có nhiều lớp; lớp là nơi tổ chức lịch, học viên và tiến độ (8.1).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">+ Tạo khóa học</button>
        </x-slot:actions>
    </x-page-header>

    <x-tabs :tabs="$tabs" />

    <x-data-table :columns="['Tên', 'Thông tin', 'Trạng thái', '']">
        @foreach ($rows as $r)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $r['name'] }}</td>
                <td class="px-4 py-3 text-slate-500">{{ $r['meta'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$r['tone']">{{ $r['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="#" class="text-rose-600 font-medium">Xem</a></td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
