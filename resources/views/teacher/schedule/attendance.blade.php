{{--
  Route: teacher.schedule.attendance / .attendance.save | Frame: TEA-01/02
  Spec: 8.2 — điểm danh từng buổi (có mặt/vắng/vắng có phép/đi trễ). Dữ liệu thật do
  App\Http\Controllers\Teacher\ScheduleController truyền vào qua ScheduleService.
--}}
@extends('layouts.teacher')

@section('title', 'Điểm danh')
@section('page-title', 'Điểm danh')

@section('content')
    @php $rows = $rows ?? []; @endphp

    <a href="{{ route('teacher.schedule.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Lịch</a>

    <x-page-header title="Điểm danh" subtitle="{{ $classRoom->name ?? '' }} — {{ $session->starts_at?->format('d/m/Y H:i') ?? '' }}{{ $session->topic ? ' · '.$session->topic : '' }}" />

    @if (session('status') === 'attendance-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu điểm danh.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('teacher.schedule.attendance.save', $session->id) }}">
        @csrf
        <x-data-table :columns="['Học sinh', 'Có mặt', 'Vắng', 'Vắng có phép', 'Đi trễ']">
            @forelse ($rows as $r)
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($r['name']) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                                 alt="{{ $r['name'] }}" class="w-8 h-8 rounded-full shrink-0">
                            <span class="font-medium text-slate-700">{{ $r['name'] }}</span>
                        </div>
                    </td>
                    @foreach (['present' => 'Có mặt', 'absent' => 'Vắng', 'excused' => 'Vắng có phép', 'late' => 'Đi trễ'] as $value => $label)
                        <td class="px-4 py-3 text-center">
                            <input type="radio" name="status[{{ $r['studentId'] }}]" value="{{ $value }}" @checked($r['status'] === $value) class="accent-rose-600">
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Lớp này chưa có học sinh nào.</td></tr>
            @endforelse
        </x-data-table>

        @if (count($rows) > 0)
            <div class="mt-4">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu điểm danh</button>
            </div>
        @endif
    </form>
@endsection
