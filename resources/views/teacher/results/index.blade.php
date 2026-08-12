{{--
  Route: teacher.results.index | Frame: TEA-08
  Spec: 10.2 — phễu Lớp → Đề → Học sinh → Lần nộp; lọc chưa làm/đang
  làm/đã nộp; export theo chính sách.
  Dữ liệu thật do App\Http\Controllers\Teacher\ResultController truyền vào.
  TODO: export Excel thật; lọc theo trạng thái (hiện chỉ là UI).
--}}
@extends('layouts.teacher')

@section('title', 'Kết quả')
@section('page-title', 'Kết quả')

@section('content')
    @php
        $classRooms = $classRooms ?? collect();
        $assignments = $assignments ?? collect();
        $students = $students ?? collect();
        $stats = $stats ?? ['submitted' => 0, 'inProgress' => 0, 'notStarted' => 0];
    @endphp

    <x-page-header title="Kết quả" subtitle="Phễu Lớp → Đề → Học sinh → Lần nộp (10.2).">
        <x-slot:actions>
            <button type="button" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Xuất Excel</button>
        </x-slot:actions>
    </x-page-header>

    <form method="GET" action="{{ route('teacher.results.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <select name="class" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm p-2.5">
            @forelse ($classRooms as $c)
                <option value="{{ $c->id }}" @selected($selectedClassId == $c->id)>Lớp: {{ $c->name }}</option>
            @empty
                <option>Chưa có lớp nào</option>
            @endforelse
        </select>
        <select name="assessment" onchange="this.form.submit()" class="rounded-lg border border-slate-200 text-sm p-2.5">
            @forelse ($assignments as $a)
                <option value="{{ $a->id }}" @selected($selectedAssignmentId == $a->id)>Đề: {{ $a->assessment->title ?? 'Bài tập' }}</option>
            @empty
                <option>Chưa có đề nào giao cho lớp này</option>
            @endforelse
        </select>
        <select class="rounded-lg border border-slate-200 text-sm p-2.5">
            <option>Trạng thái: Tất cả</option>
            <option>Chưa làm</option>
            <option>Đang làm</option>
            <option>Đã nộp</option>
        </select>
    </form>

    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-stat-tile label="Đã nộp" value="{{ $stats['submitted'] }}/{{ $students->count() }}" tone="success" />
        <x-stat-tile label="Đang làm" value="{{ $stats['inProgress'] }}" tone="info" />
        <x-stat-tile label="Chưa làm" value="{{ $stats['notStarted'] }}" tone="warning" />
    </div>

    <x-data-table :columns="['Học sinh', 'Trạng thái', 'Điểm', 'Thời gian', '']">
        @forelse ($students as $s)
            <tr>
                <td class="px-4 py-3 font-medium text-slate-700">{{ $s['name'] }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$s['tone']">{{ $s['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-slate-600">{{ $s['score'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $s['time'] }}</td>
                <td class="px-4 py-3 text-right"><a href="#" class="text-rose-600 font-medium">Xem lần nộp</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chọn lớp và đề để xem kết quả.</td></tr>
        @endforelse
    </x-data-table>
@endsection
