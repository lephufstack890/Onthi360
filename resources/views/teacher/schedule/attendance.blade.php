@extends('layouts.teacher')

@section('title', 'Điểm danh')
@section('page-title', 'Điểm danh')

@section('content')
    @php
        $rows = $rows ?? [];
        $sessionResources = $sessionResources ?? [];
        $materialOptions = $materialOptions ?? [];
        $questionOptions = $questionOptions ?? [];
        $assessmentOptions = $assessmentOptions ?? [];
    @endphp

    <a href="{{ route('teacher.schedule.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Lịch</a>

    <x-page-header title="Điểm danh" subtitle="{{ $classRoom->name ?? '' }} — {{ $session->starts_at?->format('d/m/Y H:i') ?? '' }}{{ $session->topic ? ' · '.$session->topic : '' }}" />

    @if (session('status') === 'attendance-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu điểm danh.'])
    @elseif (session('status') === 'summary-saved')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã lưu tổng kết buổi học.'])
    @elseif (session('status') === 'resource-added')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gắn tài nguyên vào buổi học.'])
    @elseif (session('status') === 'resource-removed')
        @include('partials.toast-flash', ['type' => 'success', 'message' => 'Đã gỡ tài nguyên khỏi buổi học.'])
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

    {{-- Tài nguyên buổi học — note họp 13/8 mục 3: gắn tài liệu/câu hỏi/đề thi/video/link
         riêng cho đúng buổi này, khác với "học liệu gắn cả lớp" ở tab Học liệu. --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mt-6">
        <h3 class="font-medium text-slate-700 mb-1">Tài nguyên buổi học</h3>
        <p class="text-xs text-slate-400 mb-3">Tài liệu, câu hỏi, đề thi, video, link… chuẩn bị riêng cho buổi này.</p>

        <div class="space-y-2 mb-4">
            @forelse ($sessionResources as $res)
                <div class="flex items-center justify-between gap-3 bg-slate-50 rounded-lg px-3 py-2">
                    <div class="min-w-0 flex items-center gap-2">
                        <x-status-badge tone="info">{{ $res['typeLabel'] }}</x-status-badge>
                        <div class="min-w-0">
                            @if ($res['url'])
                                <a href="{{ $res['url'] }}" target="_blank" rel="noopener" class="text-sm text-rose-600 truncate hover:underline">{{ $res['title'] }}</a>
                            @else
                                <p class="text-sm text-slate-700 truncate">{{ $res['title'] }}</p>
                            @endif
                            @if ($res['note'])
                                <p class="text-xs text-slate-400 truncate">{{ $res['note'] }}</p>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('teacher.schedule.resources.delete', ['session' => $session->id, 'resource' => $res['id']]) }}" class="inline shrink-0">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-rose-500 text-xs">Gỡ</button>
                    </form>
                </div>
            @empty
                <div class="rounded-2xl border-2 border-dashed border-slate-200 text-slate-400 text-sm py-4 text-center">
                    Buổi học này chưa gắn tài nguyên nào.
                </div>
            @endforelse
        </div>

        <div x-data="{ type: 'material' }" class="rounded-2xl border-2 border-dashed border-slate-200 p-4">
            <form method="POST" action="{{ route('teacher.schedule.resources.save', $session->id) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="text-xs text-slate-500">Loại tài nguyên</label>
                    <x-select name="type" x-model="type" class="mt-1 w-full sm:w-64">
                        <option value="material">Tài liệu (đã gắn lớp)</option>
                        <option value="question">Câu hỏi (của tôi)</option>
                        <option value="assessment">Đề thi / bài tập (của tôi)</option>
                        <option value="video">Video</option>
                        <option value="link">Link</option>
                        <option value="note">Ghi chú</option>
                    </x-select>
                </div>

                <div x-show="type === 'material'">
                    <label class="text-xs text-slate-500">Chọn tài liệu</label>
                    <x-select name="material_id" class="mt-1 w-full">
                        @if (empty($materialOptions))
                            <option value="">— Lớp chưa gắn tài liệu nào (xem tab Học liệu) —</option>
                        @else
                            @foreach ($materialOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['title'] }}</option>
                            @endforeach
                        @endif
                    </x-select>
                </div>

                <div x-show="type === 'question'">
                    <label class="text-xs text-slate-500">Chọn câu hỏi</label>
                    <x-select name="question_id" class="mt-1 w-full">
                        @if (empty($questionOptions))
                            <option value="">— Bạn chưa có câu hỏi đã phát hành nào (xem Kho câu hỏi) —</option>
                        @else
                            @foreach ($questionOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['title'] }}</option>
                            @endforeach
                        @endif
                    </x-select>
                </div>

                <div x-show="type === 'assessment'">
                    <label class="text-xs text-slate-500">Chọn đề thi / bài tập</label>
                    <x-select name="assessment_id" class="mt-1 w-full">
                        @if (empty($assessmentOptions))
                            <option value="">— Bạn chưa tạo đề thi/bài tập nào —</option>
                        @else
                            @foreach ($assessmentOptions as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['title'] }}</option>
                            @endforeach
                        @endif
                    </x-select>
                </div>

                <div x-show="type === 'video' || type === 'link' || type === 'note'" class="space-y-2">
                    <div>
                        <label class="text-xs text-slate-500">Tiêu đề</label>
                        <input type="text" name="title" maxlength="255" placeholder="VD: Video ôn tập chương 2"
                               class="mt-1 w-full rounded-lg border border-slate-200 text-sm p-2">
                    </div>
                    <div x-show="type === 'video' || type === 'link'">
                        <label class="text-xs text-slate-500">Link</label>
                        <input type="url" name="url" maxlength="2048" placeholder="https://..."
                               class="mt-1 w-full rounded-lg border border-slate-200 text-sm p-2">
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Ghi chú (tuỳ chọn)</label>
                        <input type="text" name="note" maxlength="1000"
                               class="mt-1 w-full rounded-lg border border-slate-200 text-sm p-2">
                    </div>
                </div>

                <button type="submit" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-xs font-medium">Gắn vào buổi học</button>
            </form>
        </div>
    </div>
@endsection
