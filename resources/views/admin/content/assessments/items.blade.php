@extends('layouts.admin')

@section('title', 'Chọn câu hỏi cho đề/bộ bài')
@section('page-title', 'Chọn câu hỏi cho đề/bộ bài')

@section('content')
    @php
        $questions = $questions ?? [];
        $selectedIds = $selectedIds ?? [];
        $typeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻'];
        // SỬA 9/10 — dữ liệu cho danh sách "Thứ tự câu trong đề" (partials/assessment-question-order).
        $pickMeta = (object) collect($questions)->mapWithKeys(fn ($q) => [$q['id'] => ['title' => $q['title'], 'points' => (int) $q['points']]])->all();
    @endphp

    <a href="{{ route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment']) }}" class="text-[13px] text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-blue-600">‹ Quay lại chi tiết</a>

    <x-ws.page-header title="Chọn câu hỏi" icon="clipboard-list" :subtitle="$assessment->title" />

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    {{-- SỬA 23/9 (khách: "thay vì nhập tổng điểm thì nhập điểm cho từng câu") — màn này giờ là
         NƠI DUY NHẤT đặt điểm cho đề. Ô "Tổng điểm" ở form Tạo/Sửa đề đã bỏ hẳn; tổng điểm là
         số CỘNG LẠI từ các câu được tick, chạy ngay trên màn hình để admin thấy mình đang cho
         đề bao nhiêu điểm trước khi bấm Lưu. --}}
    <form method="POST" action="{{ route('admin.content.assessments.items.update', $assessment->id) }}"
          x-data="assessmentPicker(@js(old('question_ids', $selectedIds)), @js($pickMeta))">
        @csrf
        @method('PUT')

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-sky-100 bg-white px-4 py-3 shadow-[0_2px_8px_rgba(0,90,180,.04)]">
            <div class="flex items-center gap-2 text-[13px] text-slate-600">
                <x-lucide name="calculator" class="h-4 w-4 text-blue-600" />
                <span>Tổng điểm của đề = cộng điểm các câu đã chọn</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-[13px] text-slate-500"><strong class="font-bold text-slate-700" x-text="count"></strong> câu</span>
                <span class="rounded-xl bg-blue-50 px-3 py-1.5 text-[15px] font-bold text-blue-700">
                    <span x-text="total"></span> điểm
                </span>
            </div>
        </div>

        <div class="rounded-3xl border border-sky-100 bg-white shadow-[0_2px_8px_rgba(0,90,180,.04)] p-4 sm:p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-medium text-slate-700 flex items-center gap-2"><span><x-lucide name="book-open" class="h-4 w-4" /></span> Kho câu hỏi (Kho chung + kho riêng từng giáo viên)</h3>
                <a href="{{ route('admin.content.questions.create') }}" class="text-[13px] text-blue-600 font-medium">+ Tạo câu hỏi mới</a>
            </div>

            @if (empty($questions))
                <x-ws.empty-state title="Kho câu hỏi đang trống" description="Tạo câu hỏi trước khi gắn vào đề này." actionLabel="Tạo câu hỏi" :actionHref="route('admin.content.questions.create')" />
            @else
                @include('partials.assessment-question-order')

                <div class="divide-y divide-slate-100 max-h-[32rem] overflow-y-auto">
                    @foreach ($questions as $q)
                        <label class="flex items-center justify-between py-3 gap-3 cursor-pointer" data-question-row data-points="{{ $q['points'] }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <input type="checkbox" value="{{ $q['id'] }}" :checked="has({{ $q['id'] }})" @change="toggle({{ $q['id'] }}, $event.target.checked)">
                                <span class="text-base shrink-0">{{ $typeIcons[$q['type']] ?? '❓' }}</span>
                                <div class="min-w-0">
                                    <p class="text-[13px] text-slate-700 truncate">{{ $q['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $q['ownerLabel'] }} · {{ $q['status'] === 'published' ? 'Đã phát hành' : 'Nháp' }}</p>
                                </div>
                            </div>
                                {{-- SỬA 1/10 (khách: "đừng cho nhập nhé mà tự động active điểm của các câu
                                     theo độ khó của câu đó tại vì mỗi câu đều có điểm dựa vào độ khó rồi") —
                                     ô nhập điểm đã BỎ. Con số dưới đây do
                                     QuestionDifficulty::pointsForQuestion() tính từ độ khó, và service ghi
                                     vào đề cũng gọi ĐÚNG hàm đó, nên số nhìn thấy = số máy chấm.
                                     data-points để phần cộng tổng ở trên đọc được (trước đây nó đọc ô
                                     input[type=number], giờ không còn ô nào). --}}
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="rounded-full border border-sky-100 bg-sky-50 px-2 py-1 text-[11px] font-semibold text-slate-500">{{ $q['difficultyLabel'] }}</span>
                                <span class="w-16 rounded-xl border border-sky-100 bg-slate-50 p-1.5 text-center text-[13px] font-bold text-slate-600">{{ $q['points'] }} đ</span>
                            </div>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-2">Điểm từng câu <strong>tính theo độ khó</strong> của chính câu đó (Cơ bản 2 · Dễ 4 · Khá 6 · Khó 8 · Rất khó 10), không nhập tay. Muốn đổi điểm một câu thì sửa Độ khó của câu đó trong Kho câu hỏi.</p>
                <p class="text-xs text-slate-400 mt-1">Số điểm được <strong>chốt vào đề</strong> lúc bấm Lưu: sau này đổi độ khó của câu trong kho thì đề đã lưu vẫn giữ nguyên điểm cũ, lưu lại màn này mới cập nhật theo.</p>
                <p class="text-xs text-slate-400 mt-1">Câu còn "Nháp" vẫn gắn được vào đề, nhưng đề chỉ phát hành được khi mọi câu đã Phát hành (6.2).</p>
            @endif
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu danh sách câu hỏi</button>
            <a href="{{ route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment']) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
        </div>
    </form>
@endsection
