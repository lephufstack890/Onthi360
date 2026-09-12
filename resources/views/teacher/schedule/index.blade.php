@extends('layouts.teacher')

@section('title', 'Lịch')
@section('page-title', 'Lịch')

@section('content')
    @php
        $classRooms = $classRooms ?? collect();
        $upcoming = $upcoming ?? [];
        $past = $past ?? [];
    @endphp

    <x-ws.page-header title="Lịch" subtitle="Buổi học của mọi lớp bạn phụ trách." />

    @if (session('status') === 'session-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo buổi học mới.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-3xl border border-sky-100 p-5 mb-6">
        <p class="text-[13px] font-medium text-slate-600 mb-3">+ Tạo buổi học mới</p>
        <form method="POST" action="{{ route('teacher.schedule.store') }}" x-data="{ startsDate: '{{ old('starts_date', '') }}', endsDate: '{{ old('ends_date', '') }}' }" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="class_room_id">Lớp</label>
                    <x-ws.select id="class_room_id" name="class_room_id" required>
                        <option value="">Chọn lớp</option>
                        @foreach ($classRooms as $c)
                            <option value="{{ $c->id }}" @selected(old('class_room_id') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="topic">Chủ đề buổi học</label>
                    <input id="topic" type="text" name="topic" maxlength="255" value="{{ old('topic') }}" placeholder="Ví dụ: Ôn tập chương 3" class="admin-input">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1" for="location">Địa điểm/link</label>
                    <input id="location" type="text" name="location" maxlength="255" value="{{ old('location') }}" placeholder="Phòng học hoặc link online" class="admin-input">
                </div>
            </div>

            @include('partials.session-datetime-fields')

            <div>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Tạo buổi học</button>
            </div>
        </form>
    </div>

    <p class="text-[13px] font-semibold text-slate-700 mb-2">Sắp tới</p>
    <x-ws.table :columns="['Lớp', 'Thời gian (bắt đầu - kết thúc)', 'Chủ đề', 'Trạng thái', 'Điểm danh', '']">
        @forelse ($upcoming as $s)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $s['className'] }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $s['timeRangeLabel'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $s['topic'] ?? '—' }}</td>
                <td class="px-4 py-3"><x-ws.badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-ws.badge></td>
                <td class="px-4 py-3"><x-ws.badge :tone="$s['attendanceTaken'] ? 'success' : 'neutral'">{{ $s['attendanceSummary'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="text-blue-600 font-medium">Điểm danh</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có buổi học sắp tới.</td></tr>
        @endforelse
    </x-ws.table>

    <p class="text-[13px] font-semibold text-slate-700 mt-6 mb-2">Đã qua</p>
    <x-ws.table :columns="['Lớp', 'Thời gian (bắt đầu - kết thúc)', 'Chủ đề', 'Trạng thái', 'Điểm danh', '']">
        @forelse ($past as $s)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $s['className'] }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $s['timeRangeLabel'] }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $s['topic'] ?? '—' }}</td>
                <td class="px-4 py-3"><x-ws.badge :tone="$s['timeStatusTone']">{{ $s['timeStatusLabel'] }}</x-ws.badge></td>
                <td class="px-4 py-3"><x-ws.badge :tone="$s['attendanceTaken'] ? 'success' : 'warning'">{{ $s['attendanceSummary'] }}</x-ws.badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="text-blue-600 font-medium">Điểm danh</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">Chưa có buổi học nào trong quá khứ.</td></tr>
        @endforelse
    </x-ws.table>
@endsection
