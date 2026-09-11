@extends('layouts.guest')

@section('title', 'Bảng xếp hạng & Đại sảnh Danh vọng')
@section('meta-description', 'Bảng xếp hạng Ôn Thi 360 — vinh danh học viên theo từng cuộc thi đã công bố, tính từ điểm thi và số câu làm đúng, cập nhật theo thời điểm chấm.')

@section('content')
{{-- ═══════════════ [LEADERBOARD] MÀN BẢNG XẾP HẠNG ═══════════════
     SỬA 11/9 — dựng lại theo ĐÚNG source giao diện khách gửi:
     education-main/src/components/LeaderboardPage.jsx.
     Bố cục/class chép nguyên; React state đổi sang Alpine; mọi nút gắn link thật.

     Dữ liệu lấy từ cơ sở dữ liệu (App\Services\Public\LeaderboardService::indexData):
       · dải phạm vi  <- $boards (cuộc thi ĐÃ CÔNG BỐ có bảng xếp hạng) + $examTabs (kỳ thi con)
       · bục vinh danh<- 3 hạng đầu của $entries
       · bảng xếp hạng<- $entries (hạng, điểm, số câu làm đúng, thời điểm chấm)
       · "Vị trí của bạn" <- $yourEntry / $totalEntries

     KHÁC bản mẫu ở 3 chỗ, đều là chủ ý chứ không phải thiếu sót:
       · Bản mẫu có 4 phạm vi cứng (Toàn thời gian / Tháng này / Cuộc thi gần nhất / Lớp của
         tôi). Hệ thống KHÔNG có bảng xếp hạng toàn thời gian hay theo tháng — leaderboard_entries
         luôn gắn với 1 cuộc thi/kỳ thi cụ thể — nên dải phạm vi dựng từ các cuộc thi có thật.
       · Cột "Trường" và "Chuỗi N ngày" không có nguồn dữ liệu; thay bằng "Kỳ thi" và thời
         điểm chấm — đều là cột thật của bảng.
       · Ô "Ẩn danh tên học sinh" của bản mẫu bật/tắt được. Ở đây ẩn danh là BẮT BUỘC (bảo vệ
         dữ liệu trẻ em — chưa có cột "đồng ý hiển thị công khai"), nên ô này khoá ở trạng thái
         bật; riêng dòng của chính người đang đăng nhập mới hiện tên thật. --}}
@php
    $boards = $boards ?? [];
    $entries = $entries ?? [];
    $examTabs = $examTabs ?? [];
    $selected = $selected ?? null;
    $selectedExamId = $selectedExamId ?? null;
    $totalEntries = $totalEntries ?? 0;
    $yourEntry = $yourEntry ?? null;
    $rankingRule = $rankingRule ?? [];
    $updatedAt = $updatedAt ?? null;
    $scopeLabel = $scopeLabel ?? null;

    // Ảnh đại diện ẩn danh — xoay vòng 5 ảnh của bản mẫu theo hạng, ổn định giữa các lần tải.
    $rankAvatars = ['rank-avatar-1.png', 'rank-avatar-2.png', 'rank-avatar-3.png', 'rank-avatar-4.png', 'rank-avatar-5.png'];
    $avatarFor = fn (int $rank) => asset('assets/'.$rankAvatars[max(0, $rank - 1) % count($rankAvatars)]);

    // 3 hạng đầu lên bục, phần còn lại xuống bảng — đúng cách bản mẫu chia.
    $podium = array_slice($entries, 0, 3);
    $rest = array_slice($entries, 3);

    // Huy hiệu theo hạng: dữ liệu thật duy nhất có ở đây là THỨ HẠNG, nên nhãn suy từ hạng
    // chứ không bịa ra cấp bậc mà hệ thống không có.
    $badgeFor = fn (int $rank) => $rank === 1 ? 'Quán quân' : ($rank === 2 ? 'Á quân' : 'Hạng ba');

    // Hàng dữ liệu đưa sang Alpine để tìm kiếm ngay tại chỗ.
    $rows = [];
    foreach ($rest as $e) {
        $rows[] = [
            'rank' => $e['rank'],
            'search' => mb_strtolower('#'.$e['rank'].' '.$e['name'].' '.(string) $scopeLabel),
        ];
    }
