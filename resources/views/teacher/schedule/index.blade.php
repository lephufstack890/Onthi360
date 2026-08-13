{{--
  Route: teacher.schedule.index | Frame: TEA-01/02
  Spec: 8.2 "Lớp học: ... lịch, điểm danh, thông báo" — lịch buổi học xuyên TẤT CẢ các lớp
  giáo viên phụ trách, sắp tới trước. Dữ liệu thật do
  App\Http\Controllers\Teacher\ScheduleController truyền vào qua ScheduleService.
--}}
@extends('layouts.teacher')

@section('title', 'Lịch')
@section('page-title', 'Lịch')

@section('content')
    @php
        $classRooms = $classRooms ?? collect();
        $upcoming = $upcoming ?? [];
        $past = $past ?? [];
    @endphp

    <x-page-header title="Lịch" subtitle="Buổi học của mọi lớp bạn phụ trách." />

    @if (session('status') === 'session-created')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã tạo buổi học mới.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-5 mb-6">
        <p class="text-sm font-medium text-slate-600 mb-3">+ Tạo buổi học mới</p>
        <form method="POST" action="{{ route('teacher.schedule.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3">
            @csrf
            <x-select name="class_room_id" required>
                <option value="">Chọn lớp</option>
                @foreach ($classRooms as $c)
                    <option value="{{ $c->id }}" @selected(old('class_room_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </x-select>
            <input type="datetime-local" name="starts_at" required class="rounded-lg border border-slate-200 text-sm p-2.5">
            <input type="datetime-local" name="ends_at" required class="rounded-lg border border-slate-200 text-sm p-2.5">
            <input type="text" name="topic" maxlength="255" placeholder="Chủ đề buổi học" class="rounded-lg border border-slate-200 text-sm p-2.5">
            <input type="text" name="location" maxlength="255" placeholder="Địa điểm/link" class="rounded-lg border border-slate-200 text-sm p-2.5">
            <div class="sm:col-span-5">
                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Tạo buổi học</button>
            </div>
        </form>
    </div>

    <p class="text-sm font-semibold text-slate-700 mb-2">Sắp tới</p>
    <x-data-table :columns="['Lớp', 'Thời gian', 'Chủ đề', 'Điểm danh', '']">
        @forelse ($upcoming as $s)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $s['className'] }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $s['startsAt']?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $s['topic'] ?? '—' }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$s['attendanceTaken'] ? 'success' : 'neutral'">{{ $s['attendanceSummary'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="text-rose-600 font-medium">Điểm danh</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có buổi học sắp tới.</td></tr>
        @endforelse
    </x-data-table>

    <p class="text-sm font-semibold text-slate-700 mt-6 mb-2">Đã qua</p>
    <x-data-table :columns="['Lớp', 'Thời gian', 'Chủ đề', 'Điểm danh', '']">
        @forelse ($past as $s)
            <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 font-medium text-slate-700">{{ $s['className'] }}</td>
                <td class="px-4 py-3 text-slate-600">{{ $s['startsAt']?->format('d/m/Y H:i') ?? '—' }}</td>
                <td class="px-4 py-3 text-slate-400">{{ $s['topic'] ?? '—' }}</td>
                <td class="px-4 py-3"><x-status-badge :tone="$s['attendanceTaken'] ? 'success' : 'warning'">{{ $s['attendanceSummary'] }}</x-status-badge></td>
                <td class="px-4 py-3 text-right"><a href="{{ route('teacher.schedule.attendance', $s['id']) }}" class="text-rose-600 font-medium">Điểm danh</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Chưa có buổi học nào trong quá khứ.</td></tr>
        @endforelse
    </x-data-table>
@endsection
