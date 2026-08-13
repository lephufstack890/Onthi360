{{--
  Route: leaderboard.index | Frame: PUB-09
  Spec: 11.2 (phạm vi rõ, công thức điểm, "Chờ công bố" không lộ rank
  tạm, bảo vệ dữ liệu trẻ em — mặc định ẩn danh, không dùng ảnh/tên thật).
  TODO controller: truyền $entries theo phạm vi đã chọn + $yourRank (nếu
  user đăng nhập có tham gia) — hiện là dữ liệu minh họa để dựng UI.
  Avatar dùng màu cố định theo hạng, không suy ra từ tên thật (11.2).

  UI tham khảo phong cách bảng xếp hạng của các nền tảng học tập có yếu tố
  game hoá được nhiều người dùng khen đẹp (Duolingo, Kahoot, Quizizz) —
  bục top 3 nổi bật, mũi tên tăng/giảm hạng, thanh điểm so với hạng 1,
  thẻ "Hạng của bạn" luôn hiện. Môi trường dựng UI này không truy cập được
  Internet để chụp lại ảnh tham khảo trực tiếp, nên đây là dựng lại theo
  đúng các mẫu thiết kế phổ biến đã biết, không phải sao chép ảnh cụ thể.
--}}
@extends('layouts.guest')

@section('title', 'Bảng xếp hạng')

