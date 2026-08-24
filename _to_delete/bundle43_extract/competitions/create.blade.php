@extends('layouts.admin')

@section('title', 'Tạo cuộc thi')
@section('page-title', 'Tạo cuộc thi')

@section('content')
    @php
        $types = $types ?? []; $assessmentOptions = $assessmentOptions ?? [];
        $organizerTypes = $organizerTypes ?? []; $teacherOptions = $teacherOptions ?? [];
    @endphp

    <a href="{{ route('admin.competitions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Cuộc thi</a>

    <x-page-header title="🏆 Tạo cuộc thi" subtitle="Đề thi luôn thuộc Tài liệu; cuộc thi chỉ tham chiếu đề để tổ chức sự kiện (11.1)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="bg-white rounded-2xl border border-slate-200 p-6">
        <form method="POST" action="{{ route('admin.competitions.store') }}" class="space-y-4" x-data="{ organizerType: '{{ old('organizer_type', 'internal') }}' }">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên cuộc thi</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                           placeholder="Ví dụ: Cuộc thi Tin học trẻ vòng trường"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="type">Loại</label>
                    <x-select id="type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', 'contest') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                    <p class="text-xs text-slate-400 mt-1">Khảo sát = loại sự kiện không thi đua (11.1).</p>
                </div>
            </div>

            <div class="rounded-lg bg-amber-50 border border-amber-100 p-4 space-y-3">
                <p class="text-sm font-medium text-amber-700">Đơn vị tổ chức</p>
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="organizer_type">Cuộc thi do ai tổ chức?</label>
                    <x-select id="organizer_type" name="organizer_type" x-model="organizerType" required>
                        @foreach ($organizerTypes as $value => $label)
                            <option value="{{ $value }}" @selected(old('organizer_type', 'internal') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select>
                </div>
                <div x-show="organizerType === 'external'" x-cloak>
                    <label class="block text-xs text-slate-500 mb-1" for="organizer_name">Tên đơn vị tổ chức</label>
                    <input id="organizer_name" name="organizer_name" type="text" value="{{ old('organizer_name') }}" maxlength="255"
                           placeholder="Ví dụ: Hội Tin học TP..."
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5">

                    <label class="block text-xs text-slate-500 mb-1 mt-3">Giáo viên cố vấn/đồng hành (bắt buộc ≥1 — tăng uy tín cho cuộc thi bên ngoài)</label>
                    <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 space-y-1">
                        @forelse ($teacherOptions as $t)
                            <label class="flex items-center gap-2 text-sm px-2 py-1 rounded hover:bg-amber-50 cursor-pointer">
                                <input type="checkbox" name="advisor_teacher_ids[]" value="{{ $t['id'] }}" @checked(in_array($t['id'], old('advisor_teacher_ids', [])))>
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
                        <option value="{{ $a->id }}" @selected((string) old('assessment_id') === (string) $a->id)>{{ $a->title }}</option>
                    @endforeach
                </x-select>
                <p class="text-xs text-slate-400 mt-1">Đề vẫn thuộc Tài liệu — cuộc thi chỉ tham chiếu, không tạo bản sao đề (11.1).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-600 mb-1" for="rules">Thể lệ</label>
                <textarea id="rules" name="rules" rows="4" maxlength="5000"
                          placeholder="Đối tượng dự thi, cách tính điểm, quy định..."
                          class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition">{{ old('rules') }}</textarea>
            </div>

            {{--
              TẠM ẨN 24/8: Khách hiện không cần nhập Bắt đầu/Kết thúc/Công bố kết quả lúc tạo
              cuộc thi (đang thừa) — comment lại (KHÔNG xoá) để sau này cần dùng lại thì chỉ
              cần bỏ comment, không phải viết lại từ đầu. Không đổi field name/logic bên trong.

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-date-time-fields name="starts_at" label="Bắt đầu"
                                     :dayValue="old('starts_at_day')" :monthValue="old('starts_at_month')" :yearValue="old('starts_at_year')"
                                     :hourValue="old('starts_at_hour')" :minuteValue="old('starts_at_minute')" />
                <x-date-time-fields name="ends_at" label="Kết thúc"
                                     :dayValue="old('ends_at_day')" :monthValue="old('ends_at_month')" :yearValue="old('ends_at_year')"
                                     :hourValue="old('ends_at_hour')" :minuteValue="old('ends_at_minute')" />
                <x-date-time-fields name="publish_result_at" label="Công bố kết quả"
                                     :dayValue="old('publish_result_at_day')" :monthValue="old('publish_result_at_month')" :yearValue="old('publish_result_at_year')"
                                     :hourValue="old('publish_result_at_hour')" :minuteValue="old('publish_result_at_minute')" />
            </div>

            <p class="text-xs text-slate-400 -mt-2">
                🔄 Trạng thái (Sắp diễn ra/Đang diễn ra/Chờ công bố/Đã công bố) tự tính theo lịch Bắt đầu/Kết thúc/Công bố kết quả ở trên — không cần chọn tay. Chỉ "Lưu trữ" là admin tự bấm sau khi tạo (ở trang chi tiết cuộc thi).
            </p>
            --}}

            <div class="rounded-lg bg-sky-50 border border-sky-100 p-4 space-y-3">
                <p class="text-sm font-medium text-sky-700">Quy tắc bảng xếp hạng</p>
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="scoring_note">Công thức điểm / kỳ tính</label>
                    <input id="scoring_note" name="scoring_note" type="text" value="{{ old('scoring_note') }}" maxlength="500"
                           placeholder="Ví dụ: Tổng điểm các câu, cập nhật mỗi 5 phút"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="penalty_note">Penalty</label>
                    <input id="penalty_note" name="penalty_note" type="text" value="{{ old('penalty_note') }}" maxlength="500"
                           placeholder="Ví dụ: Trừ 5 điểm mỗi lần nộp sai"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
                <div>
                    <label class="block text-xs text-slate-500 mb-1" for="tie_break_note">Quy tắc đồng điểm</label>
                    <input id="tie_break_note" name="tie_break_note" type="text" value="{{ old('tie_break_note') }}" maxlength="500"
                           placeholder="Ví dụ: Ai nộp bài đúng sớm hơn xếp trên"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium shadow-sm hover:bg-rose-700 transition">Tạo cuộc thi</button>
                <a href="{{ route('admin.competitions.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600 transition">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
