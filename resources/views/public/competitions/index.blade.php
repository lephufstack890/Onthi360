@extends('layouts.guest')

@section('title', 'Cuộc thi & Đấu trường 360')
@section('meta-description', 'Cuộc thi lập trình và khảo sát năng lực trên Ôn Thi 360 — lịch từng vòng thi, điểm cao nhất, Top 5 và cách vào phòng thi.')

@section('content')
{{-- ═══════════════ [CONTEST] MÀN CUỘC THI ═══════════════
     SỬA 12/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/ContestsPage.jsx.
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\CompetitionService::indexData):
       · thẻ cuộc thi   <- bảng competitions (trạng thái tự tính theo giờ thật)
       · dải vòng thi   <- bảng competition_exams (mỗi vòng có giờ riêng)
       · điểm cao nhất  <- MAX(leaderboard_entries.score) của đúng vòng đó
       · Top 5          <- leaderboard_entries scope=competition (đã xếp hạng)
       · "Đã tham gia"  <- attempts đã nộp của chính người đang xem
       · đếm ngược      <- starts_at/ends_at thật, Alpine chạy đồng hồ tại chỗ

     KHÁC bản mẫu ở 3 chỗ, đều là chủ ý và đã báo khách:
       · Bản mẫu có luồng "gửi đăng ký → BTC duyệt → vào phòng thi". Hệ thống KHÔNG có bảng
         đăng ký/duyệt nào; vào thi là vào thẳng đề tham chiếu khi đang trong khung giờ của
         vòng. Dải 3 bước giữ nguyên hình dáng nhưng đổi thành 3 bước CÓ THẬT:
         Đăng nhập → Vào phòng thi → Xem kết quả.
       · Ô "Giải thưởng" không có cột tương ứng; thay bằng mốc công bố kết quả — thông tin
         thật mà người thi quan tâm đúng ở vị trí đó.
       · Tên trong Top 5 ẩn danh giống bảng xếp hạng công khai (bảo vệ dữ liệu học sinh);
         chỉ dòng của chính người đang đăng nhập mới hiện tên thật. --}}
