@extends('layouts.guest')

@section('title', 'Chi tiết cuộc thi')

@section('content')
    @php
        $rankingRule = $rankingRule ?? [];
        $startCountdown = $startCountdown ?? null;
        $countdownText = match ($startCountdown['unit'] ?? null) {
            'days' => $startCountdown['days'].' ngày',
            'hm' => $startCountdown['hours'].' giờ '.$startCountdown['minutes'].' phút',
            default => null,
        };
        $canJoinDirectly = $canJoinDirectly ?? false;
        $alreadyAttempted = $alreadyAttempted ?? false;
        $examSittings = $examSittings ?? [];
        $endedExamsCount = collect($examSittings)->where('hasEnded', true)->count();
    @endphp

    <div class="max-w-5xl mx-auto px-4 pt-6">
        <a href="{{ route('competitions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Cuộc thi</a>

        {{-- Hero: ảnh + gradient tối + nội dung đều nằm TRONG cùng 1 khối rounded/overflow-hidden
             cố định chiều cao — tránh dùng margin âm kéo nội dung ra ngoài khối ảnh (từng gây
             lỗi phần nền/gradient bị "dính" chồng lên layout bên dưới khi tiêu đề dài 2 dòng). --}}
        <div class="rounded-3xl overflow-hidden relative mt-3 mb-8 shadow-sm">
            <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($competition->title) }}/1200/480" alt="" class="w-full h-56 lg:h-72 object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/85 via-slate-900/35 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-6 lg:p-8 text-white">
                <div class="flex flex-wrap items-center gap-2">
                    <x-status-badge :tone="$statusTone">{{ $statusLabel }}</x-status-badge>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white/10 text-white text-xs font-medium">{{ $competition->type->value === 'contest' ? '🏆 Cuộc thi' : '📊 Khảo sát' }}</span>
                    @if ($competition->isExternallyOrganized())
                        <x-status-badge tone="warning">Tổ chức bởi {{ $competition->organizer_name }}</x-status-badge>
                    @endif
                </div>

                <h1 class="text-2xl lg:text-3xl font-semibold mt-2 leading-tight">{{ $competition->title }}</h1>

                <p class="text-slate-200 mt-1.5 inline-flex items-center gap-1.5 text-sm">
                    <span>🗓</span>
                    @if ($competition->starts_at && $competition->ends_at)
                        {{ $competition->starts_at->format('d/m/Y H:i') }} – {{ $competition->ends_at->format('d/m/Y H:i') }}
                    @else
                        Chưa đặt lịch
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 pb-14">
        {{-- Dải số liệu nhanh: đếm ngược (nếu còn), số người tham gia, thời lượng --}}
        <div class="grid grid-cols-1 {{ $countdownText ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-4 mb-8">
            @if ($countdownText)
                <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                    <p class="text-2xl font-semibold text-rose-600">⏳ {{ $countdownText }}</p>
                    <p class="text-xs text-slate-400 mt-1">nữa bắt đầu</p>
                </div>
            @endif
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                <p class="text-2xl font-semibold text-slate-800">👥 {{ number_format($competition->leaderboard_entries_count) }}</p>
                <p class="text-xs text-slate-400 mt-1">đã có trên bảng xếp hạng</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                <p class="text-2xl font-semibold text-slate-800">⏱ {{ $competition->assessment?->duration_minutes ? $competition->assessment->duration_minutes."'" : '—' }}</p>
                <p class="text-xs text-slate-400 mt-1">thời gian mỗi lượt thi</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-5">
                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>📋</span> Thể lệ</h2>
                    <p class="text-sm text-slate-500 whitespace-pre-line leading-relaxed">{{ $competition->rules ?: 'Chưa nhập thể lệ.' }}</p>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                    <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🧾</span> Đề tham chiếu</h2>
                    <p class="text-sm text-slate-500">{{ $competition->assessment->title ?? '— Chưa gắn đề —' }}</p>
                    <p class="text-xs text-slate-400 mt-3">Đề thi thuộc kho Tài liệu chung — cuộc thi chỉ tham chiếu để tổ chức thành sự kiện (4.3).</p>
                </div>

                @if (($rankingRule['scoring_note'] ?? '') !== '' || ($rankingRule['penalty_note'] ?? '') !== '' || ($rankingRule['tie_break_note'] ?? '') !== '')
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🥇</span> Quy tắc bảng xếp hạng</h2>
                        <ul class="space-y-2 text-sm text-slate-600">
                            @if ($rankingRule['scoring_note'] ?? null)
                                <li><span class="text-slate-400">Công thức điểm:</span> {{ $rankingRule['scoring_note'] }}</li>
                            @endif
                            @if ($rankingRule['penalty_note'] ?? null)
                                <li><span class="text-slate-400">Penalty:</span> {{ $rankingRule['penalty_note'] }}</li>
                            @endif
                            @if ($rankingRule['tie_break_note'] ?? null)
                                <li><span class="text-slate-400">Đồng điểm:</span> {{ $rankingRule['tie_break_note'] }}</li>
                            @endif
                        </ul>
                    </div>
                @endif

                @if ($competition->isExternallyOrganized() && $competition->advisors->isNotEmpty())
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 shadow-sm">
                        <h2 class="font-medium text-slate-700 mb-3 flex items-center gap-2"><span>🎓</span> Giáo viên cố vấn/đồng hành</h2>
                        <ul class="flex flex-wrap gap-2">
                            @foreach ($competition->advisors as $advisor)
                                <li class="px-2.5 py-1 rounded-full bg-amber-50 border border-amber-100 text-xs text-amber-700">{{ $advisor->name }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 h-fit sticky top-6 shadow-sm">
                @if (count($examSittings) > 0)
                    <div class="flex items-center justify-between mb-1">
                        <h2 class="font-medium text-slate-700">Các kỳ thi</h2>
                        <span class="text-xs text-slate-400">{{ count($examSittings) }} kỳ thi</span>
                    </div>
                    <p class="text-sm text-slate-500 mb-4">Chọn kỳ thi để vào làm hoặc xem bảng xếp hạng riêng.</p>

                    <div class="space-y-3">
                        @foreach ($examSittings as $exam)
                            <div class="rounded-xl border p-3.5 transition-colors
                                @if ($exam['hasEnded']) border-slate-200 bg-slate-50
                                @elseif ($exam['ongoing']) border-emerald-200 bg-emerald-50/50
                                @else border-slate-200 bg-white @endif">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <p class="text-sm font-medium text-slate-700 truncate">{{ $exam['title'] }}</p>
                                    <x-status-badge :tone="$exam['statusTone']">{{ $exam['statusLabel'] }}</x-status-badge>
                                </div>
                                @if ($exam['startsAt'] || $exam['endsAt'])
                                    <p class="text-xs text-slate-400 mb-3">
                                        🗓 {{ $exam['startsAt']?->format('d/m/Y H:i') ?? '…' }} – {{ $exam['endsAt']?->format('d/m/Y H:i') ?? '…' }}
                                    </p>
                                @endif

                                @if ($exam['hasEnded'])
                                    <a href="{{ route('leaderboard.index', ['competition' => $competition->id, 'exam' => $exam['id']]) }}" class="block text-center px-3 py-2 rounded-lg border border-slate-200 bg-white text-slate-600 text-sm font-medium hover:bg-slate-100">
                                        Xem kết quả ›
                                    </a>
                                @elseif ($exam['alreadyAttempted'])
                                    {{-- SỬA 18/8: mỗi học sinh chỉ được làm 1 kỳ thi con này 1 lần — đã nộp bài rồi (dù kỳ thi vẫn đang "Đang diễn ra" cho người khác) thì hiện "Đã làm" thay vì "Vào thi" nữa, vẫn cho xem bảng xếp hạng riêng của kỳ thi này. --}}
                                    <a href="{{ route('leaderboard.index', ['competition' => $competition->id, 'exam' => $exam['id']]) }}" class="block text-center px-3 py-2 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 text-sm font-medium hover:bg-emerald-100">
                                        ✓ Đã làm — Xem xếp hạng
                                    </a>
                                @elseif ($exam['canJoinDirectly'])
                                    <a href="{{ route('student.assessment.take', $exam['assessmentId']) }}" class="block text-center px-3 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                                        Vào thi
                                    </a>
                                @elseif ($exam['ongoing'])
                                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="block text-center px-3 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50">
                                        {{ auth()->check() ? 'Về trang của tôi' : 'Đăng nhập để tham gia' }}
                                    </a>
                                @else
                                    <p class="text-center px-3 py-2 rounded-lg bg-slate-100 text-slate-400 text-sm font-medium">Chưa mở — quay lại sau</p>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if ($endedExamsCount > 0)
                        <a href="{{ route('leaderboard.index', ['competition' => $competition->id]) }}" class="block text-center mt-4 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50">
                            Xem bảng xếp hạng tổng
                        </a>
                    @endif
                @elseif ($canJoinDirectly)
                    <h2 class="font-medium text-slate-700 mb-2">Sẵn sàng tham gia?</h2>
                    <p class="text-sm text-slate-500 mb-4">Vào làm đề tham chiếu của cuộc thi — kết quả sẽ được ghi nhận vào hồ sơ của bạn.</p>
                    <a href="{{ route('student.assessment.take', $competition->assessment_id) }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                        Vào thi ngay
                    </a>
                    <a href="{{ route('leaderboard.index', ['competition' => $competition->id]) }}" class="block text-center mt-2 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50">
                        Xem bảng xếp hạng
                    </a>
                @elseif ($alreadyAttempted)
                    {{-- SỬA 18/8: mỗi học sinh chỉ được làm cuộc thi này 1 lần — đã nộp bài rồi thì hiện "Đã làm" thay vì "Vào thi ngay" nữa. --}}
                    <h2 class="font-medium text-slate-700 mb-2">Bạn đã hoàn thành!</h2>
                    <p class="text-sm text-slate-500 mb-4">Bạn đã nộp bài cho cuộc thi này rồi — mỗi học sinh chỉ được làm 1 lần. Xem kết quả và bảng xếp hạng bên dưới.</p>
                    <a href="{{ route('leaderboard.index', ['competition' => $competition->id]) }}" class="block text-center px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">
                        ✓ Đã làm — Xem bảng xếp hạng
                    </a>
                @else
                    <h2 class="font-medium text-slate-700 mb-2">Sẵn sàng tham gia?</h2>
                    <p class="text-sm text-slate-500 mb-4">Đăng nhập để tham gia — kết quả và bảng xếp hạng sẽ tự cập nhật vào hồ sơ của bạn.</p>
                    <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
                        {{ auth()->check() ? 'Về trang của tôi' : 'Đăng nhập để tham gia' }}
                    </a>
                    <a href="{{ route('leaderboard.index', ['competition' => $competition->id]) }}" class="block text-center mt-2 px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:bg-slate-50">
                        Xem bảng xếp hạng
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
