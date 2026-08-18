{{--
  Route: student.practice.index | Frame: STU-04
  Spec: 10.1 — Tabs Tự luyện · Theo lớp · Bài được giao · Đã lưu · Lịch sử.
  Bộ lọc: môn, khối, chuyên đề, độ khó, loại câu/đề, trạng thái, quyền.
  Dữ liệu thật do App\Http\Controllers\Student\PracticeController truyền vào.

  SỬA 18/8: trước đây 4 nút lọc dưới đây chỉ là UI tĩnh, không lọc được gì thật (xem TODO cũ).
  Giờ lọc thật theo 2 chiều có dữ liệu sẵn (App\Services\Student\PracticeService::
  buildIndexData() — xem docblock ở đó để biết vì sao "chuyên đề" tạm dùng tên
  App\Models\QuestionBank thay vì 1 bảng Tag/Chuyên đề riêng, hệ thống chưa có):
  - $type: lọc đề có ít nhất 1 câu hỏi đúng dạng (Lập trình/Trắc nghiệm/Điền đáp án).
  - $topic: lọc theo "chuyên đề" (tên ngân hàng câu hỏi) — chỉ hiện các chip khi tab đang xem
    (Tự luyện/Theo lớp/Được giao) thực sự có ít nhất 1 đề gắn chuyên đề nào đó.
  "Độ khó" CHƯA lọc được thật vì Question không có cột difficulty — cố tình để dạng vô hiệu
  hoá (không phải nút chết do quên nối, mà là chưa có dữ liệu để lọc).
--}}
@extends('layouts.student')

@section('title', 'Luyện tập')
@section('page-title', 'Luyện tập')

@section('content')
    @php
        $tab = $tab ?? 'self';
        $tabs = $tabs ?? [];
        $items = $items ?? [];
        $type = $type ?? null;
        $topic = $topic ?? null;
        $filtersApply = $filtersApply ?? false;
        $availableTopics = $availableTopics ?? [];

        $baseParams = array_filter(['tab' => $tab !== 'self' ? $tab : null]);
        $typeHref = fn (?string $val) => route('student.practice.index', array_filter($baseParams + ['type' => $val, 'topic' => $topic]));
        $topicHref = fn (?string $val) => route('student.practice.index', array_filter($baseParams + ['type' => $type, 'topic' => $val]));
        $chipClass = fn (bool $active) => $active
            ? 'px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium'
            : 'px-3 py-1.5 rounded-full border border-slate-200 text-slate-500 hover:border-rose-200';
        $topicChipClass = fn (bool $active) => $active
            ? 'px-2.5 py-1 rounded-full bg-slate-800 text-white'
            : 'px-2.5 py-1 rounded-full border border-slate-200 text-slate-500 hover:border-slate-300';
    @endphp

    <x-page-header title="📝 Luyện tập" subtitle="Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3)." />

    <x-tabs :tabs="$tabs" />

    @if ($filtersApply)
        <div class="flex flex-wrap gap-2 mb-4 text-sm">
            <a href="{{ $typeHref(null) }}" class="{{ $chipClass($type === null) }}">Tất cả</a>
            <a href="{{ $typeHref('coding') }}" class="{{ $chipClass($type === 'coding') }}">💻 Lập trình</a>
            <a href="{{ $typeHref('mcq') }}" class="{{ $chipClass($type === 'mcq') }}">🔤 Trắc nghiệm</a>
            <a href="{{ $typeHref('fill_blank') }}" class="{{ $chipClass($type === 'fill_blank') }}">✏️ Điền đáp án</a>
            <span class="px-3 py-1.5 rounded-full border border-dashed border-slate-200 text-slate-300 cursor-not-allowed" title="Chưa có dữ liệu độ khó cho câu hỏi để lọc">Độ khó (sắp có)</span>
        </div>

        @if (count($availableTopics) > 0)
            <div class="flex flex-wrap items-center gap-2 mb-6 text-xs">
                <span class="text-slate-400">Chuyên đề:</span>
                <a href="{{ $topicHref(null) }}" class="{{ $topicChipClass($topic === null) }}">Tất cả</a>
                @foreach ($availableTopics as $t)
                    <a href="{{ $topicHref($t) }}" class="{{ $topicChipClass($topic === $t) }}">{{ $t }}</a>
                @endforeach
            </div>
        @endif
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($items as $it)
            <div class="rounded-2xl bg-white border border-slate-200 p-5 hover:shadow-md hover:border-rose-200 transition">
                <div class="flex items-start justify-between gap-2 mb-2">
                    {{-- typeLabel/typeIcon (App\Enums\AssessmentType::label()/icon()) — trước đây
                         2 dòng này lookup icon theo nhãn CÂU HỎI ('Lập trình'/'Trắc nghiệm'...)
                         trong khi $it['type'] lại là loại ĐỀ (practice/assignment/exam...), 2
                         enum khác nhau nên icon luôn rơi về mặc định và badge hiện thẳng string
                         thô ("practice") thay vì tiếng Việt. --}}
                    <span class="text-lg">{{ $it['typeIcon'] ?? '📝' }}</span>
                    <x-status-badge tone="info">{{ $it['typeLabel'] ?? $it['type'] }}</x-status-badge>
                </div>
                <h3 class="font-medium text-slate-800">{{ $it['title'] }}</h3>
                <p class="text-xs text-slate-400 mt-1">{{ $it['source'] }}{{ $it['difficulty'] ? ' · Độ khó: '.$it['difficulty'] : '' }}</p>
                @if (! empty($it['topics'] ?? []))
                    <p class="text-xs text-slate-400 mt-0.5">📚 {{ implode(', ', $it['topics']) }}</p>
                @endif
                <div class="mt-3 flex items-center justify-between">
                    <x-status-badge :tone="$it['tone']">{{ $it['status'] }}</x-status-badge>
                    {{-- Tab "Lịch sử" trỏ sang trang KẾT QUẢ (đã nộp), không phải vào làm bài mới — nhãn nút phải phản ánh đúng hành động, không dùng chung "Làm bài" cho cả 2 trường hợp. --}}
                    <a href="{{ $it['takeRoute'] ?? route('student.practice.index') }}" class="text-sm font-medium text-rose-600">{{ $tab === 'history' ? 'Xem kết quả ›' : 'Làm bài ›' }}</a>
                </div>
            </div>
        @empty
            <div class="col-span-full">
                <x-empty-state title="Chưa có bài phù hợp bộ lọc" description="Thử bỏ bộ lọc hoặc khám phá thêm bài luyện tập công khai." actionLabel="Khám phá Luyện tập công khai" :actionHref="route('practice.index')" />
            </div>
        @endforelse
    </div>
@endsection