@php
    $competitions = $competitions ?? [];
    $heroPanel = $heroPanel ?? ['eyebrow' => 'Lịch thi', 'deadline' => null, 'note' => 'Chưa có sự kiện nào đang mở'];

    // Bộ lọc của bản mẫu — giữ nguyên 5 mục, đều lọc được bằng dữ liệu thật.
    $contestFilters = [
        ['id' => 'all', 'label' => 'Tất cả sự kiện'],
        ['id' => 'ongoing', 'label' => 'Đang diễn ra 🔥'],
        ['id' => 'upcoming', 'label' => 'Sắp diễn ra'],
        ['id' => 'participated', 'label' => 'Đã tham gia'],
        ['id' => 'surveys', 'label' => 'Khảo sát năng lực'],
    ];

    // Dữ liệu đưa sang Alpine để lọc/phân trang/mở hộp chi tiết ngay tại chỗ (không tải lại trang).
    $contestRows = [];
    foreach ($competitions as $c) {
        $contestRows[] = [
            'id' => $c['id'],
            'type' => $c['type'],
            'statusValue' => $c['statusValue'],
            'participated' => $c['participated'],
        ];
    }
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiContestsPage({{ Js::from(['rows' => $contestRows, 'deadline' => $heroPanel['deadline']]) }})"
     class="flex flex-col gap-4 animate-fadeIn">

    {{-- ══════ 1. HERO ══════ --}}
    <div class="relative flex flex-col items-center justify-between gap-4 overflow-hidden rounded-3xl border border-sky-200/90 bg-gradient-to-r from-[#0050A0] via-[#0066CC] to-[#0284C7] p-5 text-white shadow-[0_10px_35px_rgba(0,100,220,0.08)] md:flex-row sm:p-6">
        <img src="{{ asset('assets/hero-contests.jpg') }}" alt="" loading="eager" decoding="async"
             class="pointer-events-none absolute inset-0 h-full w-full object-cover object-right opacity-40 mix-blend-overlay">

        <div class="relative z-10 max-w-2xl">
            <div class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-bold text-amber-950 shadow-sm">
                <x-lucide name="trophy" class="h-3.5 w-3.5" />Đấu trường Đỉnh cao
            </div>
            <h1 class="text-xl font-black leading-tight tracking-tight text-white sm:text-2xl">Cuộc thi Lập trình & Đấu trường 360</h1>
            <p class="mt-1.5 text-xs leading-relaxed text-sky-100">
                Theo dõi các vòng thi, thành tích và vào phòng thi khi vòng đang mở. Mỗi vòng có khung giờ riêng — hết giờ là khoá, kết quả công bố theo lịch của cuộc thi.
            </p>
        </div>

        {{-- Đồng hồ đếm ngược THẬT: tới giờ đóng của cuộc thi đang mở, hoặc tới giờ mở của cuộc thi gần nhất. --}}
        <div class="relative z-10 w-full rounded-2xl border border-white/20 bg-white/10 p-3 text-center shadow-xl backdrop-blur-md md:w-64">
            <p class="text-[10px] font-bold uppercase tracking-wider text-sky-200">{{ $heroPanel['eyebrow'] }}</p>
            <p class="mt-1 font-mono text-xl font-black text-white" x-text="countdownLabel">—</p>
            <div class="mt-1 flex items-center justify-center gap-1 text-[10px] text-sky-100">
                <x-lucide name="sparkles" class="h-3 w-3 shrink-0 text-amber-300" />
                <span class="truncate">{{ $heroPanel['note'] }}</span>
            </div>
        </div>
    </div>

    {{-- ══════ 2. BỘ LỌC ══════ --}}
    <div class="flex items-center justify-between rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_2px_10px_rgba(0,100,220,0.04)]">
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar">
            @foreach ($contestFilters as $f)
                <button type="button" :aria-pressed="activeTab === '{{ $f['id'] }}'" @click="setTab('{{ $f['id'] }}')"
                        class="min-h-10 whitespace-nowrap rounded-lg px-3.5 py-1.5 text-[11px] font-bold transition-all"
                        :class="activeTab === '{{ $f['id'] }}' ? 'bg-[#0066CC] text-white shadow-2xs' : 'text-slate-600 hover:bg-sky-50'">
                    {{ $f['label'] }}
                </button>
            @endforeach
        </div>
        <span class="hidden shrink-0 text-[10px] font-bold text-slate-400 sm:block"><span x-text="matchedIds.length">{{ count($competitions) }}</span> sự kiện</span>
    </div>

    {{-- ══════ 3. LƯỚI THẺ CUỘC THI ══════ --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($competitions as $c)
            <article x-show="isVisible({{ $c['id'] }})" x-cloak
                     class="group flex flex-col justify-between overflow-hidden rounded-2xl border transition-all duration-300 hover:border-sky-200 hover:shadow-lg {{ $c['cardStyle'] }}">
                <div>
                    <div class="relative h-32 overflow-hidden bg-slate-100">
                        <img src="{{ $c['image'] }}" alt="{{ $c['title'] }}" loading="lazy" decoding="async"
                             class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                        <span class="absolute left-3 top-3 rounded-full border border-sky-200 bg-white/95 px-2.5 py-0.5 text-[10px] font-bold text-[#0050A0] shadow-2xs backdrop-blur-xs">{{ $c['tag'] }}</span>
                        <span class="absolute right-3 top-3 rounded-full border px-2.5 py-0.5 text-[10px] font-bold shadow-2xs backdrop-blur-xs {{ $c['statusStyle'] }}">{{ $c['statusLabel'] }}</span>
                    </div>

                    <div class="p-4">
                        <h3 class="mb-2 line-clamp-2 text-sm font-bold leading-5 text-[#0B3C78] transition-colors group-hover:text-blue-600">{{ $c['title'] }}</h3>

                        <div class="mb-3 flex flex-wrap items-center gap-1.5 text-[11px] font-bold text-slate-500">
                            <span class="rounded-lg border border-sky-100 bg-sky-50 px-2 py-1">{{ $c['editionLabel'] }}</span>
                            <span class="rounded-lg border border-slate-100 bg-slate-50 px-2 py-1">{{ $c['roundLabel'] }}</span>
                        </div>

                        {{-- Dải vòng thi + 4 chỉ số, tất cả từ competition_exams / assessments thật --}}
                        <div class="mb-3 rounded-xl border border-sky-100 bg-[#F8FBFE] p-2.5">
                            @include('partials.contest-round-timeline', ['rounds' => $c['rounds'], 'compact' => true])

                            <div class="mt-2 grid grid-cols-2 gap-2 border-t border-sky-100 pt-2 text-[10px] text-slate-500">
                                <span class="inline-flex items-center gap-1.5">
                                    <x-lucide name="calendar" class="h-3.5 w-3.5 shrink-0 text-blue-500" />{{ $c['currentRound']['date'] ?? 'Đã kết thúc' }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-lucide name="users" class="h-3.5 w-3.5 shrink-0 text-purple-500" />{{ number_format($c['participants'], 0, ',', '.') }} người
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-lucide name="clock-3" class="h-3.5 w-3.5 shrink-0 text-amber-500" />{{ $c['duration'] }}
                                </span>
                                <span class="inline-flex items-center gap-1.5">
                                    <x-lucide name="bar-chart-3" class="h-3.5 w-3.5 shrink-0 text-emerald-500" />{{ $c['completedRounds'] }}/{{ count($c['rounds']) }} vòng qua
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 p-2 text-[11px] font-bold text-amber-700">
                            <x-lucide name="award" class="h-4 w-4 shrink-0 text-amber-500" />
                            <span class="truncate">{{ $c['awardLabel'] }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 border-t border-sky-100 p-4 pt-3">
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-bold {{ $c['participationStyle'] }}">
                        <x-lucide name="user-check" class="h-3.5 w-3.5 shrink-0" />{{ $c['participationLabel'] }}
                    </span>
                    <button type="button" @click="openDetail({{ $c['id'] }})"
                            class="inline-flex min-h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-blue-600 to-sky-500 px-3 py-2 text-[11px] font-bold text-white shadow-md transition-all hover:brightness-105">
                        Chi tiết cuộc thi <x-lucide name="chevron-right" class="h-3.5 w-3.5" />
                    </button>
                </div>
            </article>
        @endforeach
    </div>

    {{-- ══════ 4. PHÂN TRANG ══════ --}}
    <nav x-show="totalPages > 1" x-cloak aria-label="Phân trang danh sách cuộc thi"
         class="mt-1 flex flex-col items-center justify-between gap-2 rounded-2xl border border-sky-100 bg-white p-2.5 shadow-[0_2px_10px_rgba(0,100,220,0.04)] sm:flex-row">
        <span class="text-[11px] text-slate-400">Trang <strong class="text-slate-600" x-text="page">1</strong> / <span x-text="totalPages">1</span></span>
        <div class="flex items-center gap-1.5">
            <button type="button" aria-label="Trang trước" :disabled="page === 1" @click="page = page - 1"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-sky-100 text-sky-700 transition-colors hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-35">
                <x-lucide name="chevron-left" class="h-4 w-4" />
            </button>
            <template x-for="pageNumber in totalPages" :key="pageNumber">
                <button type="button" :aria-label="'Trang ' + pageNumber" :aria-current="pageNumber === page ? 'page' : null"
                        @click="page = pageNumber"
                        class="inline-flex h-9 min-w-9 items-center justify-center rounded-xl px-2 text-[11px] font-bold transition-colors"
                        :class="pageNumber === page ? 'bg-[#0066CC] text-white shadow-sm' : 'text-slate-600 hover:bg-sky-50'"
                        x-text="pageNumber"></button>
            </template>
            <button type="button" aria-label="Trang sau" :disabled="page === totalPages" @click="page = page + 1"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-sky-100 text-sky-700 transition-colors hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-35">
                <x-lucide name="chevron-right" class="h-4 w-4" />
            </button>
        </div>
    </nav>

    {{-- ══════ 5. TRẠNG THÁI RỖNG ══════ --}}
    <div x-show="matchedIds.length === 0" x-cloak class="rounded-3xl border border-dashed border-sky-200 bg-white p-10 text-center">
        <x-lucide name="trophy" class="mx-auto h-9 w-9 text-sky-300" />
        <h2 class="mt-3 text-sm font-black text-slate-800">Chưa có sự kiện ở trạng thái này</h2>
        <p class="mt-1 text-xs text-slate-500">Hãy quay lại sau hoặc xem tất cả sự kiện đang công bố.</p>
        <button type="button" @click="setTab('all')" class="mt-4 text-xs font-bold text-blue-600">Xem tất cả</button>
    </div>

    {{-- ══════ 6. HỘP CHI TIẾT CUỘC THI ══════
         Bản mẫu mở modal; dữ liệu của modal đã được nạp sẵn ở trên nên bấm mở là hiện ngay,
         không gọi thêm truy vấn. Nút trong hộp dẫn sang trang thể lệ / phòng thi / bảng xếp
         hạng thật. --}}
    {{-- SỬA 12/9 (2) — hộp thoại được ĐƯA THẲNG RA <body> bằng x-teleport. Nếu để nguyên tại
         chỗ, nó nằm trong thẻ gốc của trang; bất kỳ hiệu ứng/bộ lọc nào trên các thẻ cha (kể
         cả animation) đều tạo "stacking context" mới, khiến hộp fixed inset-0 bị co lại theo
         thẻ cha và thanh header vẽ đè lên. Teleport ra body thì hộp luôn phủ đúng màn hình,
         không phụ thuộc bố cục trang. --}}
    @foreach ($competitions as $c)
        <template x-teleport="body">
        <div x-show="openId === {{ $c['id'] }}" x-cloak
             class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-2 sm:p-4"
             @mousedown.self="openId = null" @keydown.escape.window="openId = null">
            <section role="dialog" aria-modal="true" aria-labelledby="contest-detail-title-{{ $c['id'] }}"
                     class="max-h-[calc(100vh-16px)] w-full max-w-4xl overflow-y-auto rounded-3xl bg-white shadow-2xl">

                <header class="relative overflow-hidden bg-gradient-to-r from-[#064C99] via-[#0066CC] to-[#0891B2] px-4 py-3.5 text-white sm:px-5">
                    <img src="{{ $c['image'] }}" alt="" decoding="async" class="absolute inset-0 h-full w-full object-cover opacity-20">
                    <div class="relative">
                        <button type="button" aria-label="Đóng chi tiết cuộc thi" @click="openId = null"
                                class="absolute right-0 top-0 rounded-xl bg-white/15 p-1.5 text-white transition hover:bg-white/25">
                            <x-lucide name="x" class="h-4 w-4" />
                        </button>
                        <div class="flex flex-wrap items-center gap-1.5 pr-8">
                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-[9px] font-bold">{{ $c['editionLabel'] }}</span>
                            <span class="rounded-full bg-amber-300 px-2 py-0.5 text-[9px] font-black text-amber-950">{{ $c['roundLabel'] }}</span>
                            <span class="rounded-full border px-2 py-0.5 text-[9px] font-bold {{ $c['statusStyle'] }}">{{ $c['statusLabel'] }}</span>
                        </div>
                        <h2 id="contest-detail-title-{{ $c['id'] }}" class="mt-2 max-w-3xl text-lg font-black leading-tight text-white sm:text-xl">{{ $c['title'] }}</h2>
                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[10px] font-medium text-sky-100">
                            <span class="inline-flex items-center gap-1"><x-lucide name="calendar" class="h-3 w-3 shrink-0" />Vòng này: {{ $c['currentRound']['date'] ?? 'Chưa xếp lịch' }}</span>
                            <span class="inline-flex items-center gap-1"><x-lucide name="clock-3" class="h-3 w-3 shrink-0" />{{ $c['duration'] }}</span>
                            <span class="inline-flex items-center gap-1"><x-lucide name="users" class="h-3 w-3 shrink-0" />Tổ chức: {{ $c['organizerLabel'] }}</span>
                        </div>
                    </div>
                </header>

                <div class="space-y-2 p-3 sm:p-4">
                    {{-- Dải các vòng --}}
                    <section class="rounded-xl border border-sky-100 bg-sky-50/50 px-3 py-2">
                        <div class="flex items-center justify-between text-[9px] font-bold text-slate-500">
                            <span>Các vòng thi</span>
                            <span>{{ $c['completedRounds'] }}/{{ count($c['rounds']) }} vòng đã qua</span>
                        </div>
                        <div class="mt-1">
                            @include('partials.contest-round-timeline', ['rounds' => $c['rounds'], 'compact' => true])
                        </div>
                    </section>

                    <div class="grid grid-cols-1 items-start gap-2 sm:grid-cols-2">
                        {{-- Biểu đồ điểm cao nhất từng vòng (MAX(score) thật của bảng xếp hạng vòng đó) --}}
                        <section class="rounded-xl border border-sky-100 bg-white p-3">
                            <div class="flex items-center gap-2">
                                <x-lucide name="bar-chart-3" class="h-4 w-4 shrink-0 text-amber-600" />
                                <h3 class="text-xs font-black text-[#123B68]">Điểm cao nhất</h3>
                            </div>
                            @php $scoredRounds = array_values(array_filter($c['rounds'], fn ($r) => $r['maxScore'] !== null)); @endphp
                            <div class="mt-2 rounded-xl border border-amber-100 bg-[#FFFCF4] p-2.5">
                                <div class="flex h-28 items-end gap-3 border-b border-l border-amber-200 px-3 pt-2 sm:gap-5">
                                    @forelse ($scoredRounds as $r)
                                        <div class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
                                            <span class="text-[11px] font-black text-amber-700">{{ rtrim(rtrim(number_format($r['maxScore'], 2, ',', ''), '0'), ',') }}đ</span>
                                            <div class="w-full max-w-10 rounded-t-lg bg-amber-400" style="height: {{ max(18, min(100, (int) round($r['maxScore'] / max(1, (float) ($r['totalPoints'] ?: 100)) * 100))) }}%"></div>
                                            <span class="w-16 truncate text-center text-[10px] font-bold text-slate-600">{{ $r['shortLabel'] }}</span>
                                        </div>
                                    @empty
                                        <p class="w-full pb-5 text-center text-[11px] text-slate-400">Chưa có điểm</p>
                                    @endforelse
                                </div>
                                <div class="mt-1 flex justify-between text-[10px] text-slate-500">
                                    <span>Điểm cao nhất</span>
                                    <span>Thang {{ (int) ($c['currentRound']['totalPoints'] ?: 100) }}</span>
                                </div>
                            </div>
                        </section>

                        {{-- Top 5 (ẩn danh) --}}
                        <section class="rounded-xl border border-sky-100 bg-white p-3">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-black text-[#123B68]">Top 5 bảng tổng</h3>
                                <x-lucide name="award" class="h-4 w-4 shrink-0 text-amber-500" />
                            </div>
                            <div class="mt-2 overflow-hidden rounded-xl border border-sky-100">
                                <div class="grid grid-cols-[22px_30px_1fr_48px] gap-2 bg-[#F3F7FA] px-2.5 py-2 text-[10px] font-black uppercase tracking-wide text-slate-500">
                                    <span>#</span><span></span><span>Học viên dẫn đầu</span><span class="text-right">Điểm</span>
                                </div>
                                <div class="divide-y divide-sky-50 bg-white">
                                    @forelse ($c['topFive'] as $i => $t)
                                        <div class="grid grid-cols-[22px_30px_1fr_48px] items-center gap-2 px-2.5 py-2">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-black {{ $i === 0 ? 'bg-[#FFF1CF] text-[#9A741E]' : 'bg-slate-100 text-slate-500' }}">{{ $t['rank'] }}</span>
                                            <img src="{{ $t['avatar'] }}" alt="" decoding="async" class="h-7 w-7 rounded-full border border-white object-cover shadow-sm">
                                            <div class="min-w-0">
                                                <p class="truncate text-[11px] font-bold {{ $t['isViewer'] ? 'text-blue-700' : 'text-slate-700' }}">{{ $t['name'] }}@if ($t['isViewer']) <span class="font-black">· Bạn</span>@endif</p>
                                                <p class="truncate text-[10px] text-slate-400">Chấm ngày {{ $t['note'] }}</p>
                                            </div>
                                            <span class="text-right text-[11px] font-black text-[#9A741E]">{{ rtrim(rtrim(number_format($t['score'], 2, ',', ''), '0'), ',') }}đ</span>
                                        </div>
                                    @empty
                                        <p class="px-2.5 py-6 text-center text-[11px] text-slate-400">Chưa có kết quả nào được chấm.</p>
                                    @endforelse
                                </div>
                            </div>
                        </section>
                    </div>

                    {{-- Chi tiết vòng + 3 bước tham gia THẬT --}}
                    <section class="rounded-xl border border-sky-100 bg-[#F8FBFE] p-3">
                        <div class="flex items-center gap-2">
                            <x-lucide name="calendar" class="h-4 w-4 shrink-0 text-blue-600" />
                            <h3 class="text-xs font-black text-[#123B68]">Chi tiết vòng & cách tham gia</h3>
                        </div>

                        <div class="mt-2 grid gap-2 sm:grid-cols-[1.15fr_0.85fr]">
                            <div class="grid grid-cols-2 gap-1.5">
                                <div class="rounded-lg bg-white p-2">
                                    <p class="text-[9px] text-slate-400">Thời gian vòng này</p>
                                    <p class="mt-0.5 text-[10px] font-black text-slate-700">{{ $c['currentRound']['date'] ?? 'Chưa xếp lịch' }}</p>
                                </div>
                                <div class="rounded-lg bg-white p-2">
                                    <p class="text-[9px] text-slate-400">Đã dự thi vòng này</p>
                                    <p class="mt-0.5 text-[10px] font-black text-[#126F91]">{{ number_format($c['currentRound']['participants'] ?? 0, 0, ',', '.') }} người</p>
                                </div>
                                <div class="rounded-lg bg-white p-2">
                                    <p class="text-[9px] text-slate-400">Bài thi</p>
                                    <p class="mt-0.5 text-[10px] font-black text-slate-700">{{ $c['problemsCount'] }} câu · {{ $c['duration'] }}</p>
                                </div>
                                <div class="rounded-lg bg-white p-2">
                                    <p class="text-[9px] text-slate-400">Hạn dự thi</p>
                                    <p class="mt-0.5 text-[10px] font-black text-slate-700">{{ $c['deadlineLabel'] }}</p>
                                </div>
                                <div class="col-span-2 flex min-w-0 items-center gap-3 overflow-hidden rounded-lg bg-white px-2 py-1.5 text-[9px]">
                                    <span class="min-w-0 truncate"><b class="font-bold text-slate-400">Kết quả:</b> <span class="font-black text-amber-700">{{ $c['awardLabel'] }}</span></span>
                                    <span class="min-w-0 truncate text-slate-600"><b class="font-bold text-slate-400">Mở thi:</b> {{ $c['startsAtLabel'] }}</span>
                                </div>
                            </div>

                            {{-- 3 bước THẬT: Đăng nhập → Vào phòng thi → Xem kết quả --}}
                            @php
                                $stepLoggedIn = auth()->check();
                                $stepJoined = $c['participated'];
                                $stepResult = $c['statusValue'] === 'published';
                            @endphp
                            <div class="rounded-xl border border-sky-100 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <x-lucide name="user-check" class="h-3.5 w-3.5 shrink-0 text-[#3A7185]" />
                                        <h3 class="text-sm font-black text-[#123B68]">Tham gia thế nào</h3>
                                    </div>
                                    <span class="text-[10px] font-bold text-slate-500">Không cần đăng ký trước</span>
                                </div>

                                <div class="mt-2 grid grid-cols-3 gap-1.5 text-center text-[10px] font-bold">
                                    <div class="rounded-lg p-1.5 {{ $stepLoggedIn ? 'bg-[#E7F3EE] text-[#39755F]' : 'bg-slate-50 text-slate-500' }}">
                                        @if ($stepLoggedIn)
                                            <x-lucide name="check" class="mx-auto mb-0.5 h-3 w-3" />
                                        @else
                                            1<br>
                                        @endif
                                        Đăng nhập
                                    </div>
                                    <div class="rounded-lg p-1.5 {{ $stepJoined ? 'bg-[#E7F3EE] text-[#39755F]' : ($c['canJoinNow'] ? 'bg-[#FFF3D9] text-[#9A741E]' : 'bg-slate-50 text-slate-500') }}">
                                        @if ($stepJoined)
                                            <x-lucide name="check" class="mx-auto mb-0.5 h-3 w-3" />
                                        @else
                                            2<br>
                                        @endif
                                        Vào phòng thi
                                    </div>
                                    <div class="rounded-lg p-1.5 {{ $stepResult ? 'bg-[#EAF2F8] text-[#356782]' : 'bg-slate-50 text-slate-500' }}">
                                        3<br>Xem kết quả
                                    </div>
                                </div>

                                @if ($c['cta']['tone'] === 'go')
                                    <a href="{{ $c['cta']['href'] }}"
                                       class="mt-2 flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-[#43876F] px-3 py-2 text-[11px] font-black text-white transition hover:bg-[#3A7561]">
                                        <x-lucide :name="$c['cta']['icon']" class="h-3.5 w-3.5" />{{ $c['cta']['label'] }}
                                    </a>
                                @elseif ($c['cta']['tone'] === 'primary')
                                    <a href="{{ $c['cta']['href'] }}"
                                       class="mt-2 flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg bg-[#2F7890] px-3 py-2 text-[11px] font-black text-white shadow-sm transition hover:bg-[#286B80]">
                                        <x-lucide :name="$c['cta']['icon']" class="h-3.5 w-3.5" />{{ $c['cta']['label'] }}
                                    </a>
                                @else
                                    <a href="{{ $c['cta']['href'] }}"
                                       class="mt-2 flex min-h-9 w-full items-center justify-center gap-1.5 rounded-lg border border-sky-100 bg-white px-3 py-2 text-[11px] font-black text-[#2F7890] transition hover:bg-sky-50">
                                        <x-lucide :name="$c['cta']['icon']" class="h-3.5 w-3.5" />{{ $c['cta']['label'] }}
                                    </a>
                                @endif

                                @if (! $c['canJoinNow'] && $c['statusValue'] !== 'ongoing')
                                    <div class="mt-2 flex items-center gap-1.5 rounded-lg border border-violet-100 bg-violet-50 px-2.5 py-2 text-[10px] font-bold text-violet-700">
                                        <x-lucide name="info" class="h-3.5 w-3.5 shrink-0" />Ngoài khung giờ thi — chỉ xem thông tin và kết quả.
                                    </div>
                                @endif

                                <a href="{{ $c['href'] }}" class="mt-2 flex w-fit items-center gap-1 text-[10px] font-bold text-blue-600 hover:underline">
                                    Xem thể lệ đầy đủ <x-lucide name="chevron-right" class="h-3 w-3" />
                                </a>
                            </div>
                        </div>

                        {{-- Bảng từng vòng: giờ riêng + trạng thái + nút vào đúng vòng đang mở --}}
                        <div class="mt-2 overflow-hidden rounded-xl border border-sky-100 bg-white">
                            @foreach ($c['rounds'] as $r)
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-sky-50 px-3 py-2 last:border-b-0">
                                    <div class="min-w-0">
                                        <p class="truncate text-[11px] font-bold text-slate-700">{{ $r['label'] }}</p>
                                        <p class="mt-0.5 text-[10px] text-slate-400">
                                            {{ $r['date'] }}
                                            @if ($r['maxScore'] !== null)
                                                · cao nhất {{ rtrim(rtrim(number_format($r['maxScore'], 2, ',', ''), '0'), ',') }}đ
                                            @endif
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5">
                                        <span class="rounded-full border px-2 py-0.5 text-[9px] font-bold {{ $r['status'] === 'completed' ? 'border-slate-200 bg-slate-50 text-slate-600' : ($r['status'] === 'current' ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-amber-300 bg-amber-50 text-amber-700') }}">
                                            {{ $r['status'] === 'completed' ? 'Đã kết thúc' : ($r['status'] === 'current' ? 'Đang diễn ra' : 'Sắp diễn ra') }}
                                        </span>
                                        @if ($r['canJoin'])
                                            <a href="{{ route('student.assessment.take', $r['assessmentId']) }}"
                                               class="inline-flex items-center gap-1 rounded-lg bg-[#43876F] px-2.5 py-1 text-[10px] font-black text-white transition hover:bg-[#3A7561]">
                                                <x-lucide name="play" class="h-3 w-3" />Vào thi
                                            </a>
                                        @elseif ($r['alreadyAttempted'])
                                            <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 px-2.5 py-1 text-[10px] font-black text-emerald-700">
                                                <x-lucide name="check-circle-2" class="h-3 w-3" />Đã làm
                                            </span>
                                        @endif
                                        <a href="{{ route('leaderboard.index', ['competition' => $c['id'], 'exam' => $r['id']]) }}"
                                           class="inline-flex items-center gap-1 rounded-lg border border-sky-100 px-2.5 py-1 text-[10px] font-bold text-sky-700 transition hover:bg-sky-50">
                                            <x-lucide name="bar-chart-3" class="h-3 w-3" />Xếp hạng
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>
            </section>
        </div>
        </template>
    @endforeach
</div>
</div>
@endsection

@push('scripts')
    @include('partials.contests-page-script')
@endpush
