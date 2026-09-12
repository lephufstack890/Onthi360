@extends('layouts.admin')

@section('title', 'Sửa đề/bộ bài')
@section('page-title', 'Sửa đề/bộ bài')

@section('content')
    @php $types = $types ?? []; $publishAnswerRules = $publishAnswerRules ?? []; @endphp

    <a href="{{ route('admin.content.show', $assessment->id) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết</a>

    <x-ws.page-header title="Sửa đề/bộ bài" icon="pencil" :subtitle="$assessment->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    @if ($assessment->isPdfMode())
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 mb-5 flex items-center justify-between gap-3 flex-wrap">
            <p class="text-[13px] text-sky-800">Đề này dùng chế độ <strong>PDF + phiếu đáp án</strong> — tải file PDF, nhập đáp án và thêm bài lập trình ở màn riêng, không phải ở đây.</p>
            <a href="{{ route('admin.content.assessments.pdf.edit', $assessment->id) }}" class="px-4 py-2 rounded-xl bg-sky-600 text-white text-[13px] font-medium shadow-sm shrink-0">📄 Quản lý đề PDF ›</a>
        </div>
    @endif

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        <form method="POST" action="{{ route('admin.content.assessments.update', $assessment->id) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên đề/bộ bài</label>
                <input id="title" name="title" type="text" value="{{ old('title', $assessment->title) }}" required maxlength="255"
                       class="admin-input">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="type">Loại</label>
                    {{-- SỬA 8/9 (5) (khách: "ẩn loại Tự luyện ở đề/bộ bài bên admin") — $types đã
                         bị lọc bỏ 'practice' theo cờ config('features.assessment_type_practice').
                         Nếu đề NÀY đang là "Tự luyện": KHÔNG đổ dropdown (loại đó không còn trong
                         danh sách, chọn bừa option đầu sẽ ÂM THẦM đổi loại đề + đổi luôn
                         content_mode sang PDF, xem ContentService::contentModeForType()) — hiện
                         loại hiện tại dạng chữ và gửi lại đúng giá trị cũ qua input ẩn. --}}
                    @if (! array_key_exists($assessment->type->value, $types))
                        <input type="hidden" name="type" value="{{ $assessment->type->value }}">
                        <p class="text-[13px] text-slate-500 py-2.5 px-1">
                            {{ $assessment->type->label() }}
                            <span class="text-xs text-slate-400">(loại này đang ẩn — giữ nguyên, không đổi được ở đây)</span>
                        </p>
                    @else
                        <x-ws.select id="type" name="type" required>
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $assessment->type->value) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-ws.select>
                    @endif
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="total_points">Tổng điểm</label>
                    <input id="total_points" name="total_points" type="number" min="0" value="{{ old('total_points', $assessment->total_points) }}"
                           class="admin-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="duration_minutes">Thời gian làm bài (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="0" value="{{ old('duration_minutes', $assessment->duration_minutes) }}"
                           class="admin-input">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="publish_answer_rule">Hiện đáp án</label>
                    <x-ws.select id="publish_answer_rule" name="publish_answer_rule" required>
                        @foreach ($publishAnswerRules as $value => $label)
                            <option value="{{ $value }}" @selected(old('publish_answer_rule', $assessment->publish_answer_rule->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ws.select>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu thay đổi</button>
                <a href="{{ route('admin.content.show', $assessment->id) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