@endphp

<div class="max-w-[1780px] w-full mx-auto px-3 sm:px-5 lg:px-6 2xl:px-10 py-3 sm:py-5">
<div x-data="onthiLeaderboardPage({{ Js::from(['rows' => $rows]) }})" class="flex flex-col gap-4">

    {{-- ══════ 1. HERO ══════ --}}
    <section class="relative overflow-hidden rounded-2xl border border-sky-200/90 bg-gradient-to-r from-[#0B4F86] via-[#166A9B] to-[#2B93BA] p-5 text-white shadow-[0_8px_24px_rgba(0,100,220,0.08)] sm:p-6">
        <img src="{{ asset('assets/hero-leaderboard.jpg') }}" alt=""
             class="pointer-events-none absolute inset-0 h-full w-full object-cover object-right opacity-25 mix-blend-overlay">

        <div class="relative z-10 flex flex-col items-start justify-between gap-5 lg:flex-row lg:items-center">
            <div class="max-w-2xl">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-bold text-amber-950">
                    <x-lucide name="sparkles" class="h-3.5 w-3.5" />Vinh danh Top Coder toàn quốc
                </div>
                <h1 class="text-2xl font-bold leading-tight tracking-tight text-white">Bảng xếp hạng &amp; Đại sảnh Danh vọng</h1>
                <p class="mt-2 max-w-2xl text-xs leading-relaxed text-sky-100 sm:text-sm">
                    Xếp hạng được tính từ điểm bài thi và số câu làm đúng của mỗi cuộc thi đã công bố kết quả.
                    Mỗi nỗ lực đều có một vị trí xứng đáng.
                </p>
                <div class="mt-4 flex flex-wrap gap-2 text-[11px] font-bold text-sky-100">
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5">
                        <x-lucide name="users" class="h-3.5 w-3.5 text-sky-200" />{{ number_format($totalEntries) }} lượt xếp hạng
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/20 bg-white/10 px-2.5 py-1.5">
                        <x-lucide name="calendar-days" class="h-3.5 w-3.5 text-amber-300" />
                        {{ $updatedAt ? 'Chấm lúc '.\Illuminate\Support\Carbon::parse($updatedAt)->format('H:i d/m/Y') : 'Chưa có mốc chấm' }}
                    </span>
                </div>
            </div>

            {{-- "Vị trí của bạn" — số thật của người đang đăng nhập; khách thấy lời mời đăng nhập --}}
            <div class="w-full max-w-sm rounded-2xl border border-white/20 bg-white/10 p-3.5 shadow-xl backdrop-blur-md lg:w-72">
                @auth
                    @if ($yourEntry)
                        <div class="flex items-center gap-3">
                            <img src="{{ $avatarFor((int) $yourEntry['rank']) }}" alt="" class="h-12 w-12 shrink-0 rounded-full border-2 border-amber-300 object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-300">Vị trí của bạn</p>
                                <p class="mt-0.5 text-lg font-black text-white">#{{ $yourEntry['rank'] }} <span class="text-xs font-medium text-sky-200">/ {{ number_format($totalEntries) }}</span></p>
                                <p class="truncate text-[11px] text-sky-100">{{ auth()->user()->name }} · {{ rtrim(rtrim(number_format($yourEntry['score'], 2, '.', ''), '0'), '.') }} điểm</p>
                            </div>
                            <x-lucide name="award" class="h-6 w-6 text-amber-300" />
                        </div>
                    @else
                        <div class="flex items-center gap-3">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full border-2 border-white/30 bg-white/10 text-sky-100">
                                <x-lucide name="target" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-300">Vị trí của bạn</p>
                                <p class="mt-0.5 text-sm font-bold text-white">Chưa có trong bảng này</p>
                                <a href="{{ route('competitions.index') }}" class="text-[11px] text-sky-100 underline underline-offset-2 hover:text-white">Tham gia một cuộc thi →</a>
                            </div>
                        </div>
                    @endif
                @else
                    <div class="flex items-center gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full border-2 border-white/30 bg-white/10 text-sky-100">
                            <x-lucide name="lock-keyhole" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-amber-300">Vị trí của bạn</p>
                            <p class="mt-0.5 text-sm font-bold text-white">Đăng nhập để xem</p>
                            <a href="{{ route('login') }}" class="text-[11px] text-sky-100 underline underline-offset-2 hover:text-white">Đăng nhập ngay →</a>
                        </div>
                    </div>
                @endauth
            </div>
        </div>
    </section>

    {{-- ══════ 2. PHẠM VI & TÌM KIẾM ══════ --}}
    <section class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_2px_10px_rgba(0,100,220,0.04)]">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-1.5">
                @forelse ($boards as $b)
                    @php $isActive = $selected && $selected->id === $b['id']; @endphp
                    <a href="{{ route('leaderboard.index', ['competition' => $b['id']]) }}"
                       aria-current="{{ $isActive ? 'page' : 'false' }}"
                       class="inline-flex min-h-10 items-center rounded-lg px-3.5 py-1.5 text-[11px] font-bold transition-colors {{ $isActive ? 'bg-[#0066CC] text-white shadow-sm' : 'text-slate-600 hover:bg-sky-50' }}">
                        {{ $b['title'] }}
                        <span class="ml-1.5 rounded-full px-1.5 text-[10px] {{ $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' }}">{{ $b['participants'] }}</span>
                    </a>
                @empty
                    <span class="text-[11px] text-slate-400">Chưa có cuộc thi nào công bố kết quả.</span>
                @endforelse
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <label class="relative block">
                    <x-lucide name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-slate-400" />
                    <input x-model="query" placeholder="Tìm theo hạng hoặc kỳ thi" aria-label="Tìm theo hạng hoặc kỳ thi"
                           class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 text-xs text-slate-700 outline-none placeholder:text-slate-400 focus:border-sky-300 focus:ring-2 focus:ring-sky-100 sm:w-56">
                </label>
                <label class="flex min-h-10 items-center gap-2 text-[11px] font-bold text-slate-600"
                       title="Bảng xếp hạng công khai luôn ẩn danh để bảo vệ dữ liệu học sinh — không thể tắt.">
                    <input type="checkbox" checked disabled class="h-4 w-4 accent-blue-600">
                    Ẩn danh tên học sinh
                    <x-lucide name="lock" class="h-3 w-3 text-slate-400" />
                </label>
            </div>
        </div>

        {{-- Dải kỳ thi con của cuộc thi đang xem — chỉ hiện khi cuộc thi có nhiều kỳ --}}
        @if (count($examTabs) > 0 && $selected)
            <div class="mt-3 flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3">
                <span class="text-[11px] font-bold text-slate-500">Kỳ thi:</span>
                <a href="{{ route('leaderboard.index', ['competition' => $selected->id]) }}"
                   class="inline-flex min-h-9 items-center rounded-lg border px-2.5 py-1 text-[11px] font-bold transition-colors {{ $selectedExamId === null ? 'border-[#9DC8D7] bg-[#EAF5F8] text-[#126F91]' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-sky-50' }}">
                    Tổng hợp
                </a>
                @foreach ($examTabs as $tab)
                    <a href="{{ route('leaderboard.index', ['competition' => $selected->id, 'exam' => $tab['id']]) }}"
                       class="inline-flex min-h-9 items-center rounded-lg border px-2.5 py-1 text-[11px] font-bold transition-colors {{ $selectedExamId === $tab['id'] ? 'border-[#9DC8D7] bg-[#EAF5F8] text-[#126F91]' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-sky-50' }}">
                        {{ $tab['title'] }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-1 border-t border-slate-100 pt-3 text-[11px] text-slate-500">
            <span class="inline-flex items-center gap-1.5">
                <x-lucide name="bar-chart" class="h-3.5 w-3.5 text-sky-600" />Đang xem: <strong class="text-slate-700">{{ $scopeLabel ?: 'Chưa chọn' }}</strong>
            </span>
            <span class="inline-flex items-center gap-1.5">
                <x-lucide name="check-circle" class="h-3.5 w-3.5 text-emerald-600" />
                @if (!empty($rankingRule['description']))
                    {{ $rankingRule['description'] }}
                @else
                    Điểm tính theo thể lệ đã công bố của cuộc thi
                @endif
            </span>
        </div>
    </section>

    {{-- ══════ 3. HALL OF FAME — BA VỊ TRÍ DẪN ĐẦU ══════ --}}
    @if (count($podium) > 0)
        <section class="overflow-hidden rounded-2xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-amber-50 p-4 shadow-[0_8px_24px_rgba(0,100,220,0.06)] sm:p-5">
            <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.12em] text-sky-700">Hall of Fame</p>
                    <h2 class="mt-1 text-lg font-bold text-[#0B3C78]">Ba vị trí dẫn đầu</h2>
                </div>
                <p class="text-[11px] text-slate-500">Tôn vinh thành tích nổi bật trong {{ $scopeLabel ?: 'cuộc thi này' }}</p>
            </div>

            <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-3">
                @foreach ($podium as $person)
                    @php
                        $rank = (int) $person['rank'];
                        $winner = $rank === 1;
                        $order = $rank === 2 ? 'order-2 md:order-1' : ($rank === 3 ? 'order-3' : 'order-1 md:order-2');
                        $shell = $winner
                            ? 'order-1 min-h-64 border-amber-300 bg-gradient-to-b from-amber-100 via-amber-50 to-white shadow-[0_12px_30px_rgba(245,158,11,0.18)] md:order-2'
                            : 'min-h-56 border-sky-100 bg-white text-[#0B3C78] shadow-[0_8px_24px_rgba(0,100,220,0.08)] '.$order;
                        $rankStyle = $winner ? 'bg-amber-400 text-amber-950' : ($rank === 2 ? 'bg-slate-100 text-slate-700' : 'bg-amber-100 text-amber-800');
                    @endphp
                    <article class="relative flex flex-col items-center justify-end rounded-2xl border p-4 text-center transition-transform hover:-translate-y-1 {{ $shell }}">
                        <div class="absolute -top-3 flex h-8 w-8 items-center justify-center rounded-full border-2 border-white text-xs font-black shadow-sm {{ $rankStyle }}">
                            @if ($winner)
                                <x-lucide name="trophy" class="h-4 w-4" />
                            @else
                                {{ $rank }}
                            @endif
                        </div>
                        <img src="{{ $avatarFor($rank) }}" alt="Avatar của {{ $person['name'] }}"
                             class="shrink-0 rounded-full object-cover {{ $winner ? 'h-20 w-20 border-4 border-amber-400 shadow-lg' : 'h-16 w-16 border-4 border-white/20 shadow-md' }}">
                        <span class="mt-2 rounded-md px-2 py-1 text-[10px] font-bold {{ $winner ? 'bg-amber-200 text-amber-900' : 'bg-sky-50 text-sky-700' }}">{{ $badgeFor($rank) }}</span>
                        <h3 class="mt-2 text-sm font-bold text-[#0B3C78]">{{ $person['name'] }}</h3>
                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $scopeLabel }}</p>
                        <div class="mt-3 flex items-center gap-2 text-xs font-black {{ $winner ? 'text-amber-800' : 'text-sky-700' }}">
                            <span>{{ rtrim(rtrim(number_format($person['score'], 2, '.', ''), '0'), '.') }} điểm</span>
                            <span>•</span>
                            <span>{{ $person['acCount'] }} câu đúng</span>
                        </div>
                        <div class="mt-1 flex items-center gap-1 text-[11px] text-slate-500">
                            <x-lucide name="clock" class="h-3.5 w-3.5 text-orange-400" />
                            {{ $person['computedAt'] ? 'Chấm '.\Illuminate\Support\Carbon::parse($person['computedAt'])->format('d/m/Y') : 'Chưa có mốc chấm' }}
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ══════ 4. DANH SÁCH XẾP HẠNG ══════ --}}
    @if (count($entries) === 0)
        <section class="rounded-2xl border border-dashed border-sky-200 bg-white p-10 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                <x-lucide name="bar-chart" class="h-6 w-6" />
            </div>
            <h2 class="mt-3 text-sm font-bold text-slate-800">Chưa có bảng xếp hạng nào được công bố</h2>
            <p class="mt-1 text-xs text-slate-500">Bảng xếp hạng chỉ hiện sau khi ban tổ chức công bố kết quả cuộc thi.</p>
            <a href="{{ route('competitions.index') }}" class="mt-4 inline-block text-[11px] font-bold text-[#126F91] hover:underline">Xem lịch cuộc thi →</a>
        </section>
    @else
        <section class="overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-[0_4px_16px_rgba(0,100,220,0.05)]">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-sky-700">Ranking</p>
                    <h2 class="mt-1 text-lg font-bold text-[#0B3C78]">Danh sách xếp hạng</h2>
                </div>
                <div class="hidden items-center gap-1.5 text-[11px] text-slate-400 sm:flex">
                    <x-lucide name="target" class="h-3.5 w-3.5 text-sky-500" />Top {{ min($totalEntries, 50) }} được vinh danh
                </div>
            </div>

            <div class="overflow-x-auto">
                <div class="min-w-[720px] divide-y divide-slate-100">
                    <div class="grid grid-cols-[56px_minmax(250px,1fr)_190px_110px_100px] bg-[#F8FBFE] px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500 sm:px-5">
                        <span>Hạng</span><span>Học viên</span><span>Kỳ thi</span><span>Câu làm đúng</span><span class="text-right">Tổng điểm</span>
                    </div>

                    @foreach ($rest as $person)
                        <div x-show="matches({{ $person['rank'] }})" x-cloak
                             class="grid min-h-16 grid-cols-[56px_minmax(250px,1fr)_190px_110px_100px] items-center px-4 py-2.5 text-xs transition-colors hover:bg-sky-50/60 sm:px-5 {{ !empty($person['isYou']) ? 'bg-sky-50/80' : '' }}">
                            <span class="font-bold text-slate-500">#{{ $person['rank'] }}</span>
                            <div class="flex min-w-0 items-center gap-2.5">
                                <img src="{{ $avatarFor((int) $person['rank']) }}" alt="Avatar của {{ $person['name'] }}"
                                     class="h-9 w-9 shrink-0 rounded-full border border-sky-100 bg-sky-50 object-cover">
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-slate-800">
                                        {{ $person['name'] }}
                                        @if (!empty($person['isYou']))
                                            <span class="ml-1 rounded-md bg-blue-100 px-1.5 py-0.5 text-[10px] font-bold text-blue-700">Bạn</span>
                                        @endif
                                    </p>
                                    <p class="mt-0.5 flex items-center gap-1 text-[11px] text-slate-400">
                                        <x-lucide name="clock" class="h-3 w-3 text-orange-400" />
                                        {{ $person['computedAt'] ? \Illuminate\Support\Carbon::parse($person['computedAt'])->format('H:i d/m/Y') : 'Chưa có mốc chấm' }}
                                    </p>
                                </div>
                            </div>
                            <span class="truncate pr-3 text-slate-600">{{ $scopeLabel }}</span>
                            <span class="font-bold text-emerald-600">{{ $person['acCount'] }} câu</span>
                            <span class="text-right font-black text-[#0B3C78]">{{ rtrim(rtrim(number_format($person['score'], 2, '.', ''), '0'), '.') }}</span>
                        </div>
                    @endforeach

                    <div x-show="visibleCount === 0" x-cloak class="px-5 py-10 text-center text-xs text-slate-500">
                        Không tìm thấy hạng hoặc kỳ thi phù hợp.
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 border-t border-slate-100 px-4 py-3 text-[11px] text-slate-400 sm:px-5">
                <x-lucide name="award" class="h-3.5 w-3.5 text-amber-500" />
                Dữ liệu tổng hợp từ các cuộc thi đã công bố kết quả; tên học viên được ẩn danh để bảo vệ dữ liệu học sinh.
            </div>
        </section>
    @endif
</div>
</div>
@endsection

@push('scripts')
    @include('partials.leaderboard-page-script')
@endpush
