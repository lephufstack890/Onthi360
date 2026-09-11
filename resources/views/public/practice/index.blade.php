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
        ['id' => 'all', 'label' => 'Mọi độ khó', 'icon' => null, 'color' => 'border-[#DDEAF0] bg-[#F8FAFB] text-[#536D86]'],
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
<div x-data="onthiPracticePage({{ Js::from(['problems' => $problemRows, 'exams' => $examRows, 'problemPageSize' => 5, 'examPageSize' => 4]) }})" class="flex flex-col gap-4">

    {{-- ══════ [PRACTICE-01] HERO LUYỆN TẬP ══════ --}}
    <div class="relative overflow-hidden rounded-3xl border border-sky-200/80 bg-gradient-to-r from-[#0B3C78] via-[#0050A0] to-[#188DB0] p-5 text-white shadow-[0_10px_35px_rgba(0,100,220,0.08)] sm:p-6 lg:p-7">
        <img src="{{ asset('assets/hero-practice.jpg') }}" alt="Kho luyện tập Ôn Thi 360"
             class="absolute inset-0 w-full h-full object-cover object-right pointer-events-none opacity-45 mix-blend-overlay">

        <div class="relative z-10 flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/15 px-3 py-1 text-[11px] font-bold text-sky-50 backdrop-blur">
                    <x-lucide name="flame" class="w-3.5 h-3.5" />
                    <span>Đấu trường Luyện tập Online Judge 24/7</span>
                </div>

                <h1 class="text-2xl font-black leading-tight tracking-tight text-white sm:text-2xl">Kho bài tập Thuật toán &amp; Lập trình</h1>

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
            <p class="text-[11px] font-bold uppercase tracking-[.08em] text-[#71869A]">Không gian luyện tập</p>
            <p class="mt-0.5 text-sm font-extrabold text-[#123B68]">Chọn cách bạn muốn rèn luyện hôm nay</p>
        </div>
        <div role="tablist" aria-label="Kiểu luyện tập" class="grid grid-cols-2 gap-1 rounded-xl border border-[#DDEAF0] bg-[#F5F8FA] p-1 sm:w-auto">
            <button type="button" role="tab" :aria-selected="practiceMode === 'problems'" @click="changeMode('problems')"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-[11px] font-extrabold transition"
                    :class="practiceMode === 'problems' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#536D86] hover:bg-white'">
                <x-lucide name="code-2" class="h-3.5 w-3.5" />Bài tập chuyên đề
            </button>
            <button type="button" role="tab" :aria-selected="practiceMode === 'exams'" @click="changeMode('exams')"
                    class="flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-3 text-[11px] font-extrabold transition"
                    :class="practiceMode === 'exams' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#536D86] hover:bg-white'">
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
                            :class="activeTab === 'all' ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#536D86] hover:bg-[#F2F8FA] hover:text-[#216F8E]'">Tất cả bài tập</button>
                    @foreach ($practiceTypes as $t)
                        <button type="button" @click="setTab(@js($t['value']))" :aria-pressed="activeTab === @js($t['value'])"
                                class="min-h-10 rounded-lg px-3.5 py-1.5 text-xs font-bold transition-all whitespace-nowrap"
                                :class="activeTab === @js($t['value']) ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#536D86] hover:bg-[#F2F8FA] hover:text-[#216F8E]'">{{ $t['icon'] }} {{ $t['label'] }}</button>
                    @endforeach
                </div>

                <span class="hidden text-[11px] font-medium text-[#71869A] sm:inline">
                    Hiển thị <strong x-text="filteredProblems.length"></strong> bài tập
                </span>
            </div>

            <div class="flex flex-col items-stretch justify-between gap-3 md:flex-row md:items-center">
                <div class="relative flex-1">
                    <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#71869A]" />
                    <input type="text" aria-label="Tìm kiếm bài tập" placeholder="Tìm theo tên bài hoặc mã bài..."
                           x-model="searchQuery"
                           class="min-h-11 w-full rounded-xl border border-[#D5E3E9] bg-[#F8FAFB] py-2 pl-10 pr-4 text-[13px] font-medium text-[#183D5E] placeholder:text-[#8193A3] focus:border-[#2D7FA3] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#DDF1F6]">
                </div>

                <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                    <span class="type-label shrink-0 text-[#536D86]">Độ khó:</span>
                    @foreach ($difficulties as $d)
                        <button type="button" @click="setDifficulty(@js($d['id']))" :aria-pressed="selectedDifficulty === @js($d['id'])"
                                class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                                :class="selectedDifficulty === @js($d['id']) ? 'border-[#9DC8D7] bg-[#EAF5F8] text-[#126F91]' : '{{ $d['color'] }} hover:brightness-[.98]'">
                            @if ($d['icon'])
                                <x-lucide :name="$d['icon']" class="h-3.5 w-3.5" />
                            @endif
                            {{ $d['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- [PRACTICE-03] CHUYÊN ĐỀ --}}
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
            <button type="button" @click="setTopic('all')" :aria-pressed="selectedTopic === 'all'"
                    class="inline-flex min-h-9 items-center gap-1 rounded-xl border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                    :class="selectedTopic === 'all' ? 'border-[#9DC8D7] bg-[#EAF5F8] text-[#126F91]' : 'border-[#DDEAF0] bg-[#F8FAFB] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">
                <span class="grid h-4 w-4 place-items-center rounded-md" :class="selectedTopic === 'all' ? 'bg-white/60' : 'bg-white'">
                    <x-lucide name="code-2" class="h-3 w-3" ::class="selectedTopic === 'all' ? 'text-[#126F91]' : 'text-[#2D7FA3]'" />
                </span>
                Tất cả chuyên đề
            </button>
            @foreach (array_slice($practiceTags, 0, 12) as $i => $tag)
                @php $icon = $topicChipIcons[$i % count($topicChipIcons)]; @endphp
                <button type="button" @click="setTopic({{ $tag['id'] }})" :aria-pressed="selectedTopic === {{ $tag['id'] }}"
                        class="inline-flex min-h-9 items-center gap-1 rounded-xl border px-2.5 py-1 text-[11px] font-bold transition-all whitespace-nowrap"
                        :class="selectedTopic === {{ $tag['id'] }} ? 'border-[#9DC8D7] bg-[#EAF5F8] text-[#126F91]' : 'border-[#DDEAF0] bg-[#F8FAFB] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">
                    <span class="grid h-4 w-4 place-items-center rounded-md" :class="selectedTopic === {{ $tag['id'] }} ? 'bg-white/60' : 'bg-white'">
                        <x-lucide :name="$icon" class="h-3 w-3" ::class="selectedTopic === {{ $tag['id'] }} ? 'text-[#126F91]' : 'text-[#4C83B0]'" />
                    </span>
                    {{ $tag['name'] }}
                </button>
            @endforeach
        </div>

        {{-- [PRACTICE-04] DANH SÁCH BÀI --}}
        <div class="divide-y divide-[#E7EFF3] overflow-hidden rounded-2xl border border-[#DDEAF0] bg-white shadow-[0_2px_12px_rgba(28,91,121,0.06)]">
            <div class="hidden bg-[#F8FAFB] px-5 py-3.5 text-[11px] font-bold uppercase tracking-[.06em] text-[#71869A] lg:grid lg:grid-cols-[88px_1fr_180px_120px_132px_148px]">
                <span>Trạng thái</span>
                <span>Tên bài tập &amp; Mã</span>
                <span>Chuyên đề</span>
                <span>Độ khó</span>
                <span>Tỷ lệ AC</span>
                <span class="text-right">Hành động</span>
            </div>

            @foreach ($problems as $prob)
                @php
                    $openHref = $canTakeDirectly
                        ? route('student.practiceByQuestion.setup')
                        : route('login');
                    $ctaLabel = match ($prob['status']) { 'ac' => 'Luyện lại', 'doing' => 'Tiếp tục', default => 'Làm bài' };
                    $ctaClass = match ($prob['status']) {
                        'ac' => 'bg-[#2F8A6B] hover:bg-[#28795E]',
                        'doing' => 'bg-[#B68032] hover:bg-[#9F702A]',
                        default => 'bg-[#126F91] hover:bg-[#0F5E7B]',
                    };
                @endphp
                <div x-show="visibleProblemIds.includes({{ $prob['id'] }})" x-cloak
                     :style="'order:' + visibleProblemIds.indexOf({{ $prob['id'] }})"
                     class="grid grid-cols-[52px_minmax(0,1fr)] gap-x-3 gap-y-2 border-l-2 border-transparent p-3.5 transition-all hover:border-l-[#2D7FA3] hover:bg-[#F8FBFC] sm:px-5 lg:grid-cols-[88px_1fr_180px_120px_132px_148px] lg:items-center lg:gap-3">

                    {{-- Trạng thái --}}
                    <div class="row-span-3 flex items-start justify-center pt-0.5 lg:row-span-1 lg:justify-start lg:pt-0">
                        @if ($prob['status'] === 'ac')
                            <div class="flex flex-col items-center gap-1 lg:items-start">
                                <span class="grid h-11 w-11 place-items-center rounded-2xl border-2 border-[#8BD5B4] bg-gradient-to-br from-[#DFF8EC] to-[#EFF9F5] text-[#188B67] shadow-[0_5px_12px_rgba(59,147,116,0.18)]"><x-lucide name="check-circle" class="h-5 w-5" /></span>
                                <span class="text-[11px] font-extrabold text-[#188B67]">AC</span>
                            </div>
                        @elseif ($prob['status'] === 'doing')
                            <div class="flex flex-col items-center gap-1 lg:items-start">
                                <span class="grid h-11 w-11 place-items-center rounded-2xl border-2 border-[#E7C674] bg-gradient-to-br from-[#FFF0C4] to-[#FFF7E3] text-[#A96D09] shadow-[0_5px_12px_rgba(211,154,62,0.2)]"><x-lucide name="clock" class="h-5 w-5" /></span>
                                <span class="text-[11px] font-extrabold text-[#A96D09]">Đang làm</span>
                            </div>
                        @else
                            <div class="flex flex-col items-center gap-1 lg:items-start">
                                <span class="grid h-11 w-11 place-items-center rounded-2xl border-2 border-[#BFD4E6] bg-gradient-to-br from-[#EAF2FA] to-[#F5F8FA] text-[#4B7EA7] shadow-[0_5px_12px_rgba(75,126,167,0.14)]"><x-lucide name="circle" class="h-5 w-5" /></span>
                                <span class="text-[11px] font-extrabold text-[#4B7EA7]">Chưa nộp</span>
                            </div>
                        @endif
                    </div>

                    {{-- Tên bài --}}
                    <div class="min-w-0">
                        <a href="{{ $openHref }}" title="{{ $prob['title'] }}"
                           class="block max-w-full line-clamp-2 text-left text-sm font-bold leading-5 text-[#123B68] transition-colors hover:text-[#126F91] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#CBEAF1] sm:text-[15px]">{{ $prob['title'] }}</a>
                        <p class="mt-1 flex items-center gap-1 text-[10px] font-semibold text-amber-700">
                            <x-lucide name="sparkles" class="h-3 w-3 shrink-0 text-amber-500" />
                            <span>Dạng: {{ $prob['typeLabel'] }}{{ $prob['subjectLabel'] ? ' · '.$prob['subjectLabel'] : '' }}</span>
                        </p>
                        <div class="mt-1 flex items-center gap-2 font-mono text-[11px] text-[#8A9BAD]">
                            <span>Mã: {{ $prob['code'] }}</span>
                            <span>•</span>
                            <span>{{ $prob['timeLimit'] }} / {{ $prob['memoryLimit'] }}</span>
                        </div>
                    </div>

                    {{-- Chuyên đề / độ khó / AC --}}
                    <div class="flex min-w-0 flex-wrap items-center gap-1.5 lg:contents">
                        <div>
                            <span class="rounded-lg border border-[#DDEAF0] bg-[#F5F8FA] px-2 py-1 text-[11px] font-semibold text-[#536D86] sm:text-xs">{{ $prob['topicLabel'] }}</span>
                        </div>

                        <div class="flex flex-col items-start gap-0.5">
                            <div class="inline-flex items-center gap-0.5" role="img" aria-label="Độ khó {{ $prob['difficultyLevel'] }} trên 5 sao">
                                @for ($star = 0; $star < 5; $star++)
                                    <x-lucide name="star" class="h-3.5 w-3.5 {{ $star < $prob['difficultyLevel'] ? 'fill-amber-400 text-amber-500' : 'text-slate-200' }}" />
                                @endfor
                                <span class="ml-1 text-[10px] font-bold text-[#71869A]">{{ $prob['difficultyLevel'] }}/5</span>
                            </div>
                            <span class="text-[10px] font-semibold text-[#71869A]">{{ $prob['points'] }} điểm</span>
                        </div>

                        <div class="min-w-[132px]">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-sm font-black text-[#123B68]">{{ $prob['acRate'] }}%</span>
                                <span class="text-[9px] font-bold uppercase tracking-wide text-[#71869A]">AC</span>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-[#EAF0F3]" aria-label="Tỷ lệ AC {{ $prob['acRate'] }}%">
                                <div class="h-full rounded-full bg-[#2F8A6B] transition-all" style="width: {{ $prob['acRate'] }}%"></div>
                            </div>
                            <p class="mt-1 text-[9px] text-[#8A9BAD]">{{ number_format($prob['acceptedCount']) }}/{{ number_format($prob['submissionCount']) }} lượt toàn hệ thống</p>
                            <p class="mt-0.5 text-[10px] font-semibold text-[#536D86]">Bạn đã nộp {{ $prob['userSubmissions'] }} lần</p>
                        </div>
                    </div>

                    {{-- Hành động --}}
                    <div class="col-span-2 flex justify-end lg:col-auto lg:w-auto">
                        <a href="{{ $openHref }}"
                           class="flex min-h-9 min-w-[96px] items-center justify-center gap-1 rounded-lg px-2 py-1.5 text-[10.5px] font-bold text-white shadow-none transition-colors active:scale-[.98] lg:min-h-9 lg:min-w-0 lg:w-auto {{ $ctaClass }}">
                            <x-lucide name="code" class="w-3.5 h-3.5" />
                            <span>{{ $ctaLabel }}</span>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Phân trang bài tập --}}
        <nav aria-label="Phân trang bài tập chuyên đề" x-show="problemTotalPages > 1" x-cloak
             class="mt-3 flex flex-col items-center justify-between gap-2 rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] p-2 sm:flex-row">
            <span class="text-[11px] text-[#71869A]">Trang <b class="text-[#536D86]" x-text="problemPage"></b> / <span x-text="problemTotalPages"></span></span>
            <div class="flex items-center gap-1.5">
                <button type="button" aria-label="Trang trước" :disabled="problemPage === 1" @click="problemPageIndex = Math.max(1, problemPage - 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#536D86] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-left" class="h-4 w-4" />
                </button>
                <template x-for="n in problemTotalPages" :key="'pp' + n">
                    <button type="button" :aria-label="'Trang ' + n" :aria-current="problemPage === n ? 'page' : null" @click="problemPageIndex = n"
                            class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                            :class="problemPage === n ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#536D86] hover:bg-white'"
                            x-text="n"></button>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="problemPage === problemTotalPages" @click="problemPageIndex = Math.min(problemTotalPages, problemPage + 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#536D86] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </nav>

        <div x-show="filteredProblems.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
            <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
            <h2 class="mt-3 text-sm font-black text-[#123B68]">Không có bài tập phù hợp</h2>
            <p class="mt-1 text-xs text-[#71869A]">Hãy đổi chuyên đề hoặc mức độ để xem kho bài khác.</p>
            <button type="button" @click="resetProblemFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
        </div>
    </div>

    {{-- ══════════════ CHẾ ĐỘ: ĐỀ THI LUYỆN TẬP ══════════════ --}}
    <div x-show="practiceMode === 'exams'" x-cloak class="flex flex-col gap-4">

        {{-- [PRACTICE-05] BỘ LỌC ĐỀ THI --}}
        <div class="flex flex-col gap-3 rounded-2xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_10px_rgba(28,91,121,0.04)] md:flex-row md:items-center md:justify-between">
            <div class="relative min-w-0 flex-1">
                <x-lucide name="search" class="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-[#71869A]" />
                <input type="text" aria-label="Tìm kiếm đề thi" placeholder="Tìm đề thi theo tên..." x-model="searchQuery"
                       class="min-h-10 w-full rounded-xl border border-[#DDEAF0] bg-[#F8FAFB] py-2 pl-10 pr-4 text-xs text-slate-800 placeholder:text-[#8A9BAD] focus:border-[#9DC8D7] focus:bg-white focus:outline-none focus:ring-4 focus:ring-[#EAF5F8]">
            </div>
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
                @foreach ([['all', 'Tất cả đề'], ['coding', 'Có bài lập trình'], ['quiz', 'Trắc nghiệm & điền đáp án']] as [$key, $label])
                    <button type="button" @click="setExamType(@js($key))" :aria-pressed="selectedExamType === @js($key)"
                            class="min-h-9 whitespace-nowrap rounded-lg border px-2.5 py-1 text-[11px] font-bold transition"
                            :class="selectedExamType === @js($key) ? 'border-[#9DC8D7] bg-[#EAF5F8] text-[#126F91]' : 'border-[#DDEAF0] bg-[#F8FAFB] text-[#536D86] hover:border-[#C9DFE8] hover:bg-white'">{{ $label }}</button>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 px-1">
            <div class="flex items-center gap-2 text-[11px] text-[#71869A]">
                <x-lucide name="file-text" class="h-3.5 w-3.5 text-[#4C83B0]" />
                <span>Hiển thị <b class="text-[#536D86]" x-text="filteredExams.length"></b> đề thi luyện tập</span>
            </div>
            <span class="hidden text-[11px] text-[#8A9BAD] sm:inline">Mỗi đề mô phỏng một lượt thi hoàn chỉnh</span>
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
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#71869A]">Kho đề thi</p>
                        <p class="text-lg font-bold leading-5 text-[#123B68]">{{ $examTotal }}</p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-[#536D86]">Đề luyện tập đã phát hành</p>
            </div>
            <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#FFF7E3] text-[#B68032]"><x-lucide name="code-2" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#71869A]">Có bài lập trình</p>
                        <p class="text-lg font-bold leading-5 text-[#123B68]">{{ $examCoding }}</p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-[#536D86]">Chấm bằng bộ test tự động</p>
            </div>
            <div class="rounded-xl border border-[#DDEAF0] bg-white p-3 shadow-[0_2px_8px_rgba(28,91,121,0.04)]">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-[#EFF9F5] text-[#2F8A6B]"><x-lucide name="award" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-[#71869A]">Tổng số câu</p>
                        <p class="text-lg font-bold leading-5 text-[#123B68]">{{ number_format($examQuestions) }}</p>
                    </div>
                </div>
                <p class="mt-2 text-[11px] text-[#536D86]">Trong toàn bộ kho đề luyện tập</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 px-1 pt-1">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[.08em] text-[#71869A]">Danh sách đề thi</p>
                <p class="mt-0.5 text-sm font-bold text-[#123B68]">Chọn một phiên thi để bắt đầu</p>
            </div>
            <span class="hidden text-[11px] text-[#8A9BAD] sm:inline"><span x-text="filteredExams.length"></span> đề phù hợp</span>
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
                         :style="'order:' + visibleExamIds.indexOf({{ $exam['id'] }})"
                         class="group flex min-h-full flex-col overflow-hidden rounded-2xl border bg-white shadow-[0_2px_12px_rgba(28,91,121,0.06)] transition hover:-translate-y-0.5 hover:shadow-[0_8px_18px_rgba(28,91,121,0.09)] {{ $borderClass }}">
                    <div class="relative flex h-36 items-center justify-center overflow-hidden bg-[#F8FBFC] p-2.5">
                        <img src="{{ asset('assets/book-img-'.(($i % 4) + 1).'.png') }}" alt="{{ $exam['title'] }}"
                             class="h-full max-w-full object-contain transition-transform duration-300 group-hover:scale-[1.03]">
                        <span class="absolute left-3 top-3 rounded-lg border px-2 py-1 text-[11px] font-bold {{ $badgeClass }}">{{ $badgeLabel }}</span>
                        <span class="absolute bottom-2 left-3 rounded-md bg-white/90 px-2 py-1 font-mono text-[10px] font-bold text-[#536D86]">#{{ $exam['id'] }}</span>
                    </div>
                    <div class="flex flex-1 flex-col p-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 text-[11px] font-bold text-[#4C83B0]">
                                <x-lucide name="file-text" class="h-3.5 w-3.5" />Đề luyện tập
                            </div>
                            <span class="text-[10px] font-bold text-[#8A9BAD]">Thi mô phỏng</span>
                        </div>
                        <h3 class="mt-1 line-clamp-2 text-sm font-bold leading-5 text-[#123B68]">{{ $exam['title'] }}</h3>
                        <p class="type-body mt-1 line-clamp-2 text-[11px]">
                            {{ $exam['itemsCount'] }} câu · {{ $exam['totalPoints'] ?: '—' }} điểm{{ $exam['durationMinutes'] ? ' · '.$exam['durationMinutes'].' phút' : '' }}
                        </p>

                        <div class="mt-2.5 grid grid-cols-3 gap-1 rounded-xl border border-[#E7EFF3] bg-[#F8FBFC] p-1.5 text-center">
                            <div>
                                <x-lucide name="timer" class="mx-auto h-3.5 w-3.5 text-[#2D7FA3]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#536D86]">{{ $exam['durationMinutes'] ? $exam['durationMinutes'].' phút' : 'Không giới hạn' }}</p>
                                <p class="text-[9px] text-[#8A9BAD]">Thời lượng</p>
                            </div>
                            <div>
                                <x-lucide name="code-2" class="mx-auto h-3.5 w-3.5 text-[#786BB1]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#536D86]">{{ $exam['itemsCount'] }} bài</p>
                                <p class="text-[9px] text-[#8A9BAD]">Cấu trúc đề</p>
                            </div>
                            <div>
                                <x-lucide name="trending-up" class="mx-auto h-3.5 w-3.5 text-[#3B9374]" />
                                <p class="mt-0.5 text-[11px] font-bold text-[#536D86]">{{ $exam['totalPoints'] ?: '—' }}</p>
                                <p class="text-[9px] text-[#8A9BAD]">Tổng điểm</p>
                            </div>
                        </div>

                        <div class="mt-2.5 flex items-center justify-between gap-2 border-t border-[#E7EFF3] pt-2.5 mt-auto">
                            <div>
                                <p class="text-[9px] text-[#8A9BAD]">Cách chấm</p>
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
            <span class="text-[11px] text-[#71869A]">Trang <b class="text-[#536D86]" x-text="examPage"></b> / <span x-text="examTotalPages"></span></span>
            <div class="flex items-center gap-1.5">
                <button type="button" aria-label="Trang trước" :disabled="examPage === 1" @click="examPageIndex = Math.max(1, examPage - 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#536D86] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-left" class="h-4 w-4" />
                </button>
                <template x-for="n in examTotalPages" :key="'ep' + n">
                    <button type="button" :aria-label="'Trang ' + n" :aria-current="examPage === n ? 'page' : null" @click="examPageIndex = n"
                            class="grid h-9 min-w-9 place-items-center rounded-lg px-2 text-[11px] font-extrabold transition"
                            :class="examPage === n ? 'bg-[#126F91] text-white shadow-[0_3px_8px_rgba(18,111,145,0.16)]' : 'text-[#536D86] hover:bg-white'"
                            x-text="n"></button>
                </template>
                <button type="button" aria-label="Trang sau" :disabled="examPage === examTotalPages" @click="examPageIndex = Math.min(examTotalPages, examPage + 1)"
                        class="grid h-9 w-9 place-items-center rounded-lg border border-[#DDEAF0] bg-white text-[#536D86] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                    <x-lucide name="chevron-right" class="h-4 w-4" />
                </button>
            </div>
        </nav>

        <div x-show="filteredExams.length === 0" x-cloak class="rounded-3xl border border-dashed border-[#C9DFE8] bg-white p-10 text-center">
            <x-lucide name="search" class="mx-auto h-9 w-9 text-[#9DC8D7]" />
            <h2 class="mt-3 text-sm font-black text-[#123B68]">Không có đề thi phù hợp</h2>
            <p class="mt-1 text-xs text-[#71869A]">Thử đổi loại đề hoặc từ khóa tìm kiếm.</p>
            <button type="button" @click="resetExamFilters()" class="mt-4 text-[11px] font-bold text-[#126F91] hover:underline">Xóa bộ lọc</button>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
    @include('partials.practice-page-script')
@endpush
