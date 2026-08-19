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
            ? 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm font-medium bg-rose-600 text-white shadow-sm shadow-rose-200 transition'
            : 'inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm font-medium bg-slate-50 text-slate-600 border border-slate-200 hover:border-rose-300 hover:text-rose-600 hover:bg-rose-50 transition';
        $topicChipClass = fn (bool $active) => $active
            ? 'px-3 py-1.5 rounded-full text-xs font-medium bg-slate-800 text-white transition'
            : 'px-3 py-1.5 rounded-full text-xs font-medium bg-white text-slate-500 border border-slate-200 hover:border-slate-400 hover:text-slate-700 transition';

        $typeFilters = [
            ['value' => null, 'label' => 'Tất cả', 'icon' => '🌈'],
            ['value' => 'coding', 'label' => 'Lập trình', 'icon' => '💻'],
            ['value' => 'mcq', 'label' => 'Trắc nghiệm', 'icon' => '🔤'],
            ['value' => 'fill_blank', 'label' => 'Điền đáp án', 'icon' => '✏️'],
        ];

        // Bảng màu theo LOẠI ĐỀ (App\Enums\AssessmentType) để mắt phân biệt nhanh Tự luyện/
        // Bài giao/Đề thi/Đề thi đấu trên card — dùng tone có sẵn của <x-icon-tile> (rose/sky/
        // violet/amber/emerald) + class Tailwind ghi trực tiếp dạng chữ (không ghép chuỗi động)
        // để Tailwind quét/build CSS được bình thường.
        $cardAccent = fn (?string $assessmentType) => match ($assessmentType) {
            'assignment' => ['tone' => 'sky', 'bar' => 'from-sky-400 to-sky-300', 'hover' => 'hover:border-sky-200'],
            'exam' => ['tone' => 'amber', 'bar' => 'from-amber-400 to-amber-300', 'hover' => 'hover:border-amber-200'],
            'competition_paper' => ['tone' => 'violet', 'bar' => 'from-violet-400 to-violet-300', 'hover' => 'hover:border-violet-200'],
            default => ['tone' => 'rose', 'bar' => 'from-rose-400 to-rose-300', 'hover' => 'hover:border-rose-200'],
        };
    @endphp

    <div class="rounded-3xl bg-gradient-to-br from-rose-100 via-pink-50 to-amber-50 p-6 lg:p-8 mb-6 flex items-center justify-between flex-wrap gap-4">
        <div>
            <p class="text-sm text-rose-600 font-medium">📝 Luyện tập</p>
            <h2 class="text-xl lg:text-2xl font-semibold text-slate-800 mt-1">Luyện đủ dạng, tự tin đi thi</h2>
            <p class="text-sm text-slate-500 mt-1 max-w-lg">Chấm được câu lập trình, trắc nghiệm và điền đáp án — trong cùng một đề (6.3).</p>
        </div>
        <div class="text-5xl">🎯</div>
    </div>

    <a href="{{ route('student.practiceByQuestion.setup') }}"
       class="mb-6 flex items-center justify-between gap-4 rounded-2xl border border-sky-200 bg-gradient-to-r from-sky-50 to-cyan-50 p-4 lg:p-5 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
        <div class="flex items-center gap-3">
            <x-icon-tile emoji="🧠" tone="sky" />
            <div>
                <h3 class="font-semibold text-slate-800">Luyện tập theo câu</h3>
                <p class="text-xs text-slate-500 mt-0.5">Chọn chuyên đề, luyện từng câu một, biết đúng/sai ngay lập tức.</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-1 text-sm font-medium text-sky-600">Bắt đầu <span aria-hidden="true">→</span></span>
    </a>

    <x-tabs :tabs="$tabs" />

    @if ($filtersApply)
        <div class="bg-white rounded-2xl border border-slate-200 p-4 lg:p-5 mb-6 space-y-4">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Loại câu hỏi</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($typeFilters as $tf)
                        <a href="{{ $typeHref($tf['value']) }}" class="{{ $chipClass($type === $tf['value']) }}">
                            <span>{{ $tf['icon'] }}</span> {{ $tf['label'] }}
                        </a>
                    @endforeach
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-sm border border-dashed border-slate-200 text-slate-300 bg-slate-50 cursor-not-allowed"
                          title="Chưa có dữ liệu độ khó cho câu hỏi để lọc">
                        <span>🔒</span> Độ khó
                    </span>
                </div>
            </div>

            @if (count($availableTopics) > 0)
                <div class="pt-3 border-t border-slate-100">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Chuyên đề</p>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="{{ $topicHref(null) }}" class="{{ $topicChipClass($topic === null) }}">Tất cả</a>
                        @foreach ($availableTopics as $t)
                            <a href="{{ $topicHref($t) }}" class="{{ $topicChipClass($topic === $t) }}">{{ $t }}</a>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="flex items-center justify-between mb-3">
        <p class="text-sm text-slate-400">{{ count($items) }} bài phù hợp</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($items as $it)
            @php $accent = $cardAccent($it['type'] ?? null); @endphp
            <a href="{{ $it['takeRoute'] ?? route('student.practice.index') }}"
               class="group relative flex flex-col h-full rounded-2xl bg-white border border-slate-200 p-5 pt-6 overflow-hidden transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 {{ $accent['hover'] }}">
                <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $accent['bar'] }}"></span>

                <div class="flex items-start justify-between gap-2 mb-3">
                    <x-icon-tile :emoji="$it['typeIcon'] ?? '📝'" :tone="$accent['tone']" />
                    <x-status-badge :tone="$it['tone']">{{ $it['status'] }}</x-status-badge>
                </div>

                <div class="mb-2">
                    <x-status-badge tone="info">{{ $it['typeLabel'] ?? $it['type'] }}</x-status-badge>
                </div>

                <h3 class="font-semibold text-slate-800 leading-snug line-clamp-2">{{ $it['title'] }}</h3>
                <p class="text-xs text-slate-400 mt-1.5">
                    {{ $it['source'] }}{{ $it['difficulty'] ? ' · Độ khó: '.$it['difficulty'] : '' }}
                </p>

                @if (! empty($it['topics'] ?? []))
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach ($it['topics'] as $t)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">📚 {{ $t }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="mt-auto pt-4 flex items-center justify-end">
                    <span class="inline-flex items-center gap-1 text-sm font-medium text-rose-600 group-hover:gap-2 transition-all">
                        {{ $tab === 'history' ? 'Xem kết quả' : 'Làm bài' }}
                        <span aria-hidden="true">→</span>
                    </span>
                </div>
            </a>
        @empty
            <div class="col-span-full">
                <x-empty-state title="Chưa có bài phù hợp bộ lọc" description="Thử bỏ bộ lọc hoặc khám phá thêm bài luyện tập công khai." actionLabel="Khám phá Luyện tập công khai" :actionHref="route('practice.index')" />
            </div>
        @endforelse
    </div>
@endsection
