@extends('layouts.admin')

@section('title', 'Chọn câu hỏi cho đề/bộ bài')
@section('page-title', 'Chọn câu hỏi cho đề/bộ bài')

@section('content')
    @php
        $questions = $questions ?? [];
        $selectedIds = $selectedIds ?? [];
        $pointsOverrides = $pointsOverrides ?? [];
        $typeIcons = ['mcq' => '🔤', 'fill_blank' => '✏️', 'coding' => '💻'];
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
          x-data="{
              total: 0,
              count: 0,
              recalc() {
                  let t = 0, c = 0;
                  this.$el.querySelectorAll('[data-question-row]').forEach((row) => {
                      const picked = row.querySelector('input[type=checkbox]');
                      const points = row.querySelector('input[type=number]');
                      if (picked && picked.checked) {
                          c += 1;
                          t += parseInt(points && points.value ? points.value : '0', 10) || 0;
                      }
                  });
                  this.total = t;
                  this.count = c;
              }
          }"
          x-init="recalc()" @change="recalc()" @input="recalc()">
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
                <div class="divide-y divide-slate-100 max-h-[32rem] overflow-y-auto">
                    @foreach ($questions as $q)
                        <label class="flex items-center justify-between py-3 gap-3 cursor-pointer" data-question-row>
                            <div class="flex items-center gap-3 min-w-0">
                                <input type="checkbox" name="question_ids[]" value="{{ $q['id'] }}" @checked(in_array($q['id'], old('question_ids', $selectedIds)))>
                                <span class="text-base shrink-0">{{ $typeIcons[$q['type']] ?? '❓' }}</span>
                                <div class="min-w-0">
                                    <p class="text-[13px] text-slate-700 truncate">{{ $q['title'] }}</p>
                                    <p class="text-xs text-slate-400">{{ $q['ownerLabel'] }} · {{ $q['status'] === 'published' ? 'Đã phát hành' : 'Nháp' }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-xs font-semibold text-slate-400">Điểm</span>
                                <input type="number" name="points_override[{{ $q['id'] }}]" value="{{ old('points_override.'.$q['id'], $pointsOverrides[$q['id']] ?? $q['points']) }}" min="1" max="100"
                                       class="w-16 rounded-xl border border-sky-100 text-[13px] p-1.5 text-center" onclick="event.stopPropagation()">
                            </div>
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-slate-400 mt-2">Điểm gõ ở đây là điểm của câu <strong>trong đề này</strong> — không đụng tới điểm gốc của câu trong kho, nên cùng một câu có thể 10 điểm ở đề này và 5 điểm ở đề khác. Đây cũng chính là điểm máy dùng để chấm.</p>
                <p class="text-xs text-slate-400 mt-1">Câu còn "Nháp" vẫn gắn được vào đề, nhưng đề chỉ phát hành được khi mọi câu đã Phát hành (6.2).</p>
            @endif
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 shadow-sm hover:bg-blue-700 transition">Lưu danh sách câu hỏi</button>
            <a href="{{ route('admin.content.show', ['content' => $assessment->id, 'kind' => 'assessment']) }}" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">Huỷ</a>
        </div>
    </form>
@endsection
