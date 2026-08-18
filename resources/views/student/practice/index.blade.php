{{--
  Route: student.practice.index | Frame: STU-04
  Spec: 10.1 — Tabs Tự luyện · Theo lớp · Bài được giao · Đã lưu · Lịch sử.
  Bộ lọc: môn, khối, chuyên đề, độ khó, loại câu/đề, trạng thái, quyền.
  Dữ liệu thật do App\Http\Controllers\Student\PracticeController truyền vào.
  TODO: nối bộ lọc thật (môn/khối/chuyên đề/độ khó) — hiện các nút lọc chỉ là UI.
--}}
@extends('layouts.student')

@section('title', 'Luyện tập')
@section('page-title', 'Luyện tập')

@section('content')
    @php
        $tab = $tab ?? 'self';
        $tabs = $tabs ?? [];
        $items = $items ?? [];
    @endphp

    <x-page-header title="📝 Luyện tập" subtitle="Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3)." />

    <x-tabs :tabs="$tabs" />

    <div class="flex flex-wrap gap-2 mb-6 text-sm">
        <button type="button" class="px-3 py-1.5 rounded-full bg-rose-50 text-rose-600 font-medium">Tất cả</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Lập trình</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Trắc nghiệm</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Điền đáp án</button>
        <button type="button" class="px-3 py-1.5 rounded-full border border-slate-200 text-slate-500">Độ khó</button>
    </div>

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
