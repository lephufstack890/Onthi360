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

    <a href="{{ route('admin.competitions.show', $competition->id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết</a>

    <x-ws.page-header title="Sửa cuộc thi" icon="pencil" :subtitle="$competition->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    {{-- SỬA 24/10 (khách: "khi sửa là lưu cái mới luôn, không có lưu trữ hay tính lại theo giờ") — bỏ khối Lưu trữ bên phải, form chiếm hết bề ngang. --}}
    <div>
        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
            <form method="POST" action="{{ route('admin.competitions.update', $competition->id) }}" class="space-y-4" enctype="multipart/form-data"
                  x-data="{ organizerType: '{{ old('organizer_type', $competition->organizer_type->value) }}', type: '{{ old('type', $competition->type->value) }}' }">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên cuộc thi</label>
                        <input id="title" name="title" type="text" value="{{ old('title', $competition->title) }}" required maxlength="255"
                               class="admin-input">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-slate-600 mb-1" for="type">Loại</label>
                        <x-ws.select id="type" name="type" required x-model="type">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $competition->type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                </div>

                <div class="rounded-xl bg-amber-50 border border-amber-100 p-4 space-y-3">
                    <p class="text-[13px] font-medium text-amber-700">Đơn vị tổ chức</p>
                    <div>
                        <label class="block text-xs text-slate-500 mb-1" for="organizer_type">Cuộc thi do ai tổ chức?</label>
                        <x-ws.select id="organizer_type" name="organizer_type" x-model="organizerType" required>
                            @foreach ($organizerTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('organizer_type', $competition->organizer_type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    </div>
                    <div x-show="organizerType === 'external'" x-cloak>
                        <label class="block text-xs text-slate-500 mb-1" for="organizer_name">Tên đơn vị tổ chức</label>
                        <input id="organizer_name" name="organizer_name" type="text" value="{{ old('organizer_name', $competition->organizer_name) }}" maxlength="255"
                               class="admin-input">
                    </div>
                    <div>
                        {{-- SỬA 24/10 — giáo viên tổ chức hiện cho CẢ cuộc thi nội bộ: họ quản lý học sinh, duyệt đăng ký ngay trong phòng thi. --}}
                        <label class="block text-xs text-slate-500 mb-1">Giáo viên tổ chức / cố vấn — quản lý học sinh &amp; duyệt đăng ký trong phòng thi <span class="text-slate-400">(bắt buộc ≥1 nếu bên ngoài tổ chức)</span></label>
                        <div class="max-h-40 overflow-y-auto rounded-xl border border-sky-100 bg-white p-2 space-y-1">
                            @forelse ($teacherOptions as $t)
                                <label class="flex items-center gap-2 text-[13px] px-2 py-1 rounded hover:bg-amber-50 cursor-pointer">
                                    <input type="checkbox" name="advisor_teacher_ids[]" value="{{ $t['id'] }}" @checked(in_array($t['id'], old('advisor_teacher_ids', $selectedAdvisorIds)))>
                                    {{ $t['name'] }}
                                </label>
                            @empty
                                <p class="text-xs text-slate-400 px-2 py-1">Chưa có giáo viên nào được duyệt để chọn làm cố vấn.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                @include('admin.competitions._hub-fields', ['competition' => $competition])

                {{-- SỬA 20/10 (khách: "thêm/sửa cuộc thi bỏ ô Đề/bộ bài tham chiếu và khối Quy tắc bảng xếp hạng") — ẩn khỏi form,
                     nhưng gửi lại GIÁ TRỊ ĐANG CÓ bằng ô ẩn để bấm Lưu không xoá mất dữ liệu cũ của cuộc thi. --}}
                <input type="hidden" name="assessment_id" value="{{ old('assessment_id', $competition->assessment_id) }}">
                <input type="hidden" name="scoring_note" value="{{ old('scoring_note', $rankingRule['scoring_note'] ?? '') }}">
                <input type="hidden" name="penalty_note" value="{{ old('penalty_note', $rankingRule['penalty_note'] ?? '') }}">
                <input type="hidden" name="tie_break_note" value="{{ old('tie_break_note', $rankingRule['tie_break_note'] ?? '') }}">

                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="rules">Thể lệ</label>
                    <textarea id="rules" name="rules" rows="4" maxlength="5000"
                              class="admin-input">{{ old('rules', $competition->rules) }}</textarea>
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
                    <label class="block text-[13px] font-medium text-slate-600 mb-1">Trạng thái</label>
                    <p class="text-[13px] text-slate-700">{{ $statusLabels[$computedStatusValue] ?? $computedStatusValue }}</p>
                    <p class="text-xs text-slate-400 mt-1">🔄 Tự tính theo lịch Bắt đầu/Kết thúc/Công bố kết quả — không chọn tay. Muốn "Lưu trữ" thì dùng nút riêng ở trang chi tiết.</p>
                </div>
                --}}

                <div class="flex gap-3 pt-2">
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                    <a href="{{ route('admin.competitions.show', $competition->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
                </div>
            </form>
        </div>

    </div>
@endsection
