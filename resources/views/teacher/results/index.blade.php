{{--
  Route: teacher.results.index | Frame: TEA-08
  Spec: 10.2 — phễu Lớp → Đề → Học sinh → Lần nộp; lọc chưa làm/đang
  làm/đã nộp; export theo chính sách.
  TODO controller: truyền $classes/$assessments/$students theo phễu đã chọn.
--}}
@extends('layouts.teacher')

@section('title', 'Kết quả')
@section('page-title', 'Kết quả')

@section('content')
    @php
        $students = [
            ['name' => 'Trần Thị B', 'status' => 'Đã nộp', 'score' => '9/10', 'tone' => 'success', 'time' => '2 ngày trước'],
            ['name' => 'Trần Văn D', 'status' => 'Chưa làm', 'score' => '—', 'tone' => 'neutral', 'time' => '—'],
            ['name' => 'Ngô Thị E', 'status' => 'Đang làm', 'score' => '—', 'tone' => 'info', 'time' => 'Đang mở'],
        ];
    @endphp

    <x-page-header title="Kết quả" subtitle="Phễu Lớp → Đề → Học sinh → Lần nộp (10.2).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Xuất Excel</button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <select class="rounded-lg border border-slate-200 text-sm p-2.5">
            <option>Lớp: 10CT-2026</option>
            <option>Lớp: 11HSG-2026</option>
        </select>
        <select class="rounded-lg border border-slate-200 text-sm p-2.5">
            <option>Đề: Đề ôn chương 3</option>
            <option>Đề: Trắc nghiệm chương 2</option>
        </select>
        <select class="rounded-lg border border-slate-200 text-sm p-2.5">
            <option>Trạng thái: Tất cả</option>
            <option>Chưa làm</option>
            <option>Đang làm</option>
            <option>Đã nộp</option>
        </select>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-stat-tile label="Đã nộp" value="24/32" tone="success" />
        <x-stat-tile label="Đang làm" value="3" tone="info" />
        <x-stat-tile label="Chưa làm" value="5" tone="warning" />
    </div>

    <x-data-table :columns="['Học sinh', 'Trạng thái', 'Điểm', 'Thời gian', '']">
        @foreach ($students as $s)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $s['name'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$s['tone']">{{ $s['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-slate-600">{{ $s['score'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $s['time'] }}</td>
                <td class="px-4 py-3 text-right"><a href="#" class="text-rose-600 font-medium">Xem lần nộp</a></td>
            </tr>
        @endforeach
    </x-data-table>
@endsection
