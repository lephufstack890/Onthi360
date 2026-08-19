@extends('layouts.guest')

@section('title', 'Bảng xếp hạng')

@section('content')
    @php
        $boards = $boards ?? [];
        $entries = $entries ?? [];
        $yourEntry = $yourEntry ?? null;
        $rankingRule = $rankingRule ?? [];
        $totalEntries = $totalEntries ?? 0;
        $examTabs = $examTabs ?? [];
        $selectedExamId = $selectedExamId ?? null;
        $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
        $top3 = array_slice($entries, 0, 3);
        $rest = array_slice($entries, 3);
        $topScore = $entries[0]['score'] ?? 1;
        $hasRankingRule = ($rankingRule['scoring_note'] ?? '') !== '' || ($rankingRule['penalty_note'] ?? '') !== '' || ($rankingRule['tie_break_note'] ?? '') !== '';
    @endphp

    {{-- Hero --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-purple-950 to-amber-900 text-white">
        <div class="absolute -top-16 -left-16 w-72 h-72 rounded-full bg-amber-400/10 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-24 -right-10 w-80 h-80 rounded-full bg-purple-400/10 blur-3xl" aria-hidden="true"></div>

        <div class="max-w-5xl mx-auto px-4 py-14 lg:py-16 text-center relative">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-amber-200 text-xs font-medium mb-4">🏆 Bảng xếp hạng</span>
            <h1 class="text-2xl lg:text-3xl font-semibold">{{ $selected->title ?? 'Chưa có bảng xếp hạng nào được công bố' }}</h1>
            @if ($selected)
                <div class="flex flex-wrap items-center justify-center gap-2 mt-4 text-sm">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-slate-200">👥 {{ number_format($totalEntries) }} người tham gia</span>
                    @if ($updatedAt)
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-slate-200">🕐 Cập nhật {{ $updatedAt->diffForHumans() }}</span>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-10 lg:py-14">
        {{-- Bộ chọn cuộc thi — phạm vi thật (11.2), thay cho bộ lọc thời gian giả trước đây --}}
        @if (count($boards) > 1)
            <div class="flex flex-wrap justify-center gap-2 mb-4 text-sm">
                @foreach ($boards as $b)
                    <a href="{{ route('leaderboard.index', ['competition' => $b['id']]) }}"
                       class="px-3.5 py-1.5 rounded-full font-medium transition {{ $selected && $selected->id === $b['id'] ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-500 hover:border-slate-300' }}">
                        {{ $b['title'] }}
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Bộ chọn kỳ thi (Tổng cuộc thi / từng kỳ thi) — chỉ hiện khi cuộc thi đang chọn có kỳ thi. --}}
        @if ($selected && count($examTabs) > 0)
            <div class="flex flex-wrap justify-center gap-2 mb-10 text-sm">
                <a href="{{ route('leaderboard.index', ['competition' => $selected->id]) }}"
                   class="px-3.5 py-1.5 rounded-full font-medium transition {{ $selectedExamId === null ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-500 hover:border-slate-300' }}">
                    Tổng cuộc thi
                </a>
                @foreach ($examTabs as $tab)
                    <a href="{{ route('leaderboard.index', ['competition' => $selected->id, 'exam' => $tab['id']]) }}"
                       class="px-3.5 py-1.5 rounded-full font-medium transition {{ $selectedExamId === $tab['id'] ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-500 hover:border-slate-300' }}">
                        {{ $tab['title'] }}
                    </a>
                @endforeach
            </div>
        @endif

        @if ($selected === null)
            <div class="max-w-lg mx-auto">
                <x-empty-state title="Chưa có bảng xếp hạng nào được công bố" description="Kết quả sẽ hiển thị ở đây khi cuộc thi hoàn tất và admin công bố xếp hạng (11.2)." />
            </div>
        @else
            {{-- Công thức điểm / penalty / đồng điểm --}}
            @if ($hasRankingRule)
                <div class="rounded-2xl bg-white border border-slate-200 p-4 mb-8 text-sm text-slate-600 space-y-1">
                    @if ($rankingRule['scoring_note'] ?? null)
                        <p><span class="text-slate-400">Công thức điểm:</span> {{ $rankingRule['scoring_note'] }}</p>
                    @endif
                    @if ($rankingRule['penalty_note'] ?? null)
                        <p><span class="text-slate-400">Penalty:</span> {{ $rankingRule['penalty_note'] }}</p>
                    @endif
                    @if ($rankingRule['tie_break_note'] ?? null)
                        <p><span class="text-slate-400">Đồng điểm:</span> {{ $rankingRule['tie_break_note'] }}</p>
                    @endif
                </div>
            @endif

            {{-- Hạng của bạn --}}
            @auth
                @if ($yourEntry)
                    <div class="rounded-2xl bg-white border-2 border-rose-100 p-4 mb-8 flex items-center gap-4 flex-wrap shadow-sm">
                        <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center text-xl font-semibold text-rose-600 shrink-0">#{{ $yourEntry['rank'] }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-700">Hạng của bạn</p>
                            <p class="text-xs text-slate-400">{{ number_format($yourEntry['score'], 2) }} điểm</p>
                        </div>
                        <a href="{{ route('student.practice.index') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0 hover:bg-rose-700 transition">Luyện thêm để lên hạng</a>
                    </div>
                @else
                    <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-4 mb-8 flex items-center gap-4 flex-wrap">
                        <span class="text-2xl">👀</span>
                        <p class="text-sm text-slate-500 flex-1 min-w-[200px]">Bạn chưa có trong bảng xếp hạng này — tham gia cuộc thi để xuất hiện ở đây.</p>
                        <a href="{{ route('competitions.index') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0 hover:bg-rose-700 transition">Xem cuộc thi</a>
                    </div>
                @endif
            @else
                <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-4 mb-8 flex items-center gap-4 flex-wrap">
                    <span class="text-2xl">🔒</span>
                    <p class="text-sm text-slate-500 flex-1 min-w-[200px]">Đăng nhập để xem hạng của chính bạn nếu bạn đã tham gia cuộc thi này.</p>
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0 hover:bg-rose-700 transition">Đăng nhập</a>
                </div>
            @endauth

            {{-- Podium top 3 --}}
            @if (count($top3) > 0)
                <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-10 items-end">
                    @foreach ($top3 as $e)
                        @php
                            $isFirst = $e['rank'] === 1;
                            $order = $e['rank'] === 1 ? 'order-2' : ($e['rank'] === 2 ? 'order-1' : 'order-3');
                            $ring = $e['isYou'] ? 'ring-4 ring-rose-300' : ($e['rank'] === 1 ? 'ring-4 ring-amber-300' : ($e['rank'] === 2 ? 'ring-4 ring-slate-200' : 'ring-4 ring-orange-200'));
                            $pad = $isFirst ? 'pt-8 pb-6' : 'pt-5 pb-5';
                        @endphp
                        <div class="{{ $order }} rounded-2xl bg-white border border-slate-200 px-3 sm:px-5 {{ $pad }} text-center relative {{ $isFirst ? 'shadow-xl border-amber-200 sm:-translate-y-4' : 'shadow-sm' }}">
                            @if ($isFirst)
                                <span class="absolute -top-3 left-1/2 -translate-x-1/2 text-2xl">👑</span>
                            @endif
                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full mx-auto mb-2 flex items-center justify-center text-2xl bg-slate-100 {{ $ring }}">👤</div>
                            <p class="text-xl sm:text-2xl mb-1">{{ $medals[$e['rank']] ?? '' }}</p>
                            <p class="font-medium text-slate-700 text-xs sm:text-sm truncate">{{ $e['name'] }}{{ $e['isYou'] ? ' (Bạn)' : '' }}</p>
                            <p class="text-base sm:text-lg font-semibold text-slate-800 mt-1">{{ number_format($e['score'], 2) }}<span class="text-xs text-slate-400 font-normal"> đ</span></p>
                        </div>
                    @endforeach
                </div>
            @endif

            {{--
                Danh sách còn lại (hạng 4 trở đi) — SỬA 19/8 (báo cáo thật của Admin: "trang
                rõ ràng có hiện Top 3/Hạng của bạn ở trên mà bên dưới vẫn hiện 'Chưa có dữ liệu
                xếp hạng', k hiểu"): $rest = array_slice($entries, 3) — khi TOÀN BỘ cuộc thi có
                từ 1-3 người tham gia, $rest LUÔN rỗng (mọi người đã hiện đủ ở bục Top 3 phía
                trên rồi, không có ai "hạng 4 trở đi" cả) — đây là chuyện BÌNH THƯỜNG, không
                phải "chưa có dữ liệu". Trước đây @forelse/@empty không phân biệt được 2 trường
                hợp "chỉ còn thiếu người hạng 4+" (bình thường) và "cuộc thi này thật sự chưa có
                ai xếp hạng" (thật sự trống) — cả 2 đều hiện chung 1 câu gây hiểu lầm. Giờ: chỉ
                hiện khối này (và câu "Chưa có dữ liệu xếp hạng.") khi $entries RỖNG HOÀN TOÀN
                (trên thực tế khó xảy ra ở đây vì indexData() đã lọc leaderboard_entries_count
                > 0 từ đầu — chỉ còn là lớp phòng vệ); có 1-3 người thì Top 3 ở trên đã đủ, ẩn
                hẳn khối này, không hiện thêm câu nào gây rối.
            --}}
            @if (count($rest) > 0)
                <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
                    @foreach ($rest as $e)
                        <div class="flex items-center gap-3 px-4 py-3 {{ $e['isYou'] ? 'bg-rose-50/60' : 'hover:bg-slate-50' }} transition">
                            <span class="w-6 text-sm font-semibold text-slate-400 shrink-0">{{ $e['rank'] }}</span>
                            <span class="w-9 h-9 rounded-full bg-slate-100 flex items-center justify-center text-sm shrink-0">👤</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-slate-700 truncate">{{ $e['name'] }}{{ $e['isYou'] ? ' (Bạn)' : '' }}</p>
                                <div class="h-1.5 rounded-full bg-slate-100 mt-1.5 max-w-[140px] overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-rose-400 to-amber-400" style="width: {{ max(6, round($e['score'] / max($topScore, 1) * 100)) }}%"></div>
                                </div>
                            </div>
                            <span class="text-sm font-semibold text-slate-700 shrink-0 w-20 text-right">{{ number_format($e['score'], 2) }} đ</span>
                        </div>
                    @endforeach
                </div>
            @elseif (count($entries) === 0)
                <div class="bg-white rounded-2xl border border-slate-200">
                    <div class="px-4 py-8 text-center text-slate-400 text-sm">Chưa có dữ liệu xếp hạng.</div>
                </div>
            @endif

            @if ($totalEntries > count($entries))
                <p class="text-xs text-slate-400 text-center mt-3">Hiển thị top {{ count($entries) }}/{{ number_format($totalEntries) }} — bảng đầy đủ do admin quản lý.</p>
            @endif

            {{-- Bảo vệ dữ liệu trẻ em --}}
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 mt-6 flex items-start gap-3">
                <x-icon-tile emoji="🔒" tone="violet" />
                <p class="text-sm text-slate-500 leading-relaxed">Tên và ảnh đại diện trên bảng xếp hạng luôn được ẩn danh mặc định để bảo vệ dữ liệu học sinh dưới 18 tuổi (11.2) — thứ tự và điểm số là thật, chỉ danh tính được che.</p>
            </div>
        @endif
    </div>
@endsection
