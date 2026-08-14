{{--
  Route: teacher.schedule.attendance / .attendance.save / .schedule.summary.save
  Frame: TEA-01/02
  Spec: 8.2 — điểm danh từng buổi (có mặt/vắng/vắng có phép/đi trễ) + nhận xét đánh giá
  (mặc định 1 câu, sửa được) + "Em cần học thêm" + tổng kết buổi học. Dữ liệu thật do
  App\Http\Controllers\Teacher\ScheduleController truyền vào qua ScheduleService.
  Dòng "Tự động" (source=auto) là học sinh tự vào làm bài lúc buổi học đang diễn ra,
  App\Services\AttemptService::autoCheckIn() đã điểm danh sẵn — giáo viên chỉ cần rà lại
  nhận xét, không bắt buộc phải sửa trạng thái Có mặt/Vắng của các dòng này.
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
    @if (session('status') === 'summary-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu tổng kết buổi học.'])
    @endif
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('teacher.schedule.attendance.save', $session->id) }}">
        @csrf
        <x-data-table :columns="['Học sinh', 'Có mặt', 'Vắng', 'Vắng có phép', 'Đi trễ', 'Nhận xét', 'Em cần học thêm']">
            @forelse ($rows as $r)
                <tr class="hover:bg-slate-50 align-top">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($r['name']) }}&background=e0f2fe&color=0369a1&size=64&bold=true"
                                 alt="{{ $r['name'] }}" class="w-8 h-8 rounded-full shrink-0">
                            <div>
                                <span class="font-medium text-slate-700">{{ $r['name'] }}</span>
                                @if ($r['source'] === 'auto')
                                    <span class="block text-[11px] text-emerald-600 font-medium mt-0.5">✓ Tự động (đã vào làm bài)</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    @foreach (['present' => 'Có mặt', 'absent' => 'Vắng', 'excused' => 'Vắng có phép', 'late' => 'Đi trễ'] as $value => $label)
                        <td class="px-4 py-3 text-center">
                            <input type="radio" name="status[{{ $r['studentId'] }}]" value="{{ $value }}" @checked($r['status'] === $value) class="accent-rose-600">
                        </td>
                    @endforeach
                    <td class="px-4 py-3">
                        <textarea name="note[{{ $r['studentId'] }}]" rows="2"
                                  class="w-48 rounded-lg border border-slate-200 text-xs p-2 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ $r['note'] }}</textarea>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <input type="checkbox" name="needs_more_practice[{{ $r['studentId'] }}]" value="1" @checked($r['needsMorePractice']) class="accent-amber-500 w-4 h-4">
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Lớp này chưa có học sinh nào.</td></tr>
            @endforelse
        </x-data-table>

        @if (count($rows) > 0)
            <div class="mt-4">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu điểm danh</button>
            </div>
        @endif
    </form>

    <div class="bg-white rounded-2xl border border-slate-200 p-5 mt-6">
        <h3 class="font-medium text-slate-700 mb-2">Tổng kết buổi học</h3>
        <form method="POST" action="{{ route('teacher.schedule.summary.save', $session->id) }}">
            @csrf
            <textarea name="summary" rows="4" placeholder="Buổi học hôm nay đã dạy gì, học sinh tiếp thu ra sao, cần lưu ý gì cho buổi sau..."
                      class="w-full rounded-lg border border-slate-200 text-sm p-3 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ $session->summary }}</textarea>
            <button type="submit" class="mt-3 px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-300 transition">Lưu tổng kết</button>
        </form>
    </div>
@endsection
