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

    {{-- Tài nguyên buổi học — note họp 13/8 mục 3: gắn riêng cho đúng buổi này, khác với "học
         liệu gắn cả lớp" ở tab Học liệu. SỬA 8/9 (6) — chỉ còn gắn được Bài giao (xem form bên
         dưới); danh sách vẫn hiện đủ mọi loại đã gắn từ trước. --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-5 mt-6">
        <h3 class="font-medium text-slate-700 mb-1">Tài nguyên buổi học</h3>
        <p class="text-xs text-slate-400 mb-3">Bài giao chuẩn bị riêng cho buổi này.</p>

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

        {{-- SỬA 8/9 (6) (khách: "loại tài nguyên chỉ cần để bài giao là được, còn lại xoá hết
             đi" + "xoá luôn field chọn học liệu, không cần") — form gắn tài nguyên giờ CHỈ còn
             đúng 1 loại: Bài giao (SessionResourceType::Assessment). Ô "Loại tài nguyên" rút từ
             6 lựa chọn xuống còn 1; đã bỏ hẳn ô nhập của 5 loại kia (Tài liệu, Câu hỏi, Video,
             Link, Ghi chú) — kéo theo bỏ luôn state Alpine `type` vì không còn gì để ẩn/hiện.
             GIỮ NGUYÊN phía sau: enum App\Enums\SessionResourceType và các nhánh xử lý từng
             loại ở Teacher\ScheduleService::addResource() — tài nguyên loại cũ đã gắn vào các
             buổi học TRƯỚC ĐÂY vẫn hiển thị đúng tên/nhãn ở danh sách phía trên và vẫn gỡ được;
             xoá enum sẽ làm các bản ghi cũ đó lỗi khi đọc ra. --}}
        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-4">
            <form method="POST" action="{{ route('teacher.schedule.resources.save', $session->id) }}" class="space-y-3">
                @csrf
                {{-- SỬA 8/9 (6b) (khách: "chọn bài giao chỉ cần để 1 loại bài giao, xong chọn đề
                     để giao") — giữ lại ô "Loại tài nguyên" nhưng chỉ còn DUY NHẤT 1 lựa chọn
                     "Bài giao", rồi mới tới ô chọn ĐỀ. Cố ý không thay ô này bằng input ẩn: giáo
                     viên vẫn cần nhìn thấy mình đang gắn loại gì vào buổi học. --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-slate-500" for="resource_type">Loại tài nguyên</label>
                        <x-select id="resource_type" name="type" class="mt-1 w-full">
                            <option value="assessment" selected>Bài giao</option>
                        </x-select>
                    </div>

                    <div>
                        <label class="text-xs text-slate-500" for="assessment_id">Chọn đề để giao</label>
                        <x-select id="assessment_id" name="assessment_id" class="mt-1 w-full">
                            @if (empty($assessmentOptions))
                                <option value="">— Bạn chưa tạo đề nào —</option>
                            @else
                                @foreach ($assessmentOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['title'] }}</option>
                                @endforeach
                            @endif
                        </x-select>
                    </div>
                </div>

                <button type="submit" @disabled(empty($assessmentOptions))
                        class="px-4 py-2 rounded-lg bg-rose-600 text-white text-xs font-medium disabled:opacity-60 disabled:cursor-not-allowed">Gắn vào buổi học</button>
            </form>
        </div>
    </div>
@endsection
