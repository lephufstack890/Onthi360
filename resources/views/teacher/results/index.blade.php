@extends('layouts.teacher')

@section('title', 'Kết quả')
@section('page-title', 'Kết quả')

@section('content')
    @php
        $classRooms = $classRooms ?? collect();
        $assignments = $assignments ?? collect();
        $students = $students ?? collect();
        $stats = $stats ?? ['submitted' => 0, 'inProgress' => 0, 'notStarted' => 0];
        $selectedStatus = $selectedStatus ?? '';
        $exportQuery = ['class' => $selectedClassId, 'assessment' => $selectedAssignmentId, 'status' => $selectedStatus];
    @endphp

    <x-page-header title="Kết quả" subtitle="Phễu Lớp → Đề → Học sinh → Lần nộp (10.2).">
        <x-slot:actions>
            <a href="{{ route('teacher.results.export', $exportQuery) }}" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">⇩ Xuất CSV</a>
        </x-slot:actions>
    </x-page-header>

    <div class="bg-white rounded-2xl border border-slate-200 p-4 mb-6">
        <form method="GET" action="{{ route('teacher.results.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <x-select name="class" onchange="this.form.submit()">
                @forelse ($classRooms as $c)
                    <option value="{{ $c->id }}" @selected($selectedClassId == $c->id)>🏫 Lớp: {{ $c->name }}</option>
                @empty
                    <option>Chưa có lớp nào</option>
                @endforelse
            </x-select>
            <x-select name="assessment" onchange="this.form.submit()">
                @forelse ($assignments as $a)
                    <option value="{{ $a->id }}" @selected($selectedAssignmentId == $a->id)>📝 Đề: {{ $a->assessment->title ?? 'Bài tập' }}</option>
                @empty
                    <option>Chưa có đề nào giao cho lớp này</option>
                @endforelse
            </x-select>
            <x-select name="status" onchange="this.form.submit()">
                <option value="" @selected($selectedStatus === '')>Trạng thái: Tất cả</option>
                <option value="Chưa làm" @selected($selectedStatus === 'Chưa làm')>Chưa làm</option>
                <option value="Đang làm" @selected($selectedStatus === 'Đang làm')>Đang làm</option>
                <option value="Đã nộp" @selected($selectedStatus === 'Đã nộp')>Đã nộp</option>
            </x-select>
        </form>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <x-stat-tile label="Đã nộp" value="{{ $stats['submitted'] }}/{{ $students->count() }}" tone="success" />
        <x-stat-tile label="Đang làm" :value="$stats['inProgress']" tone="info" />
        <x-stat-tile label="Chưa làm" :value="$stats['notStarted']" tone="warning" />
    </div>

    <x-data-table :columns="['Học sinh', 'Trạng thái', 'Điểm', 'Thời gian', '']">
        @forelse ($students as $s)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-3">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($s['name']) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                             alt="{{ $s['name'] }}" class="w-8 h-8 rounded-full shrink-0">
                        <span class="font-medium text-slate-700">{{ $s['name'] }}</span>
                    </div>
                </td>
                <td class="px-4 py-3"><x-status-badge :tone="$s['tone']">{{ $s['status'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-slate-600">{{ $s['score'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $s['time'] }}</td>
                <td class="px-4 py-3 text-right">
                    @if ($s['attemptId'])
                        <a href="{{ route('teacher.results.attempt', $s['attemptId']) }}" class="text-rose-600 font-medium">Xem lần nộp</a>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chọn lớp và đề để xem kết quả.</td></tr>
        @endforelse
    </x-data-table>
@endsection