@section('content')
    @php
        $scope = 'Cuộc thi Tin học trẻ 2026';
        $formula = 'Tổng điểm các bài · phạt nộp muộn 5%/giờ';
        $daysLeft = 3;
        $filters = ['Tuần này', 'Tháng này', 'Cuộc thi này'];
        $activeFilter = 'Cuộc thi này';
        $yourRank = ['rank' => 12, 'score' => 705, 'previousRank' => 15];
        $entries = [
            ['rank' => 1, 'name' => 'Học sinh đã xác thực', 'score' => 980, 'color' => 'fde68a', 'previousRank' => 1],
            ['rank' => 2, 'name' => 'Học sinh đã xác thực', 'score' => 945, 'color' => 'e2e8f0', 'previousRank' => 3],
            ['rank' => 3, 'name' => 'Học sinh đã xác thực', 'score' => 920, 'color' => 'fed7aa', 'previousRank' => 2],
            ['rank' => 4, 'name' => 'Học sinh đã xác thực', 'score' => 885, 'color' => 'e0f2fe', 'previousRank' => 6],
            ['rank' => 5, 'name' => 'Học sinh đã xác thực', 'score' => 860, 'color' => 'fce7f3', 'previousRank' => 4],
            ['rank' => 6, 'name' => 'Học sinh đã xác thực', 'score' => 840, 'color' => 'dcfce7', 'previousRank' => 5],
            ['rank' => 7, 'name' => 'Học sinh đã xác thực', 'score' => 810, 'color' => 'e0f2fe', 'previousRank' => null],
        ];
        $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
        $top3 = array_slice($entries, 0, 3);
        $rest = array_slice($entries, 3);
        $topScore = $entries[0]['score'] ?? 1;
    @endphp

    {{-- Hero --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 via-purple-950 to-amber-900 text-white">
        <div class="absolute -top-16 -left-16 w-72 h-72 rounded-full bg-amber-400/10 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-24 -right-10 w-80 h-80 rounded-full bg-purple-400/10 blur-3xl" aria-hidden="true"></div>

        <div class="max-w-5xl mx-auto px-4 py-14 lg:py-16 text-center relative">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-amber-200 text-xs font-medium mb-4">🏆 Bảng xếp hạng</span>
            <h1 class="text-2xl lg:text-3xl font-semibold">{{ $scope }}</h1>
            <div class="flex flex-wrap items-center justify-center gap-2 mt-4 text-sm">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 text-slate-200">📐 {{ $formula }}</span>
                @if ($daysLeft !== null)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-400/20 text-amber-200 font-medium">⏳ Còn {{ $daysLeft }} ngày</span>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 py-10 lg:py-14">
        {{-- Hạng của bạn --}}
        @auth
            <div class="rounded-2xl bg-white border-2 border-rose-100 p-4 mb-8 flex items-center gap-4 flex-wrap shadow-sm">
                <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center text-xl font-semibold text-rose-600 shrink-0">#{{ $yourRank['rank'] }}</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-700">Hạng của bạn</p>
                    <p class="text-xs text-slate-400">{{ $yourRank['score'] }} điểm</p>
                </div>
                @if ($yourRank['previousRank'] && $yourRank['previousRank'] > $yourRank['rank'])
                    <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">▲ Tăng {{ $yourRank['previousRank'] - $yourRank['rank'] }} hạng</span>
                @endif
                <a href="{{ route('practice.index') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0 hover:bg-rose-700 transition">Luyện thêm để lên hạng</a>
            </div>
        @else
            <div class="rounded-2xl bg-white border border-dashed border-slate-300 p-4 mb-8 flex items-center gap-4 flex-wrap">
                <span class="text-2xl">🔒</span>
                <p class="text-sm text-slate-500 flex-1 min-w-[200px]">Đăng nhập để xem hạng của chính bạn và theo dõi tiến bộ qua từng tuần.</p>
                <a href="{{ route('login') }}" class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium shrink-0 hover:bg-rose-700 transition">Đăng nhập</a>
            </div>
        @endauth

        {{-- Bộ lọc phạm vi — UI tĩnh, TODO nối filter thật --}}
        <div class="flex flex-wrap justify-center gap-2 mb-10 text-sm">
            @foreach ($filters as $f)
                <button type="button" class="px-3.5 py-1.5 rounded-full font-medium transition {{ $f === $activeFilter ? 'bg-slate-900 text-white' : 'border border-slate-200 text-slate-500 hover:border-slate-300' }}">{{ $f }}</button>
            @endforeach
        </div>

        {{-- Podium top 3 --}}
        <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-10 items-end">
            @foreach ($top3 as $e)
                @php
                    $isFirst = $e['rank'] === 1;
                    $order = $e['rank'] === 1 ? 'order-2' : ($e['rank'] === 2 ? 'order-1' : 'order-3');
                    $ring = $e['rank'] === 1 ? 'ring-4 ring-amber-300' : ($e['rank'] === 2 ? 'ring-4 ring-slate-200' : 'ring-4 ring-orange-200');
                    $pad = $isFirst ? 'pt-8 pb-6' : 'pt-5 pb-5';
                @endphp
                <div class="{{ $order }} rounded-2xl bg-white border border-slate-200 px-3 sm:px-5 {{ $pad }} text-center relative {{ $isFirst ? 'shadow-xl border-amber-200 sm:-translate-y-4' : 'shadow-sm' }}">
                    @if ($isFirst)
                        <span class="absolute -top-3 left-1/2 -translate-x-1/2 text-2xl">👑</span>
                    @endif
                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full mx-auto mb-2 flex items-center justify-center text-2xl {{ $ring }}" style="background-color:#{{ $e['color'] }}">👤</div>
                    <p class="text-xl sm:text-2xl mb-1">{{ $medals[$e['rank']] }}</p>
                    <p class="font-medium text-slate-700 text-xs sm:text-sm truncate">{{ $e['name'] }}</p>
                    <p class="text-base sm:text-lg font-semibold text-slate-800 mt-1">{{ number_format($e['score']) }}<span class="text-xs text-slate-400 font-normal"> đ</span></p>
                </div>
            @endforeach
        </div>

        {{-- Danh sách còn lại --}}
        <div class="bg-white rounded-2xl border border-slate-200 divide-y divide-slate-100">
            @forelse ($rest as $e)
                @php
                    $change = null;
                    if ($e['previousRank']) {
                        $diff = $e['previousRank'] - $e['rank'];
                        $change = $diff > 0 ? ['icon' => '▲', 'tone' => 'text-emerald-600', 'n' => $diff]
                            : ($diff < 0 ? ['icon' => '▼', 'tone' => 'text-rose-500', 'n' => abs($diff)] : null);
                    }
                @endphp
                <div class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition">
                    <span class="w-6 text-sm font-semibold text-slate-400 shrink-0">{{ $e['rank'] }}</span>
                    <span class="w-9 h-9 rounded-full flex items-center justify-center text-sm shrink-0" style="background-color:#{{ $e['color'] }}">👤</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm text-slate-700 truncate">{{ $e['name'] }}</p>
                        <div class="h-1.5 rounded-full bg-slate-100 mt-1.5 max-w-[140px] overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-rose-400 to-amber-400" style="width: {{ max(6, round($e['score'] / $topScore * 100)) }}%"></div>
                        </div>
                    </div>
                    @if ($change)
                        <span class="hidden sm:inline-flex items-center gap-0.5 text-xs font-semibold {{ $change['tone'] }} shrink-0">{{ $change['icon'] }} {{ $change['n'] }}</span>
                    @elseif (! $e['previousRank'])
                        <span class="hidden sm:inline-flex text-xs font-semibold text-sky-600 shrink-0">Mới</span>
                    @endif
                    <span class="text-sm font-semibold text-slate-700 shrink-0 w-16 text-right">{{ number_format($e['score']) }} đ</span>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-slate-400 text-sm">Chưa có dữ liệu xếp hạng.</div>
            @endforelse
        </div>

        {{-- Bảo vệ dữ liệu trẻ em --}}
        <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 mt-6 flex items-start gap-3">
            <x-icon-tile emoji="🔒" tone="violet" />
            <p class="text-sm text-slate-500 leading-relaxed">Tên và ảnh đại diện trên bảng xếp hạng luôn được ẩn danh mặc định để bảo vệ dữ liệu học sinh dưới 18 tuổi (11.2) — thứ tự và điểm số là thật, chỉ danh tính được che.</p>
        </div>
    </div>
@endsection
