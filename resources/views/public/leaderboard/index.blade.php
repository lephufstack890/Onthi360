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
    $badgeFor = fn (int $rank) => match (true) {
        $rank === 1 => 'Quán quân',
        $rank === 2 => 'Á quân',
        $rank === 3 => 'Hạng ba',
        $rank <= 10 => 'Top 10',
        $rank <= 50 => 'Top 50',
        default => 'Đã xếp hạng',
    };

    // Điểm cao nhất của bảng — mẫu số cho thanh "Tiến độ điểm" (SỬA 12/9).
    $topScore = 0.0;
    foreach ($entries as $e) {
        $topScore = max($topScore, (float) $e['score']);
    }

    // Nút "Chi tiết" ở cuối mỗi dòng trỏ về đúng cuộc thi đang xem.
    $detailHref = $selected ? route('competitions.show', $selected->id) : route('competitions.index');

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
<div x-data="onthiLeaderboardPage({{ Js::from(['rows' => $rows, 'pageSize' => 5]) }})" class="flex flex-col gap-4 animate-fadeIn">

    {{-- ══════ 1. HERO ══════ --}}
    <section class="relative overflow-hidden rounded-2xl border border-sky-200/90 bg-gradient-to-r from-[#0B4F86] via-[#166A9B] to-[#2B93BA] p-5 text-white shadow-[0_8px_24px_rgba(0,100,220,0.08)] sm:p-6">
        <img src="{{ asset('assets/hero-leaderboard.jpg') }}" alt=""
             class="pointer-events-none absolute inset-0 h-full w-full object-cover object-right opacity-25 mix-blend-overlay">

        <div class="relative z-10 flex flex-col items-start justify-between gap-5 lg:flex-row lg:items-center">
            <div class="max-w-2xl">
                <div class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-amber-400 px-3 py-1 text-[11px] font-bold text-amber-950">
                    <x-lucide name="sparkles" class="h-3.5 w-3.5" />Vinh danh Top Coder toàn quốc
                </div>
                <h1 class="text-2xl font-bold leading-tight tracking-tight text-white">Bảng xếp hạng & Đại sảnh Danh vọng</h1>
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
                            <x-lucide name="medal" class="h-6 w-6 text-amber-300" />
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
                <x-lucide name="bar-chart-3" class="h-3.5 w-3.5 text-sky-600" />Đang xem: <strong class="text-slate-700">{{ $scopeLabel ?: 'Chưa chọn' }}</strong>
            </span>
            <span class="inline-flex items-center gap-1.5">
                <x-lucide name="check-circle-2" class="h-3.5 w-3.5 text-emerald-600" />
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
                    {{-- SỬA 12/9 — source mới đổi hẳn bục vinh danh: vòng huy chương vàng/bạc/đồng
                         bọc ngoài ảnh, huy hiệu hình viên thuốc có vương miện, bỏ vòng tròn nổi ở
                         mép trên. --}}
                    @php
                        $rank = (int) $person['rank'];
                        $order = $rank === 2 ? 'order-2 md:order-1' : ($rank === 3 ? 'order-3' : 'order-1 md:order-2');
                        $theme = match ($rank) {
                            1 => [
                                'shell' => 'order-1 min-h-64 border-[#E3C56C] bg-[#FFF9EA] shadow-[0_12px_30px_rgba(176,135,45,0.15)] md:order-2',
                                'medal' => 'bg-gradient-to-br from-[#F8D978] via-[#D9AA42] to-[#A8751E]',
                                'crown' => 'text-[#B98521]',
                                'badge' => 'bg-[#FFF0C7] text-[#8B681D]',
                                'score' => 'text-[#916D1F]',
                            ],
                            2 => [
                                'shell' => 'min-h-56 border-[#CCD5DE] bg-[#F8FAFC] text-[#0B3C78] shadow-[0_8px_24px_rgba(74,91,108,0.10)] '.$order,
                                'medal' => 'bg-gradient-to-br from-[#EEF2F5] via-[#BFC8D0] to-[#8996A2]',
                                'crown' => 'text-[#7E8C99]',
                                'badge' => 'bg-[#E9EEF2] text-[#667582]',
                                'score' => 'text-[#667582]',
                            ],
                            default => [
                                'shell' => 'min-h-56 border-[#E6C2A5] bg-[#FFF9F4] text-[#0B3C78] shadow-[0_8px_24px_rgba(153,91,47,0.10)] '.$order,
                                'medal' => 'bg-gradient-to-br from-[#EBC19F] via-[#B98055] to-[#89502F]',
                                'crown' => 'text-[#9D633E]',
                                'badge' => 'bg-[#F8E5D6] text-[#89583A]',
                                'score' => 'text-[#89583A]',
                            ],
                        };
                    @endphp
                    <article class="relative flex flex-col items-center justify-end rounded-2xl border p-5 text-center transition-transform hover:-translate-y-1 {{ $theme['shell'] }}">
                        <div class="flex h-24 w-24 items-center justify-center rounded-full p-1 shadow-md {{ $theme['medal'] }}">
                            <img src="{{ $avatarFor($rank) }}" alt="Avatar của {{ $person['name'] }}"
                                 class="h-full w-full shrink-0 rounded-full border-4 border-white/90 object-cover shadow-inner">
                        </div>
                        <div class="mt-2 inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-bold {{ $theme['badge'] }}">
                            <x-lucide name="crown" class="h-4 w-4 {{ $theme['crown'] }}" fill="currentColor" stroke-width="1.5" />Hạng {{ $rank }}
                        </div>
                        <h3 class="mt-2 text-sm font-bold text-[#0B3C78]">{{ $person['name'] }}</h3>
                        {{-- Bản mẫu in tên trường; hệ thống không có cột trường nên đặt tên kỳ thi —
                             cũng là thông tin phân biệt, và là dữ liệu thật. --}}
                        <p class="mt-0.5 text-[11px] text-slate-500">{{ $scopeLabel }}</p>
                        <p class="mt-2 text-base font-black {{ $theme['score'] }}">{{ rtrim(rtrim(number_format($person['score'], 2, '.', ''), '0'), '.') }} pts</p>
                        <p class="mt-1 text-[11px] text-slate-400">{{ $person['acCount'] }} câu đúng · {{ $person['computedAt'] ? 'chấm '.\Illuminate\Support\Carbon::parse($person['computedAt'])->format('d/m/Y') : 'chưa có mốc chấm' }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ══════ 4. DANH SÁCH XẾP HẠNG ══════ --}}
    @if (count($entries) === 0)
        <section class="rounded-2xl border border-dashed border-sky-200 bg-white p-10 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                <x-lucide name="school" class="h-6 w-6" />
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
                    <x-lucide name="target" class="h-3.5 w-3.5 text-sky-500" />Top {{ min($totalEntries, 100) }} được vinh danh
                </div>
            </div>

            {{-- SỬA 12/9 — source mới nới bảng ra nhiều cột hơn, có thanh tiến độ, sọc chẵn/lẻ,
                 nút xem chi tiết và phân trang 5 dòng/trang.
                 3 cột của bản mẫu KHÔNG có nguồn dữ liệu nên được thay bằng cột thật:
                   · "Trường THPT"  -> "Kỳ thi" (tên bảng xếp hạng đang xem)
                   · "Chuỗi N ngày" -> thời điểm chấm
                   · "Rating"/"Biến động" -> bỏ (không có lịch sử thứ hạng để tính) --}}
            <div class="overflow-x-auto">
                <div class="min-w-[920px] divide-y divide-slate-100">
                    <div class="grid grid-cols-[56px_minmax(240px,1fr)_180px_150px_130px_105px_56px] bg-[#F8FBFE] px-4 py-3 text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500 sm:px-5">
                        <span>Hạng</span><span>Học viên & danh hiệu</span><span>Kỳ thi</span><span>Tiến độ điểm</span><span>Thời điểm chấm</span><span class="text-right">Tổng điểm</span><span class="text-center">Chi tiết</span>
                    </div>

                    @foreach ($rest as $person)
                        @php
                            // Tiến độ = điểm của dòng này so với điểm cao nhất bảng — số thật, không ước lượng.
                            $ratio = $topScore > 0 ? (int) round(min(100, max(0, $person['score'] / $topScore * 100))) : 0;
                        @endphp
                        <div x-show="isVisible({{ $person['rank'] }})" x-cloak
                             class="grid min-h-20 grid-cols-[56px_minmax(240px,1fr)_180px_150px_130px_105px_56px] items-center px-4 py-3 text-xs transition-colors even:bg-[#F8FBFE] hover:bg-sky-50 sm:px-5 {{ !empty($person['isYou']) ? 'bg-sky-50/80' : '' }}">
                            <span class="font-bold text-slate-500">#{{ $person['rank'] }}</span>

                            <div class="flex min-w-0 items-center gap-3">
                                <img src="{{ $avatarFor((int) $person['rank']) }}" alt="Avatar của {{ $person['name'] }}"
                                     class="h-10 w-10 shrink-0 rounded-full border border-sky-100 bg-sky-50 object-cover">
                                <div class="min-w-0">
                                    <div class="flex min-w-0 items-center gap-1.5">
                                        <p class="truncate font-bold text-slate-800">{{ $person['name'] }}</p>
                                        @if (!empty($person['isYou']))
                                            <span class="shrink-0 rounded border border-cyan-200 bg-cyan-50 px-1.5 py-0.5 text-[9px] font-bold text-cyan-700">Bạn</span>
                                        @else
                                            <span class="shrink-0 rounded border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-700">{{ $badgeFor((int) $person['rank']) }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-[11px] text-slate-400">{{ $person['acCount'] }} câu làm đúng</p>
                                </div>
                            </div>

                            <span class="truncate pr-3 text-slate-600">{{ $scopeLabel }}</span>

                            <div class="pr-4">
                                <div class="flex items-center justify-between gap-2 text-[11px] font-bold">
                                    <span class="text-emerald-700">{{ $person['acCount'] }} câu</span>
                                    <span class="text-slate-400">{{ $ratio }}%</span>
                                </div>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-emerald-500" style="width: {{ $ratio }}%"></div>
                                </div>
                            </div>

                            <span class="inline-flex w-fit items-center gap-1 rounded-lg border border-orange-200 bg-orange-50 px-2 py-1.5 text-[11px] font-bold text-orange-600">
                                <x-lucide name="clock" class="h-3.5 w-3.5 shrink-0" />
                                {{ $person['computedAt'] ? \Illuminate\Support\Carbon::parse($person['computedAt'])->format('d/m/Y') : '—' }}
                            </span>

                            <span class="text-right">
                                <strong class="block text-sm font-black text-[#0B3C78]">{{ rtrim(rtrim(number_format($person['score'], 2, '.', ''), '0'), '.') }}</strong>
                                <small class="text-[10px] text-slate-400">pts</small>
                            </span>

                            <a href="{{ $detailHref }}" aria-label="Xem chi tiết kỳ thi" title="Xem chi tiết kỳ thi"
                               class="mx-auto flex h-8 w-8 items-center justify-center rounded-lg bg-sky-50 text-blue-600 transition-colors hover:bg-sky-100">
                                <x-lucide name="eye" class="h-4 w-4" />
                            </a>
                        </div>
                    @endforeach

                    @if (count($rest) === 0)
                        {{-- Bảng chỉ có 1–3 người: tất cả đã lên bục vinh danh, không còn dòng nào để liệt kê. --}}
                        <div class="px-5 py-10 text-center text-xs text-slate-500">
                            Toàn bộ thí sinh đã được vinh danh ở bục phía trên.
                        </div>
                    @else
                        <div x-show="visibleCount === 0" x-cloak class="px-5 py-10 text-center text-xs text-slate-500">
                            Không tìm thấy hạng hoặc kỳ thi phù hợp.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Phân trang 5 dòng/trang, đúng như source mới --}}
            <nav x-show="visibleCount > 0 && totalPages > 1" x-cloak aria-label="Phân trang bảng xếp hạng"
                 class="flex items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-[11px] text-slate-500 sm:px-5">
                <span>Trang <strong class="text-slate-700" x-text="page">1</strong> / <span x-text="totalPages">1</span></span>
                <div class="flex items-center gap-1">
                    <button type="button" aria-label="Trang trước" :disabled="page === 1" @click="page = page - 1"
                            class="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-40">
                        <x-lucide name="chevron-left" class="h-4 w-4" />
                    </button>
                    <template x-for="pageNumber in totalPages" :key="pageNumber">
                        <button type="button" :aria-label="'Trang ' + pageNumber" :aria-current="pageNumber === page ? 'page' : null"
                                @click="page = pageNumber"
                                class="grid h-8 min-w-8 place-items-center rounded-lg px-2 font-bold transition"
                                :class="pageNumber === page ? 'bg-[#126F91] text-white' : 'text-slate-500 hover:bg-sky-50'"
                                x-text="pageNumber"></button>
                    </template>
                    <button type="button" aria-label="Trang sau" :disabled="page === totalPages" @click="page = page + 1"
                            class="grid h-8 w-8 place-items-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-sky-300 hover:bg-sky-50 disabled:cursor-not-allowed disabled:opacity-40">
                        <x-lucide name="chevron-right" class="h-4 w-4" />
                    </button>
                </div>
            </nav>

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
