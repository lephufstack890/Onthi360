{{--
  Route: teacher.assessments.create / .store | Frame: TEA-04
  Spec: 6.3 (đề hỗn hợp trộn nhiều kiểu câu) + 8.4 (giao đề: chọn lớp,
  mốc thời gian, quy tắc — không có ngoại lệ từng học sinh).
  Dữ liệu thật ($questions, $classRooms) do
  App\Services\Teacher\AssessmentService::createFormData() truyền vào.
--}}
@extends('layouts.teacher')

@section('title', 'Tạo đề')
@section('page-title', 'Tạo đề')

@section('content')
    @php
        $questions = $questions ?? [];
        $classRooms = $classRooms ?? [];
        $typeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻'];
    @endphp

    <a href="{{ route('teacher.assessments.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Bài tập & Đề</a>

    <x-page-header title="Tạo đề" subtitle="Trộn được lập trình, trắc nghiệm và điền đáp án trong cùng một đề (6.3)." />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <form method="POST" action="{{ route('teacher.assessments.store') }}">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <label class="block text-sm font-medium text-slate-600 mb-1" for="title">Tên đề</label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required maxlength="255"
                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5" placeholder="VD: Đề ôn chương 3 - Cấu trúc dữ liệu">
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>📋</span> Chọn câu từ kho của bạn</h3>
                        <a href="{{ route('teacher.questions.create') }}" class="text-sm text-rose-600 font-medium">+ Tạo câu mới trong kho</a>
                    </div>

                    @if (empty($questions))
                        <x-empty-state title="Kho câu hỏi của bạn đang trống" description="Tạo câu hỏi trước khi ghép thành đề." actionLabel="Tạo câu hỏi" :actionHref="route('teacher.questions.create')" />
                    @else
                        <div class="divide-y divide-slate-100 max-h-[28rem] overflow-y-auto">
                            @foreach ($questions as $q)
                                <label class="flex items-center justify-between py-3 gap-3 cursor-pointer">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <input type="checkbox" name="question_ids[]" value="{{ $q['id'] }}" @checked(in_array($q['id'], old('question_ids', [])))>
                                        <span class="text-base shrink-0">{{ $typeIcons[$q['type']] ?? '❓' }}</span>
                                        <div class="min-w-0">
                                            <p class="text-sm text-slate-700 truncate">{{ $q['title'] }}</p>
                                            <p class="text-xs text-slate-400">{{ $q['status'] === 'published' ? 'Đã phát hành' : 'Nháp' }}</p>
                                        </div>
                                    </div>
                                    <input type="number" name="points_override[{{ $q['id'] }}]" value="{{ old('points_override.'.$q['id'], $q['points']) }}" min="1" max="100"
                                           class="w-16 rounded-lg border border-slate-200 text-sm p-1.5 text-center shrink-0" onclick="event.stopPropagation()">
                                </label>
                            @endforeach
                        </div>
                        <p class="text-xs text-slate-400 mt-2">Câu còn "Nháp" vẫn ghép được vào đề, nhưng đề chỉ phát hành được khi mọi câu đã Phát hành (6.2).</p>
                    @endif
                </div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 space-y-4">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span>⚙️</span> Cấu hình</h3>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="duration_minutes">Thời lượng (phút)</label>
                    <input id="duration_minutes" name="duration_minutes" type="number" value="{{ old('duration_minutes', 45) }}" min="1" max="600" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="max_resubmissions">Nộp lại tối đa</label>
                    <input id="max_resubmissions" name="max_resubmissions" type="number" value="{{ old('max_resubmissions', 2) }}" min="1" max="10" class="w-full rounded-lg border border-slate-200 text-sm p-2.5">
                </div>
                <div>
                    <label class="block text-sm text-slate-600 mb-1" for="publish_answer_rule">Công bố đáp án/lời giải</label>
                    <x-select id="publish_answer_rule" name="publish_answer_rule">
                        <option value="after_deadline" @selected(old('publish_answer_rule', 'after_deadline') === 'after_deadline')>Sau khi hết hạn nộp</option>
                        <option value="immediately" @selected(old('publish_answer_rule') === 'immediately')>Ngay sau khi nộp</option>
                        <option value="never" @selected(old('publish_answer_rule') === 'never')>Không công bố</option>
                    </x-select>
                </div>

                <button type="submit" name="action" value="draft" class="w-full px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Lưu nháp</button>

                @if (! empty($classRooms))
                    <div class="rounded-lg bg-sky-50 border border-sky-100 p-3 space-y-2">
                        <p class="text-xs font-medium text-sky-700">Giao ngay cho lớp (8.4)</p>
                        <x-select name="class_room_id">
                            <option value="">— Chọn lớp —</option>
                            @foreach ($classRooms as $c)
                                <option value="{{ $c->id }}" @selected((string) old('class_room_id') === (string) $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </x-select>
                        <div class="space-y-2">
                            @include('partials.optional-date-hour-minute-fields', ['prefix' => 'opens', 'label' => 'Mở lúc (tùy chọn)'])
                            @include('partials.optional-date-hour-minute-fields', ['prefix' => 'closes', 'label' => 'Đóng lúc (tùy chọn)'])
                            <p class="text-[11px] text-slate-400">Để trống Ngày nếu không giới hạn mốc thời gian đó.</p>
                        </div>
                        <textarea name="instructions" rows="2" class="w-full rounded-lg border border-slate-200 text-xs p-2" placeholder="Hướng dẫn làm bài (tùy chọn)...">{{ old('instructions') }}</textarea>
                        <p class="text-xs text-sky-600">Đề sẽ tự động phát hành nếu mọi câu đã đủ điều kiện (6.2), không hỗ trợ ngoại lệ từng học sinh (8.4).</p>
                        <button type="submit" name="action" value="assign" class="w-full px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Lưu & Giao cho lớp</button>
                    </div>
                @endif
            </div>
        </div>
    </form>
@endsection
