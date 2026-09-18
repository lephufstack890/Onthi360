@extends('layouts.guest')

@section('title', 'Kho bài tập Thuật toán & Lập trình')
@section('meta-description', 'Kho luyện tập Tin học của Ôn Thi 360 — bài tập theo chuyên đề có chấm tự động và đề thi luyện tập mô phỏng, lọc theo chuyên đề, độ khó và loại đề.')

@section('content')
{{-- ═══════════════ [PRACTICE] MÀN LUYỆN TẬP ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/PracticePage.jsx (PRACTICE-01 … PRACTICE-07).
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\PracticeService::indexData):
       · bảng bài tập <- $problems  (câu hỏi thật: mã, chuyên đề, độ khó, tỷ lệ AC tính từ
                         attempt_answers, trạng thái của chính người đang xem)
       · chuyên đề    <- $practiceTags  (App\Support\PracticeFilters — cùng nguồn với màn học sinh)
       · dạng câu     <- $practiceTypes
       · thẻ đề thi   <- $items     (đề luyện tập đã phát hành)
     Bản mẫu có vài con số minh hoạ không có nguồn dữ liệu (chuỗi ngày luyện tập, điểm cao
     nhất) — thay bằng số liệu thật tương ứng. --}}
@php
    $items = $items ?? [];
    $problems = $problems ?? [];
    $practiceTypes = $practiceTypes ?? [];
    $practiceTags = $practiceTags ?? [];
    $practiceTotal = $practiceTotal ?? 0;
    $canTakeDirectly = $canTakeDirectly ?? false;

    // 5 mức độ khó của bản mẫu.
    $difficulties = [
        ['id' => 'all', 'label' => 'Mọi độ khó', 'icon' => null, 'color' => 'border-[#DDEAF0] bg-[#F8FAFB] text-[#45657D]'],
        ['id' => 'easy', 'label' => 'Dễ', 'icon' => 'check-circle', 'color' => 'text-[#397C68] bg-[#EFF9F5] border-[#D4EDE2]'],
        ['id' => 'medium', 'label' => 'Trung bình', 'icon' => 'trending-up', 'color' => 'text-[#376B98] bg-[#EEF5FF] border-[#D4E3F7]'],
        ['id' => 'hard', 'label' => 'Khó', 'icon' => 'flame', 'color' => 'text-[#8E6B2E] bg-[#FFF7E3] border-[#F2E1B6]'],
        ['id' => 'expert', 'label' => 'Cực khó', 'icon' => 'award', 'color' => 'text-[#A15B5B] bg-[#FFF1F0] border-[#F3D8D6]'],
    ];

    // Dải chuyên đề: "Tất cả" + các chuyên đề có thật trong kho (tối đa 12 chuyên đề đầu).
    $topicChipIcons = ['route', 'git-branch', 'database', 'calculator', 'route', 'text-cursor-input'];

    // Hàng dữ liệu đưa sang Alpine để lọc/phân trang ngay tại chỗ.
    $problemRows = [];
    foreach ($problems as $p) {
        $problemRows[] = [
            'id' => $p['id'],
            'type' => $p['typeKey'],
            'tagIds' => $p['tagIds'],
            'difficulty' => $p['difficulty'],
            'status' => $p['status'],
            'search' => mb_strtolower(trim($p['title'].' '.$p['code'])),
        ];
    }
    $examRows = [];
    foreach ($items as $it) {
        $examRows[] = [
            'id' => $it['id'],
            'type' => $it['hasCoding'] ? 'coding' : 'quiz',
            'search' => mb_strtolower($it['title']),
        ];
    }

    $acCount = 0;
    $doingCount = 0;
    foreach ($problems as $p) {
        if ($p['status'] === 'ac') { $acCount++; }
        if ($p['status'] === 'doing') { $doingCount++; }
    }
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiPracticePage({{ Js::from(['problems' => $problemRows, 'exams' => $examRows, 'problemPageSize' => 5, 'examPageSize' => 4]) }})" class="flex flex-col gap-4 animate-fadeIn">

    {{-- ══════ [PRACTICE-01] HERO LUYỆN TẬP ══════ --}}
    <div class="relative overflow-hidden rounded-3xl border border-white/20 bg-gradient-to-r from-[#123B68] via-[#155A86] to-[#2A8B8A] p-5 text-white shadow-[0_12px_28px_rgba(18,59,104,0.1)] sm:p-6">
        <img src="{{ asset('assets/hero-practice.jpg') }}" alt="Kho luyện tập Ôn Thi 360"
             class="absolute inset-0 w-full h-full object-cover object-right pointer-events-none opacity-45 mix-blend-overlay">

        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-[11px] font-bold text-sky-50 backdrop-blur">
                    <x-lucide name="flame" class="w-3.5 h-3.5" />
                    <span>Đấu trường Luyện tập Online Judge 24/7</span>
                </div>

                <h1 class="text-2xl font-black leading-tight tracking-tight text-white sm:text-2xl">Kho bài tập Thuật toán & Lập trình</h1>

                <p class="mt-2 max-w-xl text-xs leading-5 text-sky-100 sm:text-sm sm:leading-6">
                    {{ number_format($practiceTotal) }} câu hỏi bám sát đề thi HSG Quốc gia, Tuyển sinh 10 Chuyên Tin
                    và chuẩn Olympic Tin học quốc tế. Hệ thống chấm bài tự động tức thì.
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2.5">
                    <a href="{{ $canTakeDirectly ? route('student.practiceByQuestion.setup') : route('login') }}"
                       class="flex min-h-10 items-center gap-1.5 rounded-lg bg-white px-3.5 py-2 text-[11px] font-black text-[#126F91] shadow-[0_5px_12px_rgba(4,45,105,0.14)] transition hover:-translate-y-0.5 hover:bg-[#F4FBFF] focus:outline-none focus-visible:ring-4 focus-visible:ring-white/40 active:scale-[.98]">
                        <x-lucide name="code-2" class="h-3.5 w-3.5" />Làm bài ngay<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                    </a>
                    <div class="flex min-h-9 items-center gap-1.5 rounded-lg border border-white/20 bg-white/15 px-2.5 py-1 text-[10px] font-bold backdrop-blur-sm">
                        <x-lucide name="check-circle" class="w-4 h-4 text-emerald-300" />
                        <span>Đã hoàn thành: {{ $acCount }} bài</span>
                    </div>
                    <div class="flex min-h-9 items-center gap-1.5 rounded-lg border border-white/20 bg-white/15 px-2.5 py-1 text-[10px] font-bold backdrop-blur-sm">
                        <x-lucide name="trending-up" class="w-4 h-4 text-amber-300" />
                        <span>Đang làm dở: {{ $doingCount }} bài</span>
                    </div>
                </div>
            </div>

            <div class="relative z-10 w-full rounded-2xl border border-white/20 bg-slate-950/10 p-3 shadow-lg backdrop-blur-md lg:w-72 lg:shrink-0">
                <p class="text-center text-xs font-bold uppercase tracking-[.08em] text-sky-100">Kho câu theo dạng</p>
                <div class="mt-3 space-y-2.5 text-xs">
                    @foreach (array_slice($practiceTypes, 0, 3) as $t)
                        @php $pct = $practiceTotal > 0 ? round($t['count'] / $practiceTotal * 100) : 0; @endphp
                        <div>
                            <div class="flex justify-between text-[11px] mb-1">
                                <span>{{ $t['label'] }}</span>
                                <span class="font-bold text-amber-300">{{ number_format($t['count']) }} câu</span>
                            </div>
                            <div class="w-full bg-white/20 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-amber-400 h-full rounded-full" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ══════ [PRACTICE-01A] CHỌN KIỂU LUYỆN ══════ --}}
    <div class="flex flex-col gap-3 rounded-2xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_10px_rgba(28,91,121,0.04)] sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-[.08em] text-[#607A90]">Không gian luyện tập</p>
            <p class="mt-0.5 text-sm font-extrabold text-[#123B68]">Chọn cách bạn muốn rèn luyện hôm nay</p>
        </div>
        <div role="tablist" aria-label="Kiểu luyện tập" class="grid grid-cols-2 gap-1 rounded-xl border border-[#DDEAF0] bg-[#F5F8FA] p-1 sm:w-auto">
            <button type="button" role="tab" :aria-selected="practiceMode === 'problems'" @click="changeMode('problems')"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-[11px] font-extrabold transition"
                    :class="practiceMode === 'problems' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'">
                <x-lucide name="code-2" class="h-3.5 w-3.5" />Bài tập chuyên đề
            </button>
            <button type="button" role="tab" :aria-selected="practiceMode === 'exams'" @click="changeMode('exams')"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-[11px] font-extrabold transition"
                    :class="practiceMode === 'exams' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'">
                <x-lucide name="file-text" class="h-3.5 w-3.5" />Đề thi luyện tập
            </button>
        </div>
    </div>

    {{-- ══════════════ CHẾ ĐỘ: BÀI TẬP CHUYÊN ĐỀ ══════════════ --}}
    <div x-show="practiceMode === 'problems'" class="flex flex-col gap-4">

        {{-- [PRACTICE-02] TABS & BỘ LỌC --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_10px_rgba(28,91,121,0.04)]">
            <div class="flex items-center justify-between gap-3 border-b border-[#E7EFF3] pb-3">
                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                    <button type="button" @click="setTab('all')" :aria-pressed="activeTab === 'all'"
                            class="min-h-10 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all whitespace-nowrap"
                            :class="activeTab === 'all' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-[#F2F8FA] hover:text-[#216F8E]'">Tất cả bài tập</button>
                    @foreach ($practiceTypes as $t)
                        <button type="button" @click="setTab(@js($t['value']))" :aria-pressed="activeTab === @js($t['value'])"
                                class="min-h-10 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all whitespace-nowrap"
                                :class="activeTab === @js($t['value']) ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-[#F2F8FA] hover:text-[#216F8E]'">{{ $t['icon'] }} {{ $t['label'] }}</button>
                    @endforeach
                </div>

                <span class="hidden text-[11px] font-medium text-[#607A90] sm:inline">
                    Hiển thị <strong x-text="filteredProblems.length"></strong> bài tập
                </span>
            </div>

            <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
                <div class="relative flex-1">
                    <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#607A90]" />
                    <input type="text" aria-label="Tìm kiếm bài tập" placeholder="Tìm theo tên bài hoặc mã bài (VD: DP_LIS)..."
                           x-model="searchQuery"
                           class="min-h-11 w-full rounded-xl border border-[#D5E3E9] bg-[#F8FAFB] py-2 pl-10 pr-4 text-[13px] font-medium text-[#183D5E] placeholder:text-[#8193A3] focus:border-[#2D7FA3] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#DDF1F6]">
                </div>

                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                    <span class="type-label shrink-0 text-[#45657D]">Độ khó:</span>
                    @foreach ($difficulties as $d)
                        <button type="button" @click="setDifficulty(@js($d['id']))" :aria-pressed="selectedDifficulty === @js($d['id'])"
                                class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                                :class="selectedDifficulty === @js($d['id']) ? 'border-[#123B68] bg-[#123B68] text-white' : '{{ $d['color'] }} hover:brightness-[.98]'">
                            @if ($d['icon'])
                                <x-lucide :name="$d['icon']" class="h-3.5 w-3.5" ::class="selectedDifficulty === @js($d['id']) ? 'text-white' : ''" />
                            @endif
                            {{ $d['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- [PRACTICE-03] CHUYÊN ĐỀ — bản mẫu mới bọc băng chuyên đề thành carousel có 2 nút
             cuộn hai bên để danh sách nhiều thẻ vẫn gọn. Cuộn bằng $refs của Alpine, không
             thêm mã script mới. --}}
        <div class="relative">
            <button type="button" aria-label="Cuộn chuyên đề sang trái" title="Cuộn sang trái"
                    @click="$refs.topicRail.scrollBy({ left: -260, behavior: 'smooth' })"
                    class="absolute left-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 place-items-center rounded-full border border-[#D6E3EF] bg-white/95 text-[#123B68] shadow-[0_3px_10px_rgba(18,59,104,0.1)] transition hover:bg-[#EEF4FA] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:grid">
                <x-lucide name="chevron-left" class="h-4 w-4" />
            </button>
            <div x-ref="topicRail" class="flex items-center gap-1.5 overflow-x-auto scroll-smooth no-scrollbar py-0.5 sm:px-9">
            <button type="button" @click="setTopic('all')" :aria-pressed="selectedTopic === 'all'"
                    class="inline-flex min-h-9 items-center gap-1 rounded-xl border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                    :class="selectedTopic === 'all' ? 'border-[#123B68] bg-[#123B68] text-white' : 'border-[#D6E3EF] bg-[#EEF4FA] text-[#365B7A] hover:border-[#B9CCDC] hover:bg-[#F5F8FC]'">
                <span class="grid h-4 w-4 place-items-center rounded-md" :class="selectedTopic === 'all' ? 'bg-white/60' : 'bg-white'">
                    <x-lucide name="code-2" class="h-3 w-3" ::class="selectedTopic === 'all' ? 'text-[#126F91]' : 'text-[#2D7FA3]'" />
                </span>
                Tất cả chuyên đề
            </button>
            @foreach (array_slice($practiceTags, 0, 12) as $i => $tag)
                @php $icon = $topicChipIcons[$i % count($topicChipIcons)]; @endphp
                <button type="button" @click="setTopic({{ $tag['id'] }})" :aria-pressed="selectedTopic === {{ $tag['id'] }}"
                        class="inline-flex min-h-9 items-center gap-1 rounded-xl border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                        :class="selectedTopic === {{ $tag['id'] }} ? 'border-[#123B68] bg-[#123B68] text-white' : 'border-[#D6E3EF] bg-[#EEF4FA] text-[#365B7A] hover:border-[#B9CCDC] hover:bg-[#F5F8FC]'">
                    <span class="grid h-4 w-4 place-items-center rounded-md" :class="selectedTopic === {{ $tag['id'] }} ? 'bg-white/60' : 'bg-white'">
                        <x-lucide :name="$icon" class="h-3 w-3" ::class="selectedTopic === {{ $tag['id'] }} ? 'text-[#126F91]' : 'text-[#4C83B0]'" />
                    </span>
                    {{ $tag['name'] }}
                </button>
            @endforeach
            </div>
            <button type="button" aria-label="Cuộn chuyên đề sang phải" title="Cuộn sang phải"
                    @click="$refs.topicRail.scrollBy({ left: 260, behavior: 'smooth' })"
                    class="absolute right-0 top-1/2 z-10 hidden h-8 w-8 -translate-y-1/2 place-items-center rounded-full border border-[#D6E3EF] bg-white/95 text-[#123B68] shadow-[0_3px_10px_rgba(18,59,104,0.1)] transition hover:bg-[#EEF4FA] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:grid">
                <x-lucide name="chevron-right" class="h-4 w-4" />
            </button>
        </div>

        {{-- [PRACTICE-04] DANH SÁCH BÀI — dựng lại theo bản mẫu MỚI (education-main/src/
             components/PracticePage.jsx, 16/9): bảng 5 cột trên desktop, xếp thẻ trên mobile.

             Khác bản dựng cũ: cột "Trạng thái" rộng 120px bị bỏ — huy hiệu AC thu nhỏ nằm ngay
             cạnh tên bài, còn "Đã làm N lần / Chưa làm" thành viên thuốc bên phải tiêu đề.

             KHÁC bản mẫu 1 chỗ, có chủ ý: bản mẫu có nút "Nhật ký nộp bài" mở hộp thoại lịch sử
             nộp. Trang công khai KHÔNG có nguồn dữ liệu đó (lịch sử nộp là dữ liệu riêng của
             từng học sinh, chỉ có trong khu đã đăng nhập) — dựng nút mở hộp thoại rỗng là bịa
             tính năng, nên chỗ đó đặt chip DẠNG CÂU HỎI thật, giữ nguyên nhịp bố cục. --}}
        <div class="divide-y divide-[#E7EFF3] overflow-hidden rounded-2xl border border-[#DDEAF0] bg-white shadow-[0_2px_10px_rgba(28,91,121,0.05)]">
            <div class="hidden bg-[#F4F8FB] px-4 py-2.5 text-[11px] font-bold uppercase tracking-[.06em] text-[#365B7A] lg:grid lg:grid-cols-[minmax(0,1fr)_150px_100px_136px_144px]">
                <span>Tên bài tập &amp; Mã</span>
                <span>Chuyên đề</span>
                <span>Độ khó</span>
                <span>Tỷ lệ AC</span>
                <span class="text-right">Hành động</span>
            </div>

            @foreach ($problems as $prob)
                @php
                    // SỬA 18/9 — trước đây mọi dòng đều trỏ về màn "chọn chuyên đề" chung, bấm
                    // bài nào cũng ra cùng một trang. Giờ kèm ?question=<id> để mở ĐÚNG bài vừa
                    // bấm (xem Student\PracticeByQuestionController::setup()).
                    $openHref = $canTakeDirectly
                        ? route('student.practiceByQuestion.setup', ['question' => $prob['id']])
                        : route('login');
                    $ctaLabel = match ($prob['status']) { 'ac' => 'Luyện lại', 'doing' => 'Tiếp tục', default => 'Làm bài' };
                    // Bản mẫu mới bỏ màu hổ phách cho trạng thái "đang làm": chỉ còn 2 màu nút.
                    $ctaClass = $prob['status'] === 'ac'
                        ? 'bg-[#2F8A6B] hover:bg-[#28795E]'
                        : 'bg-[#126F91] hover:bg-[#0F5E7B]';
                @endphp
                <div x-show="visibleProblemIds.includes({{ $prob['id'] }})" x-cloak
                     :style="{ order: visibleProblemIds.indexOf({{ $prob['id'] }}) }"
                     {{-- Sọc chẵn/lẻ tính theo VỊ TRÍ SAU KHI LỌC, không dùng even:/odd: của CSS —
                          danh sách được sắp lại bằng thuộc tính order nên nth-child sẽ sọc sai. --}}
                     :class="visibleProblemIds.indexOf({{ $prob['id'] }}) % 2 === 0 ? 'bg-[#FCFEFF]' : 'bg-[#F7FBFC]'"
                     class="grid grid-cols-1 gap-x-2.5 gap-y-2 border-l-2 border-transparent p-2.5 transition-all hover:border-l-[#2D7FA3] hover:bg-[#F8FBFC] sm:px-4 lg:grid-cols-[minmax(0,1fr)_150px_100px_136px_144px] lg:items-center lg:gap-2.5">

                    {{-- Tên bài --}}
                    <div class="min-w-0">
                        <div class="flex min-w-0 items-center gap-2">
                            @if ($prob['status'] === 'ac')
                                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-[#237052] text-white shadow-[0_3px_8px_rgba(35,112,82,0.2)]" title="Đã AC" aria-label="Đã AC">
                                    <x-lucide name="check-circle-2" class="h-4 w-4" />
                                </span>
                            @endif
                            <a href="{{ $openHref }}" title="{{ $prob['title'] }}"
                               class="min-w-0 flex-1 line-clamp-2 text-left text-[14px] font-semibold leading-5 text-[#123B68] transition-colors hover:text-[#126F91] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:text-[15px]">{{ $prob['title'] }}</a>
                            <span class="mt-0.5 shrink-0 whitespace-nowrap rounded-full px-2 py-1 text-[10px] font-bold {{ $prob['userSubmissions'] > 0 ? 'bg-[#EFF9F5] text-[#2F8A6B]' : 'bg-[#F2F6F8] text-[#607A90]' }}">
                                {{ $prob['userSubmissions'] > 0 ? 'Đã làm '.$prob['userSubmissions'].' lần' : 'Chưa làm' }}
                            </span>
                        </div>

                        <p class="mt-1 flex flex-wrap items-center gap-2 text-[11px] font-medium text-[#2F7F67]">
                            <span class="inline-flex min-h-7 shrink-0 items-center gap-1 rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-1 text-[10px] font-bold text-[#365B7A] shadow-[0_2px_6px_rgba(18,59,104,0.08)]">
                                <x-lucide name="clipboard-list" class="h-3 w-3" />{{ $prob['typeLabel'] }}
                            </span>
                            <span class="flex min-w-0 items-center gap-1 truncate">
                                <x-lucide name="sparkles" class="h-3 w-3 shrink-0 text-[#3B9374]" />
                                <span class="truncate">Nguồn: {{ $prob['subjectLabel'] ?: 'Kho luyện tập Ôn Thi 360' }}</span>
                            </span>
                        </p>

                        <div class="mt-1 flex items-center gap-2 font-mono text-[11px] text-[#6B8295]">
                            <span>Mã: {{ $prob['code'] }}</span>
                            <span>•</span>
                            <span>{{ $prob['timeLimit'] }} / {{ $prob['memoryLimit'] }}</span>
                        </div>
                    </div>

                    {{-- Chuyên đề / độ khó / tỷ lệ AC — trên mobile là 3 ô có nhãn, lên lg thì
                         hoà vào đúng 3 cột của bảng nhờ lg:contents. --}}
                    <div class="col-span-1 grid grid-cols-2 gap-2 lg:contents">
                        <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Chuyên đề</span>
                            <span class="block truncate rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-0.5 text-[12px] font-semibold text-[#365B7A] lg:inline-block" title="{{ $prob['topicLabel'] }}">{{ $prob['topicLabel'] }}</span>
                        </div>

                        <div class="min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Độ khó</span>
                            <div class="flex flex-col items-start gap-0.5">
                                <div class="inline-flex items-center gap-0.5" role="img" aria-label="Độ khó {{ $prob['difficultyLevel'] }} trên 5 sao">
                                    @for ($star = 0; $star < 5; $star++)
                                        <x-lucide name="star" class="h-3.5 w-3.5 {{ $star < $prob['difficultyLevel'] ? 'fill-amber-400 text-amber-500' : 'text-slate-200' }}" />
                                    @endfor
                                </div>
                                <span class="text-[11px] font-semibold text-[#607A90]">{{ $prob['difficultyLabel'] ?? 'Trung bình' }}</span>
                            </div>
                        </div>

                        <div class="col-span-2 min-w-0 rounded-lg border border-[#E7EFF3] bg-[#F8FBFC] px-2.5 py-2 lg:col-span-1 lg:rounded-none lg:border-0 lg:bg-transparent lg:p-0">
                            <span class="mb-0.5 block text-[10px] font-bold uppercase tracking-wide text-[#6B8295] lg:hidden">Tỷ lệ AC</span>
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-black text-[#123B68]">{{ $prob['acRate'] }}%</span>
                                <span class="text-[9px] font-bold uppercase tracking-wide text-[#6B8295]">AC</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[#EAF0F3]" aria-label="Tỷ lệ AC {{ $prob['acRate'] }}%">
                                <div class="h-full rounded-full bg-[#2F8A6B] transition-all" style="width: {{ $prob['acRate'] }}%"></div>
                            </div>
                            <p class="mt-1 text-[9px] text-[#6B8295]">{{ number_format($prob['acceptedCount']) }}/{{ number_format($prob['submissionCount']) }} lượt toàn hệ thống</p>
                        </div>
                    </div>

                    {{-- Hành động --}}
                    <div class="col-span-1 flex items-center justify-end gap-2 border-t border-[#E7EFF3] pt-2 lg:col-auto lg:flex-col lg:items-stretch lg:gap-1 lg:border-t-0 lg:pt-0">
                        <a href="{{ $openHref }}"
                           class="flex min-h-9 min-w-0 flex-1 items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-[12px] font-bold text-white shadow-none transition-colors active:scale-[.98] lg:w-full {{ $ctaClass }}">
                            <x-lucide name="code" class="h-3.5 w-3.5" />
                            <span>{{ $ctaLabel }}</span>
                        </a>
                    </div>
                </div>
            @endforeach

            {{-- Phân trang nằm NGAY TRONG khung danh sách, trên dải nền nhạt — đúng bản mẫu mới. --}}
            <div x-show="problemTotalPages > 1" x-cloak class="border-t border-[#E7EFF3] bg-[#F4F8FB] px-3 sm:px-4">
                <nav aria-label="Phân trang bài tập chuyên đề"
                     class="flex flex-col items-center justify-between gap-2 py-2 sm:flex-row">
                    <span class="text-[11px] text-[#607A90]">Trang <b class="text-[#45657D]" x-text="problemPage"></b> / <span x-text="problemTotalPages"></span></span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" aria-label="Trang trước" :disabled="problemPage === 1" @click="problemPageIndex = Math.max(1, problemPage - 1)"
                                class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                            <x-lucide name="chevron-left" class="h-4 w-4" />
                        </button>
                        <template x-for="n in problemTotalPages" :key="'pp' + n">
                            <button type="button" :aria-label="'Trang ' + n" :aria-current="problemPage === n ? 'page' : null" @click="problemPageIndex = n"
                                    class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                                    :class="problemPage === n ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'"
                                    x-text="n"></button>
                        </template>
                        <button type="button" aria-label="Trang sau" :disabled="problemPage === problemTotalPages" @click="problemPageIndex = Math.min(problemTotalPages, problemPage + 1)"
                                class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                            <x-lucide name="chevron-right" class="h-4 w-4" />
                        </button>
                    </div>
                </nav>
            </div>
        </div>

        <div x-show="filteredProblems.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
            <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
            <h2 class="mt-3 text-sm font-black text-[#123B68]">Không có bài tập phù hợp</h2>
            <p class="mt-1 text-xs text-[#607A90]">Hãy đổi chuyên đề hoặc mức độ để xem kho bài khác.</p>
            <button type="button" @click="resetProblemFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
        </div>
    </div>

    {{-- ══════════════ CHẾ ĐỘ: ĐỀ THI LUYỆN TẬP ══════════════ --}}
    <div x-show="practiceMode === 'exams'" x-cloak class="flex flex-col gap-4">

        {{-- [PRACTICE-05] BỘ LỌC ĐỀ THI --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_10px_rgba(28,91,121,0.04)] md:flex-row md:items-center md:justify-between">
            <div class="relative min-w-0 flex-1">
                <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#607A90]" />
                <input type="text" aria-label="Tìm kiếm đề thi" placeholder="Tìm đề thi theo tên hoặc mã đề..." x-model="searchQuery"
                       class="min-h-10 w-full rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] py-2 pl-10 pr-4 text-xs text-slate-800 placeholder:text-[#6B8295] focus:border-[#9DC8D7] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#EAF5F8]">
            </div>
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                @foreach ([['all', 'Tất cả đề thi'], ['coding', 'Có bài lập trình'], ['quiz', 'Trắc nghiệm & điền đáp án']] as [$key, $label])
                    <button type="button" @click="setExamType(@js($key))" :aria-pressed="selectedExamType === @js($key)"
                            class="min-h-9 whitespace-nowrap rounded-lg border px-2.5 py-1 text-[11px] font-bold transition"
                            :class="selectedExamType === @js($key) ? 'border-[#123B68] bg-[#123B68] text-white' : 'border-[#D6E3EF] bg-[#EEF4FA] text-[#365B7A] hover:border-[#B9CCDC] hover:bg-[#F5F8FC]'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 px-1">
            <div class="flex items-center gap-2 text-[11px] text-[#607A90]">
                <x-lucide name="file-text" class="h-3.5 w-3.5 text-[#4C83B0]" />
                <span>Hiển thị <b class="text-[#45657D]" x-text="filteredExams.length"></b> đề thi luyện tập</span>
            </div>
            <span class="hidden text-[11px] text-[#6B8295] sm:inline">Mỗi đề mô phỏng một lượt thi hoàn chỉnh</span>
        </div>

        {{-- [PRACTICE-06] TỔNG QUAN ĐỀ THI --}}
        @php
            $examTotal = count($items);
            $examCoding = 0;
            $examQuestions = 0;
            foreach ($items as $it) {
                if ($it['hasCoding']) { $examCoding++; }
                $examQuestions += (int) $it['itemsCount'];
            }
        @endphp
        <div class="grid gap-2 sm:grid-cols-3">
            <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#EAF5F8] text-[#126F91]"><x-lucide name="file-text" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#607A90]">Kho đề thi</p>
                        <p class="text-lg font-bold leading-5 text-[#123B68]">{{ $examTotal }}</p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-[#45657D]">Đề luyện tập đã phát hành</p>
            </div>
            <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#FFF7E3] text-[#B68032]"><x-lucide name="clock" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#607A90]">Có bài lập trình</p>
                        <p class="text-lg font-bold leading-5 text-[#123B68]">{{ $examCoding }}</p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-[#45657D]">Chấm bằng bộ test tự động</p>
            </div>
            <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#EFF9F5] text-[#2F8A6B]"><x-lucide name="award" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#607A90]">Tổng số câu</p>
                        <p class="text-lg font-bold leading-5 text-[#123B68]">{{ number_format($examQuestions) }}</p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-[#45657D]">Trong toàn bộ kho đề luyện tập</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 px-1 pt-1">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.08em] text-[#607A90]">Danh sách đề thi</p>
                <p class="mt-0.5 text-sm font-bold text-[#123B68]">Chọn một phiên thi để bắt đầu</p>
            </div>
            <span class="hidden text-[11px] text-[#6B8295] sm:inline"><span x-text="filteredExams.length"></span> đề phù hợp</span>
        </div>

        {{-- [PRACTICE-07] CARD ĐỀ THI --}}
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($items as $i => $exam)
                @php
                    $examHref = $canTakeDirectly ? route('student.assessment.take', $exam['id']) : route('login');
                    $borderClass = $exam['hasCoding'] ? 'border-[#E7D6AB]' : 'border-[#BFDCE5]';
                    $badgeClass = $exam['hasCoding'] ? 'border-[#F2E1B6] bg-[#FFF7E3] text-[#8E6B2E]' : 'border-[#D4EDE2] bg-[#EFF9F5] text-[#397C68]';
                    $badgeLabel = $exam['hasCoding'] ? 'Có bài lập trình' : 'Chấm tự động';
                    $btnClass = $exam['hasCoding'] ? 'bg-[#B68032] hover:bg-[#9F702A]' : 'bg-[#126F91] hover:bg-[#0F5E7B]';
                @endphp
                <article x-show="visibleExamIds.includes({{ $exam['id'] }})" x-cloak
                         :style="{ order: visibleExamIds.indexOf({{ $exam['id'] }}) }"
                         class="group flex min-h-full flex-col overflow-hidden rounded-2xl border bg-white shadow-[0_2px_12px_rgba(28,91,121,0.06)] transition hover:-translate-y-0.5 hover:shadow-[0_8px_18px_rgba(28,91,121,0.09)] {{ $borderClass }}">
                    <div class="relative flex h-32 items-center justify-center overflow-hidden bg-[#F8FBFC] p-2">
                        <img src="{{ asset('assets/book-img-'.(($i % 4) + 1).'.png') }}" alt="{{ $exam['title'] }}"
                             class="h-full max-w-full object-contain transition-transform duration-300 group-hover:scale-[1.03]">
                        <span class="absolute left-3 top-3 rounded-lg border px-2 py-1 text-[11px] font-bold {{ $badgeClass }}">{{ $badgeLabel }}</span>
                        <span class="absolute bottom-2 left-3 rounded-md bg-white/90 px-2 py-1 font-mono text-[10px] font-bold text-[#45657D]">#{{ $exam['id'] }}</span>
                    </div>
                    <div class="flex flex-1 flex-col p-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-[#4C83B0]">
                                <x-lucide name="file-text" class="h-3.5 w-3.5" />Đề luyện tập
                            </div>
                            <span class="text-[10px] font-bold text-[#6B8295]">Thi mô phỏng</span>
                        </div>
                        <h3 class="mt-1 line-clamp-2 text-sm font-bold leading-5 text-[#123B68]">{{ $exam['title'] }}</h3>
                        <p class="type-body mt-1 line-clamp-2 text-[11px]">
                            {{ $exam['itemsCount'] }} câu · {{ $exam['totalPoints'] ?: '—' }} điểm{{ $exam['durationMinutes'] ? ' · '.$exam['durationMinutes'].' phút' : '' }}
                        </p>

                        <div class="mt-2.5 grid grid-cols-3 gap-1 rounded-xl border border-[#E7EFF3] bg-[#F8FBFC] p-1.5 text-center">
                            <div>
                                <x-lucide name="timer" class="mx-auto h-3.5 w-3.5 text-[#2D7FA3]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#45657D]">{{ $exam['durationMinutes'] ? $exam['durationMinutes'].' phút' : 'Không giới hạn' }}</p>
                                <p class="text-[9px] text-[#6B8295]">Thời lượng</p>
                            </div>
                            <div>
                                <x-lucide name="code-2" class="mx-auto h-3.5 w-3.5 text-[#786BB1]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#45657D]">{{ $exam['itemsCount'] }} bài</p>
                                <p class="text-[9px] text-[#6B8295]">Cấu trúc đề</p>
                            </div>
                            <div>
                                <x-lucide name="trending-up" class="mx-auto h-3.5 w-3.5 text-[#3B9374]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#45657D]">{{ $exam['totalPoints'] ?: '—' }}</p>
                                <p class="text-[9px] text-[#6B8295]">Tổng điểm</p>
                            </div>
                        </div>

                        {{-- SỬA 12/9 — khối "Tiến độ của bạn" theo source mới; số liệu từ attempts thật của chính người đang xem. --}}
                        @php
                            $progressTone = $exam['progressStatus'] === 'open' ? '#126F91' : ($exam['progressStatus'] === 'doing' ? '#B68032' : '#2F8A6B');
                        @endphp
                        <div class="mt-2.5">
                            <div class="flex items-center justify-between gap-2 text-[10px]">
                                <span class="font-bold text-[#45657D]">Tiến độ của bạn</span>
                                <span class="font-bold" style="color: {{ $progressTone }}">{{ $exam['progressLabel'] }}</span>
                            </div>
                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-[#EAF0F3]">
                                <div class="h-full rounded-full" style="width: {{ $exam['progress'] }}%; background-color: {{ $progressTone }}"></div>
                            </div>
                        </div>

                        <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-[#E7EFF3] pt-2.5 mt-auto">
                            <div>
                                <p class="text-[9px] text-[#6B8295]">Cách chấm</p>
                                <p class="mt-0.5 text-[11px] font-bold text-[#123B68]">{{ $exam['hasCoding'] ? 'Bộ test tự động' : 'Chấm tự động ngay' }}</p>
                            </div>
                            <a href="{{ $examHref }}"
                               class="flex min-h-10 min-w-[148px] items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-[11px] font-extrabold text-white shadow-[0_4px_10px_rgba(18,111,145,0.12)] transition hover:-translate-y-0.5 active:scale-[.98] {{ $btnClass }}">
                                {{ $canTakeDirectly ? 'Bắt đầu làm đề' : 'Đăng nhập để làm' }}<x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Phân trang đề thi --}}
        <nav aria-label="Phân trang đề thi luyện tập" x-show="examTotalPages > 1" x-cloak
             class="mt-3 flex flex-col items-center justify-between gap-2 rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] p-2 sm:flex-row">
            <span class="text-[11px] text-[#607A90]">Trang <b class="text-[#45657D]" x-text="examPage"></b> / <span x-text="examTotalPages"></span></span>
            <div class="flex items-center gap-1.5">
                <button type="button" aria-label="Trang trước" :disabled="examPage === 1" @click="examPageIndex = Math.max(1, examPage - 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-left" class="h-4 w-4" />
                </button>
                <template x-for="n in examTotalPages" :key="'ep' + n">
                    <button type="button" :aria-label="'Trang ' + n" :aria-current="examPage === n ? 'page' : null" @click="examPageIndex = n"
                            class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                            :class="examPage === n ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#45657D] hover:bg-white'"
                            x-text="n"></button>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="examPage === examTotalPages" @click="examPageIndex = Math.min(examTotalPages, examPage + 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </nav>

        <div x-show="filteredExams.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
            <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
            <h2 class="mt-3 text-sm font-black text-[#123B68]">Không có đề thi phù hợp</h2>
            <p class="mt-1 text-xs text-[#607A90]">Thử đổi loại đề hoặc từ khóa tìm kiếm.</p>
            <button type="button" @click="resetExamFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.practice-page-script')
@endpush
