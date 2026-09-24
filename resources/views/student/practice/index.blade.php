@extends('layouts.workspace-auto')

@section('title', 'Luyện tập')
@section('page-title', 'Luyện tập')

@section('content')
    @php
        // ── Khoá do App\Services\Student\PracticeService::buildIndexData() cấp ──
        $tab = $tab ?? 'self';
        $tabs = $tabs ?? [];
        $items = $items ?? [];
        $type = $type ?? null;
        $topic = $topic ?? null;
        $filtersApply = $filtersApply ?? false;
        $availableTopics = $availableTopics ?? [];
        $counts = $counts ?? ['self' => 0, 'class' => 0, 'assigned' => 0, 'saved' => 0, 'history' => 0];
        $nextDueAt = $nextDueAt ?? null;
        $lastSubmittedAt = $lastSubmittedAt ?? null;
        $quickStartHref = $quickStartHref ?? null;
        $historyPaginator = $historyPaginator ?? null;

        // ── Biến của riêng trang này ──
        // SỬA 18/9 (khách: "để 1 tab lịch sử thôi") — trang chỉ còn ĐÚNG HAI mục: kho bài tập
        // (dùng chung partial với trang công khai) và Lịch sử làm bài. Bỏ hẳn 3 tab
        // Theo lớp / Bài được giao / Đã lưu.
        $isHistory = $tab === 'history';
        $catalogHref = route('student.practice.index');
        $historyHref = route('student.practice.index', ['tab' => 'history']);

        // Bản mẫu in số dạng 2 chữ số ("03") — giữ đúng cách trình bày đó.
        $pad2 = fn (int $n) => $n < 10 ? '0'.$n : (string) $n;
    @endphp

    <x-ws.page-header title="Luyện tập" icon="pen-line"
                      subtitle="Từng bước rõ ràng, mọi kết quả đều được lưu lại để bạn tiếp tục đúng lúc.">
        <x-slot:actions>
            <a href="{{ route('student.practiceByQuestion.setup') }}"
               class="inline-flex min-h-10 items-center gap-1.5 rounded-xl bg-white px-3 py-2 text-xs font-bold text-blue-700 shadow-sm transition-colors hover:bg-sky-50 lg:min-h-11">
                <x-lucide name="sparkles" class="h-3.5 w-3.5" />Vào luyện tập
            </a>
        </x-slot:actions>
    </x-ws.page-header>

    {{-- ══════ LUYỆN TẬP CỦA TÔI ══════ --}}
    <section class="mt-4 rounded-2xl border border-[#DDEAF0] bg-white p-4 shadow-[0_2px_10px_rgba(28,91,121,0.05)] sm:p-5">
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="rounded-lg bg-[#EAF5F8] p-1.5 text-[#126F91]"><x-lucide name="pen-line" class="h-3.5 w-3.5" /></span>
                <h2 class="text-sm font-bold text-[#123B68]">Luyện tập của tôi</h2>
            </div>
            {{-- <a href="{{ route('student.practiceByQuestion.setup') }}" class="text-xs font-bold text-[#126F91] transition hover:underline">
                Luyện theo câu <x-lucide name="chevron-right" class="inline h-3 w-3" />
            </a> --}}
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            {{-- 2 ô đầu chỉ để XEM (không còn tab riêng), ô Lịch sử bấm được. --}}
            <div class="rounded-xl bg-[#EEF4FA] p-4">
                <p class="text-[10px] font-bold uppercase text-[#365B7A]">Bài được giao</p>
                <p class="mt-1 text-2xl font-black text-[#365B7A]">{{ $pad2($counts['assigned']) }}</p>
                <p class="mt-2 text-[10px] text-[#6B8295]">
                    @if ($nextDueAt)
                        Hạn gần nhất: {{ $nextDueAt->format('H:i') }} ngày {{ $nextDueAt->format('d/m') }}
                    @else
                        Chưa có bài nào đang mở
                    @endif
                </p>
            </div>

            <div class="rounded-xl bg-[#FFF7E3] p-4">
                <p class="text-[10px] font-bold uppercase text-[#8E6B2E]">Bài theo lớp</p>
                <p class="mt-1 text-2xl font-black text-[#8E6B2E]">{{ $pad2($counts['class']) }}</p>
                <p class="mt-2 text-[10px] text-[#6B8295]">Bài tập lớp bạn đang theo học</p>
            </div>

            <a href="{{ $historyHref }}" class="rounded-xl bg-[#EFF9F5] p-4 transition hover:-translate-y-0.5 hover:shadow-sm">
                <p class="text-[10px] font-bold uppercase text-[#2F8A6B]">Lịch sử nộp</p>
                <p class="mt-1 text-2xl font-black text-[#2F8A6B]">{{ $pad2($counts['history']) }}</p>
                <p class="mt-2 text-[10px] text-[#6B8295]">
                    @if ($lastSubmittedAt)
                        Nộp gần nhất: {{ $lastSubmittedAt->format('H:i d/m/Y') }}
                    @else
                        Bạn chưa nộp bài nào
                    @endif
                </p>
            </a>
        </div>
    </section>

    {{-- ══════ ĐÚNG 2 MỤC: KHO BÀI TẬP | LỊCH SỬ ══════ --}}
    <div class="mt-4 flex items-center gap-1.5 overflow-x-auto rounded-2xl border border-[#DDEAF0] bg-white p-1.5 shadow-[0_2px_10px_rgba(28,91,121,0.05)]">
        <a href="{{ $catalogHref }}" @if (! $isHistory) aria-current="page" @endif
           class="inline-flex min-h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-1.5 text-[11px] font-bold transition-all {{ ! $isHistory ? 'bg-[#126F91] text-white shadow-sm' : 'text-[#45657D] hover:bg-[#F0F8FB] hover:text-[#126F91]' }}">
            <x-lucide name="pen-line" class="h-3.5 w-3.5" />Kho bài tập
        </a>
        <a href="{{ $historyHref }}" @if ($isHistory) aria-current="page" @endif
           class="inline-flex min-h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-xl px-3 py-1.5 text-[11px] font-bold transition-all {{ $isHistory ? 'bg-[#126F91] text-white shadow-sm' : 'text-[#45657D] hover:bg-[#F0F8FB] hover:text-[#126F91]' }}">
            <x-lucide name="history" class="h-3.5 w-3.5" />Lịch sử làm bài
            <span class="rounded-md px-1.5 py-0.5 text-[9px] font-bold {{ $isHistory ? 'bg-white/20 text-white' : 'bg-[#F2F6F8] text-[#607A90]' }}">{{ $counts['history'] }}</span>
        </a>
    </div>

    @if ($isHistory)
        {{-- ══════ LỊCH SỬ LÀM BÀI ══════ --}}
        <div class="mt-3 mb-6 divide-y divide-[#E7EFF3] overflow-hidden rounded-2xl border border-[#DDEAF0] bg-white shadow-[0_2px_10px_rgba(28,91,121,0.05)]">
            {{-- SỬA 24/9 (khách: "bỏ cột hành động đi", "thêm cột làm thời gian trong bao lâu")
                 — bỏ cột Hành động, cả DÒNG giờ là một liên kết đi thẳng tới trang kết quả
                 (vùng bấm rộng hơn hẳn cái nút cũ), chỗ trống dành cho cột Thời gian làm. --}}
            <div class="hidden bg-[#F4F8FB] px-4 py-2.5 text-[11px] font-bold uppercase tracking-[.06em] text-[#365B7A] lg:grid lg:grid-cols-[minmax(0,1fr)_180px_170px_150px]">
                <span>Bài đã nộp</span>
                <span>Chuyên đề</span>
                <span>Thời gian làm</span>
                <span>Kết quả</span>
            </div>

            @forelse ($items as $it)
                <a href="{{ $it['takeRoute'] ?? $catalogHref }}"
                   class="group grid grid-cols-1 gap-x-2.5 gap-y-2 border-l-2 border-transparent p-3 transition-all hover:border-l-[#2D7FA3] hover:bg-[#F8FBFC] sm:px-4 lg:grid-cols-[minmax(0,1fr)_180px_170px_150px] lg:items-center">
                    <div class="min-w-0">
                        <p class="line-clamp-2 text-[14px] font-semibold leading-5 text-[#123B68]">{{ $it['typeIcon'] ?? '' }} {{ $it['title'] }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-2 text-[11px] font-medium text-[#6B8295]">
                            <span class="inline-flex min-h-7 shrink-0 items-center gap-1 rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-1 text-[10px] font-bold text-[#365B7A]">
                                <x-lucide name="clipboard-list" class="h-3 w-3" />{{ $it['typeLabel'] ?: 'Đề luyện tập' }}
                            </span>
                            <span class="flex min-w-0 items-center gap-1 truncate">
                                <x-lucide name="sparkles" class="h-3 w-3 shrink-0 text-[#3B9374]" />
                                <span class="truncate">Nguồn: {{ $it['source'] ?: 'Ôn Thi 360' }}</span>
                            </span>
                        </p>
                    </div>

                    <div class="min-w-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Chuyên đề</span>
                        @if (! empty($it['topics'] ?? []))
                            <span class="block truncate rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-0.5 text-[12px] font-semibold text-[#365B7A]" title="{{ implode(', ', $it['topics']) }}">{{ implode(', ', $it['topics']) }}</span>
                        @else
                            <span class="text-[12px] text-[#8FA3B3]">—</span>
                        @endif
                    </div>

                    <div class="min-w-0">
                        <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Thời gian làm</span>
                        @if (! empty($it['duration']))
                            <span class="inline-flex items-center gap-1 text-[12px] font-semibold text-[#45657D]">
                                <x-lucide name="clock" class="h-3 w-3 shrink-0 text-[#8FA3B3]" />{{ $it['duration'] }}
                            </span>
                        @else
                            <span class="text-[12px] text-[#8FA3B3]">—</span>
                        @endif
                    </div>

                    <div class="flex min-w-0 items-center justify-between gap-2">
                        <span class="min-w-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Kết quả</span>
                            <x-ws.badge :tone="$it['tone'] ?? 'info'">{{ $it['status'] }}</x-ws.badge>
                        </span>
                        {{-- Mũi tên thay cho nút "Xem kết quả" cũ: vẫn nói được "bấm vào đây"
                             mà không chiếm hẳn một cột. --}}
                        <x-lucide name="chevron-right" class="h-4 w-4 shrink-0 text-[#8FA3B3] transition-colors group-hover:text-[#126F91]" />
                    </div>
                </a>
            @empty
                <div class="px-4 py-12 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#EAF5F8] text-[#126F91]"><x-lucide name="history" class="h-5 w-5" /></span>
                    <p class="mt-3 text-sm font-bold text-[#123B68]">Chưa có bài nào đã nộp</p>
                    <p class="mt-1 text-[12px] text-[#607A90]">Làm xong một đề, bài sẽ hiện ở đây kèm điểm và lối xem lại.</p>
                    <a href="{{ $catalogHref }}" class="mt-4 inline-flex items-center gap-1 text-[12px] font-bold text-[#126F91] hover:underline">Về kho bài tập <x-lucide name="chevron-right" class="h-3 w-3" /></a>
                </div>
            @endforelse
        </div>

        {{-- SỬA 23/9 (khách: "lịch sử làm bài dài quá, cho phân trang") — 12 dòng/trang. --}}
        <div class="mb-6">
            @include('partials.pager', ['paginator' => $historyPaginator, 'unit' => 'bài đã nộp'])
        </div>
    @else
        {{-- ══════ KHO BÀI TẬP — DÙNG CHUNG PARTIAL VỚI TRANG CÔNG KHAI ══════
             Cùng service (Public\PracticeService::indexData) nên bảng bài tập, bộ lọc chuyên
             đề/độ khó, thẻ đề thi và tỷ lệ AC giống hệt ngoài public. Ẩn hero tối vì khu học
             sinh đã có banner riêng ở trên. --}}
        <div class="mt-4 mb-6">
            @include('partials.practice-catalog', ['showCatalogHero' => false])
        </div>
    @endif

    {{-- <x-ws.tabs :tabs="$tabs" /> --}}

    {{-- SỬA 24/8 — khách yêu cầu ẨN hộp lọc "Loại câu hỏi"/"Chuyên đề" ở tab Tự luyện/Theo
         lớp/Bài được giao (lọc DANH SÁCH ĐỀ ở đây, "Chuyên đề" tạm dựa vào QuestionBank::name
         chứ chưa phải Tag thật — xem PracticeService::buildIndexData()). Lý do ẩn: lọc theo
         chuyên đề/dạng câu hỏi THẬT (dùng Tag) đã có sẵn ở lối "Luyện tập theo câu" ngay phía
         trên (CTA 🧠, dẫn vào student.practiceByQuestion.setup — luyện từng câu, bấm Next) nên
         khỏi trùng 2 nơi. CHỈ COMMENT LẠI — controller/service filter theo ?type=&?topic= vẫn
         chạy nguyên, chưa đụng gì, dán lại nguyên khối dưới đây để hiện lại nếu cần dùng tiếp. --}}
    {{--
    @if ($filtersApply)
        <div class="bg-white rounded-3xl border border-sky-100 p-4 lg:p-5 mb-6 space-y-4">
            <div>
                <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-2">Loại câu hỏi</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($typeFilters as $tf)
                        <a href="{{ $typeHref($tf['value']) }}" class="{{ $chipClass($type === $tf['value']) }}">
                            <span><x-lucide :name="$tf['icon']" class="h-3.5 w-3.5" /></span> {{ $tf['label'] }}
                        </a>
                    @endforeach
                    <span class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-[13px] border border-dashed border-sky-100 text-slate-300 bg-slate-50 cursor-not-allowed"
                          title="Chưa có dữ liệu độ khó cho câu hỏi để lọc">
                        <span><x-lucide name="lock" class="h-4 w-4" /></span> Độ khó
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
    --}}

    {{-- <div class="flex items-center justify-between mb-3">
        <p class="text-[13px] text-slate-400">{{ count($items) }} bài phù hợp</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse ($items as $it)
            @php $accent = $cardAccent($it['type'] ?? null); @endphp
            <a href="{{ $it['takeRoute'] ?? route('student.practice.index') }}"
               class="group relative flex flex-col h-full rounded-3xl bg-white border border-sky-100 p-5 pt-6 overflow-hidden transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 {{ $accent['hover'] }}">
                <span class="absolute inset-x-0 top-0 h-1.5 bg-gradient-to-r {{ $accent['bar'] }}"></span>

                <div class="flex items-start justify-between gap-2 mb-3">
                    <x-ws.icon-tile :emoji="$it['typeIcon'] ?? '📝'" :tone="$accent['tone']" />
                    <x-ws.badge :tone="$it['tone']">{{ $it['status'] }}</x-ws.badge>
                </div>

                <div class="mb-2">
                    <x-ws.badge tone="info">{{ $it['typeLabel'] ?? $it['type'] }}</x-ws.badge>
                </div>

                <h3 class="font-semibold text-slate-800 leading-snug line-clamp-2">{{ $it['title'] }}</h3>
                <p class="text-xs text-slate-400 mt-1.5">
                    {{ $it['source'] }}{{ $it['difficulty'] ? ' · Độ khó: '.$it['difficulty'] : '' }}
                </p>

                @if (! empty($it['topics'] ?? []))
                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach ($it['topics'] as $t)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500"><x-lucide name="book-open" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $t }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="mt-auto pt-4 flex items-center justify-end">
                    <span class="inline-flex items-center gap-1 text-[13px] font-medium text-blue-600 group-hover:gap-2 transition-all">
                        {{ $tab === 'history' ? 'Xem kết quả' : 'Làm bài' }}
                        <span aria-hidden="true">→</span>
                    </span>
                </div>
            </a>
        @empty
            <div class="col-span-full">
                <x-ws.empty-state title="Chưa có bài phù hợp bộ lọc" description="Thử bỏ bộ lọc hoặc khám phá thêm bài luyện tập công khai." actionLabel="Khám phá Luyện tập công khai" :actionHref="route('practice.index')" />
            </div>
        @endforelse
    </div> --}}
@endsection
