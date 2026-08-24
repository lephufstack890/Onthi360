@extends('layouts.admin')

@section('title', 'Sửa cuộc thi')
@section('page-title', 'Sửa cuộc thi')

@section('content')
    @php
        $types = $types ?? []; $assessmentOptions = $assessmentOptions ?? [];
        $organizerTypes = $organizerTypes ?? []; $teacherOptions = $teacherOptions ?? [];
        $selectedAdvisorIds = $selectedAdvisorIds ?? [];
        $rankingRule = $competition->ranking_rule ?? [];
        $statusLabels = [
            'upcoming' => 'Sắp diễn ra', 'ongoing' => 'Đang diễn ra', 'pending_publish' => 'Chờ công bố',
            'published' => 'Đã công bố', 'archived' => 'Lưu trữ',
        ];
        $computedStatusValue = $competition->computedStatus()->value;
    @endphp

    <a href="{{ route('admin.competitions.show', $competition->id) }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại chi tiết</a>

    <x-page-header title="✏️ Sửa cuộc thi" :subtitle="$competition->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200 p-6">
            <form method="POST" action="{{ route('admin.competitions.update', $competition->id) }}" class="space-y-4"
                  x-data="{ organizerType: '{{ old('organizer_type', $competition->organizer_type->value) }}' }">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên cuộc thi</label>
                        <input id="title" name="title" type="text" value="{{ old('title', $competition->title) }}" required maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại</label>
                        <x-select id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $competition->type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                </div>

                <div class="rounded-lg bg-amber-50 border border-amber-100 p-4 space-y-3">
                    <p class="text-sm font-medium text-amber-700">Đơn vị tổ chức</p>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="organizer_type">Cuộc thi do ai tổ chức?</label>
                        <x-select id="organizer_type" name="organizer_type" x-model="organizerType" required>
                            @foreach ($organizerTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('organizer_type', $competition->organizer_type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select>
                    </div>
                    <div x-show="organizerType === 'external'" x-cloak>
                        <label class="block text-xs text-slate-500 mb-1" for="organizer_name">Tên đơn vị tổ chức</label>
                        <input id="organizer_name" name="organizer_name" type="text" value="{{ old('organizer_name', $competition->organizer_name) }}" maxlength="255"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">

                        <label class="block text-xs text-slate-500 mb-1 mt-3">Giáo viên cố vấn/đồng hành (bắt buộc ≥1 — tăng uy tín cho cuộc thi bên ngoài)</label>
                        <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 space-y-1">
                            @forelse ($teacherOptions as $t)
                                <label class="flex items-center gap-2 text-sm px-2 py-1 rounded hover:bg-amber-50 cursor-pointer">
                                    <input type="checkbox" name="advisor_teacher_ids[]" value="{{ $t['id'] }}" @checked(in_array($t['id'], old('advisor_teacher_ids', $selectedAdvisorIds)))>
                                    {{ $t['name'] }}
                                </label>
                            @empty
                                <p class="text-xs text-slate-400 px-2 py-1">Chưa có giáo viên nào được duyệt để chọn làm cố vấn.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="assessment_id">Đề/bộ bài tham chiếu (tùy chọn)</label>
                    <x-select id="assessment_id" name="assessment_id">
                        <option value="">— Không gắn đề —</option>
                        @foreach ($assessmentOptions as $a)
                            <option value="{{ $a->id }}" @selected((string) old('assessment_id', $competition->assessment_id) === (string) $a->id)>{{ $a->title }}</option>
                        @endforeach
                    </x-select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="rules">Thể lệ</label>
                    <textarea id="rules" name="rules" rows="4" maxlength="5000"
                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('rules', $competition->rules) }}</textarea>
                </div>

                {{--
                  TẠM ẨN 24/8: Khách hiện không cần sửa Bắt đầu/Kết thúc/Công bố kết quả/xem
                  Trạng thái ở form sửa cuộc thi (đang thừa) — comment lại (KHÔNG xoá) để sau
                  này cần dùng lại thì chỉ cần bỏ comment, không phải viết lại từ đầu. Không
                  đổi field name/logic bên trong. $statusLabels/$computedStatusValue ở phần
                  khai báo đầu file vẫn giữ nguyên, không dùng tới nữa nhưng không ảnh hưởng gì.

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <x-date-time-fields name="starts_at" label="Bắt đầu"
                                         :dayValue="old('starts_at_day', $competition->starts_at?->format('d'))"
                                         :monthValue="old('starts_at_month', $competition->starts_at?->format('m'))"
                                         :yearValue="old('starts_at_year', $competition->starts_at?->format('Y'))"
                                         :hourValue="old('starts_at_hour', $competition->starts_at?->format('H'))"
                                         :minuteValue="old('starts_at_minute', $competition->starts_at?->format('i'))" />
                    <x-date-time-fields name="ends_at" label="Kết thúc"
                                         :dayValue="old('ends_at_day', $competition->ends_at?->format('d'))"
                                         :monthValue="old('ends_at_month', $competition->ends_at?->format('m'))"
                                         :yearValue="old('ends_at_year', $competition->ends_at?->format('Y'))"
                                         :hourValue="old('ends_at_hour', $competition->ends_at?->format('H'))"
                                         :minuteValue="old('ends_at_minute', $competition->ends_at?->format('i'))" />
                    <x-date-time-fields name="publish_result_at" label="Công bố kết quả"
                                         :dayValue="old('publish_result_at_day', $competition->publish_result_at?->format('d'))"
                                         :monthValue="old('publish_result_at_month', $competition->publish_result_at?->format('m'))"
                                         :yearValue="old('publish_result_at_year', $competition->publish_result_at?->format('Y'))"
                                         :hourValue="old('publish_result_at_hour', $competition->publish_result_at?->format('H'))"
                                         :minuteValue="old('publish_result_at_minute', $competition->publish_result_at?->format('i'))"
                                         hint="&quot;Chờ công bố&quot; không lộ rank tạm thời nếu quy chế cấm (11.2)." />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1">Trạng thái</label>
                    <p class="text-sm text-slate-700">{{ $statusLabels[$computedStatusValue] ?? $computedStatusValue }}</p>
                    <p class="text-xs text-slate-400 mt-1">🔄 Tự tính theo lịch Bắt đầu/Kết thúc/Công bố kết quả — không chọn tay. Muốn "Lưu trữ" thì dùng nút riêng ở trang chi tiết.</p>
                </div>
                --}}

                <div class="rounded-lg bg-sky-50 border border-sky-100 p-4 space-y-3">
                    <p class="text-sm font-medium text-sky-700">Quy tắc bảng xếp hạng (11.2)</p>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="scoring_note">Công thức điểm / kỳ tính</label>
                        <input id="scoring_note" name="scoring_note" type="text" value="{{ old('scoring_note', $rankingRule['scoring_note'] ?? '') }}" maxlength="500"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="penalty_note">Penalty</label>
                        <input id="penalty_note" name="penalty_note" type="text" value="{{ old('penalty_note', $rankingRule['penalty_note'] ?? '') }}" maxlength="500"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="tie_break_note">Quy tắc đồng điểm</label>
                        <input id="tie_break_note" name="tie_break_note" type="text" value="{{ old('tie_break_note', $rankingRule['tie_break_note'] ?? '') }}" maxlength="500"
                               class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.competitions.show', $competition->id) }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-2xl border border-rose-200 p-6 space-y-3">
            @if ($competition->status->value === 'archived')
                {{-- SỬA 19/8: "Lưu trữ" trước đây là hành động MỘT CHIỀU tuyệt đối — bấm nhầm là
                     kẹt cứng, sửa lại Bắt đầu/Kết thúc phía trên cũng KHÔNG có tác dụng đổi
                     trạng thái nữa (xem docblock CompetitionService::unarchive()). Giờ thêm nút
                     này để tự mở lại được, không cần sửa DB tay. --}}
                <h3 class="font-medium text-rose-700 flex items-center gap-2"><span>🗄️</span> Đã lưu trữ</h3>
                <p class="text-sm text-slate-500">Cuộc thi đang ở trạng thái "Lưu trữ" — sửa Bắt đầu/Kết thúc ở form bên trên sẽ KHÔNG tự đổi trạng thái, phải bấm "Bỏ lưu trữ" trước.</p>
                <form method="POST" action="{{ route('admin.competitions.unarchive', $competition->id) }}">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium">Bỏ lưu trữ (tính lại trạng thái theo giờ)</button>
                </form>
            @else
                <h3 class="font-medium text-rose-700 flex items-center gap-2"><span>🗄️</span> Lưu trữ cuộc thi</h3>
                <p class="text-sm text-slate-500">Không xóa dữ liệu — chỉ chuyển trạng thái "Lưu trữ" (11.1), khớp bước cuối vòng đời cuộc thi. Sau khi lưu trữ, sửa lại ngày giờ sẽ KHÔNG tự mở lại — phải bấm "Bỏ lưu trữ".</p>
                <form method="POST" action="{{ route('admin.competitions.archive', $competition->id) }}" onsubmit="return confirm('Xác nhận lưu trữ cuộc thi này?');">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu trữ cuộc thi</button>
                </form>
            @endif
        </div>
    </div>
@endsection
