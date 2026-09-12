@extends('layouts.teacher')

@section('title', 'Tạo đề')
@section('page-title', 'Tạo đề')

@section('content')
    @php
        $questions = $questions ?? [];
        $typeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻'];
    @endphp

    <a href="{{ route('teacher.assessments.index') }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại Luyện tập</a>

    <x-ws.page-header title="Tạo đề" subtitle="Trộn được lập trình, trắc nghiệm và điền đáp án trong cùng một đề (6.3)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('teacher.assessments.store') }}">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <label class="block text-[13px] font-medium text-slate-600 mb-1" for="title">Tên đề</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                           class="admin-input" placeholder="VD: Đề ôn chương 3 - Cấu trúc dữ liệu">
                </div>

                <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="clipboard-list" class="h-4 w-4" /></span> Chọn câu từ kho của bạn</h3>
                        <a href="{{ route('teacher.questions.create') }}" class="text-[13px] text-blue-600 font-medium">+ Tạo câu mới trong kho</a>
                    </div>

                    @if (empty($questions))
                        <x-ws.empty-state title="Kho câu hỏi của bạn đang trống" description="Tạo câu hỏi trước khi ghép thành đề." actionLabel="Tạo câu hỏi" :actionHref="route('teacher.questions.create')" />
                    @else
                        <div class="divide-y divide-slate-100 max-h-[28rem] overflow-y-auto">
                            @foreach ($questions as $q)
                                <label class="flex items-center justify-between py-3 gap-3 cursor-pointer">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <input type="checkbox" name="question_ids[]" value="{{ $q['id'] }}" @checked(in_array($q['id'], old('question_ids', [])))>
                                        <span class="text-base shrink-0">{{ $typeIcons[$q['type']] ?? '❓' }}</span>
                                        <div class="min-w-0">
                                            <p class="text-[13px] text-slate-700 truncate">{{ $q['title'] }}</p>
                                            <p class="text-xs text-slate-400">{{ $q['status'] === 'published' ? 'Đã phát hành' : 'Nháp' }}</p>
                                        </div>
                                    </div>
                                    <input type="number" name="points_override[{{ $q['id'] }}]" value="{{ old('points_override.'.$q['id'], $q['points']) }}" min="1" max="100"
                                           class="w-16 rounded-xl border border-sky-100 text-[13px] p-1.5 text-center shrink-0" onclick="event.stopPropagation()">
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-400 mt-2">Câu còn "Nháp" vẫn ghép được vào đề, nhưng đề chỉ phát hành được khi mọi câu đã Phát hành (6.2).</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-sky-100 p-5 space-y-4">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="settings" class="h-4 w-4" /></span> Cấu hình</h3>
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1" for="duration_minutes">Thời lượng (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" value="{{ old('duration_minutes', 45) }}" min="1" max="600" class="admin-input">
                </div>
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1" for="max_resubmissions">Nộp lại tối đa</label>
                    <input id="max_resubmissions" name="max_resubmissions" type="number" value="{{ old('max_resubmissions', 2) }}" min="1" max="10" class="admin-input">
                </div>
                <div>
                    <label class="block text-[13px] text-slate-600 mb-1" for="publish_answer_rule">Công bố đáp án/lời giải</label>
                    <x-ws.select id="publish_answer_rule" name="publish_answer_rule">
                        <option value="after_deadline" @selected(old('publish_answer_rule', 'after_deadline') === 'after_deadline')>Sau khi hết hạn nộp</option>
                        <option value="immediately" @selected(old('publish_answer_rule') === 'immediately')>Ngay sau khi nộp</option>
                        <option value="never" @selected(old('publish_answer_rule') === 'never')>Không công bố</option>
                    </x-ws.select>
                </div>

                {{-- SỬA 24/8 — khách yêu cầu: bỏ hẳn "Giao ngay cho lớp" ở màn Tạo đề, tạo đề
                     ở đây CHỈ để lưu (luôn Nháp) — việc giao cho lớp chuyển hẳn sang tab
                     "Giao đề" trong trang Chi tiết lớp (chọn đề có sẵn, xem
                     Teacher\ClassRoomController::assignAssessment()). --}}
                <button type="submit" class="w-full inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">Lưu đề</button>
            </div>
        </div>
    </form>
@endsection
