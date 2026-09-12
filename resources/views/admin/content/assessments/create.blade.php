@extends('layouts.admin')

@section('title', 'Tạo đề/bộ bài')
@section('page-title', 'Tạo đề/bộ bài')

@section('content')
    @php $types = $types ?? []; $publishAnswerRules = $publishAnswerRules ?? []; @endphp

    <a href="{{ route('admin.content.index', ['tab' => 'assessments']) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Nội dung</a>

    <x-admin.page-header title="Tạo đề/bộ bài" icon="scroll-text" subtitle="Chỉ tạo thông tin chung của đề — nội dung (câu hỏi rời hoặc PDF + đáp án) hoàn thiện ở màn sau khi lưu." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="rounded-xl bg-amber-50 border border-amber-100 p-3 text-xs text-amber-800 mb-4">
        <strong>Lưu ý chọn đúng "Loại":</strong> chỉ <strong>Luyện tập</strong> dùng câu hỏi rời từ Kho câu hỏi (màn "Quản lý câu hỏi" sau khi tạo).
        Còn lại — <strong>Bài giao, Đề thi, Đề thi đấu</strong> — đều chuyển sang chế độ <strong>tải file PDF + nhập đáp án</strong> (màn "Quản lý đề PDF" sau khi tạo, không phải ở đây).
        Chọn nhầm "Loại" thì phải sửa lại đúng Loại rồi lưu lại mới đổi chế độ.
    </div>

    <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-5 sm:p-6">
        <form method="POST" action="{{ route('admin.content.assessments.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên đề/bộ bài</label>
                <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                       placeholder="Ví dụ: Đề thi giữa kỳ 1 - Tin học 10"
                       class="admin-input">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="type">Loại</label>
                    @php $defaultType = array_key_first($types); @endphp
                    <x-admin.select id="type" name="type" required>
                        @foreach ($types as $value => $label)
                            <option value="{{ $value }}" @selected(old('type', $defaultType) === $value)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                    <p class="text-[11px] text-slate-400 mt-1">
                        @if (array_key_exists('practice', $types))
                            "Luyện tập" = câu hỏi rời · Còn lại = PDF + đáp án.
                        @else
                            Đề/bộ bài tạo ở đây dùng PDF + phiếu đáp án.
                        @endif
                    </p>
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="total_points">Tổng điểm</label>
                    <input id="total_points" name="total_points" type="number" min="0" value="{{ old('total_points', 10) }}"
                           class="admin-input">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="duration_minutes">Thời gian làm bài (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" min="0" value="{{ old('duration_minutes') }}"
                           placeholder="Để trống nếu không giới hạn"
                           class="admin-input">
                </div>
                <div>
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="publish_answer_rule">Hiện đáp án</label>
                    <x-admin.select id="publish_answer_rule" name="publish_answer_rule" required>
                        @foreach ($publishAnswerRules as $value => $label)
                            <option value="{{ $value }}" @selected(old('publish_answer_rule', 'never') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-admin.select>
                </div>
            </div>

            <div class="rounded-xl bg-sky-50 border border-sky-100 p-3 text-xs text-sky-700">
                Đề luôn tạo ở trạng thái <span class="font-medium">Nháp</span> — sau khi lưu sẽ vào trang chi tiết, kéo xuống để thấy đúng màn hoàn thiện nội dung (câu hỏi hoặc PDF) rồi mới Phát hành.
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Tạo đề/bộ bài</button>
                <a href="{{ route('admin.content.index', ['tab' => 'assessments']) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
            </div>
        </form>
    </div>
@endsection
