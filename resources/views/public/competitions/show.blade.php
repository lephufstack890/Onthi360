@extends('layouts.guest')

@section('title', $competition->title)
@section('meta-description', 'Cuộc thi '.$competition->title.' trên Ôn Thi 360 — thể lệ, thời gian diễn ra, cách tính điểm xếp hạng và hướng dẫn tham gia.')

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

        // SỬA 19/9 — luồng "đăng ký → BTC duyệt → vào phòng". Giá trị do
        // Public\CompetitionService::showData() tính; ?? để trang không vỡ nếu view được
        // render từ nơi khác chưa truyền đủ.
        $registrationPending = $registrationPending ?? false;
        $registrationApproved = $registrationApproved ?? false;
        $registrationRejected = $registrationRejected ?? false;
        $canRequestJoin = $canRequestJoin ?? false;
        $directExamId = $directExamId ?? null;
        $approvedRegistrations = $approvedRegistrations ?? 0;
    @endphp

    <div class="max-w-5xl mx-auto px-4 pt-6">
        <a href="{{ route('competitions.index') }}" class="text-sm text-slate-500 mb-4 inline-flex items-center gap-1 hover:text-rose-600">‹ Quay lại Cuộc thi</a>

        <div class="rounded-3xl overflow-hidden relative mt-3 mb-8 shadow-sm">
            <img src="https://picsum.photos/seed/{{ \Illuminate\Support\Str::slug($competition->title) }}/1200/480" alt="" class="w-full h-56 lg:h-72 object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/85 via-slate-900/35 to-transparent"></div>
            <div class="absolute inset-x-0 bottom-0 p-6 lg:p-8 text-white">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-full bg-white/10 text-white text-xs font-medium">{{ $competition->type->value === 'contest' ? '🏆 Cuộc thi' : '📊 Khảo sát' }}</span>
                    @if ($competition->isExternallyOrganized())
                        <x-status-badge tone="warning">Tổ chức bởi {{ $competition->organizer_name }}</x-status-badge>
                    @endif
                </div>

                <h1 class="text-2xl lg:text-3xl font-semibold mt-2 leading-tight">{{ $competition->title }}</h1>

            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 pb-14">
        <div class="grid grid-cols-1 {{ $countdownText ? 'sm:grid-cols-3' : 'sm:grid-cols-2' }} gap-4 mb-8">
            @if ($countdownText)
                <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                    <p class="text-2xl font-semibold text-rose-600">⏳ {{ $countdownText }}</p>
                    <p class="text-xs text-slate-400 mt-1">nữa bắt đầu</p>
                </div>
            @endif
            {{-- SỬA 19/9 (12) — hai con số KHÁC NHAU, cố ý tách riêng: "đã được duyệt" là số
                 thí sinh ban tổ chức nhận vào, "trên bảng xếp hạng" là số người đã có kết quả
                 chấm xong. Gộp làm một là nói dối một trong hai. --}}
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                <p class="text-2xl font-semibold text-slate-800">👥 {{ number_format($approvedRegistrations) }}</p>
                <p class="text-xs text-slate-400 mt-1">thí sinh đã được duyệt</p>
            </div>
            <div class="rounded-2xl bg-white border border-slate-200 p-5 text-center shadow-sm">
                <p class="text-2xl font-semibold text-slate-800">🏅 {{ number_format($competition->leaderboard_entries_count) }}</p>
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
                {{--
                    SỬA 19/9 — thẻ đăng ký, ĐẶT TRÊN CÙNG vì đây là việc học sinh phải làm
                    trước mọi thứ khác: chưa được BTC duyệt thì mọi nút "Vào thi" bên dưới đều
                    bị AttemptService chặn. Cùng bộ luật với popup ở trang danh sách
                    (public/competitions/index.blade.php) — sửa một bên nhớ sửa bên kia.
                --}}
                @if ($registrationApproved || $registrationPending || $canRequestJoin)
                    <div class="mb-5 rounded-xl border border-sky-100 bg-sky-50/50 p-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold text-[#123B68]">Đăng ký tham gia</h2>
                            <span class="text-[10px] font-medium text-slate-500">BTC duyệt trước khi vào phòng</span>
                        </div>

                        <div class="mt-2 grid grid-cols-3 gap-1.5 text-center text-[10px] font-semibold">
                            <span class="rounded-lg p-1.5 {{ $registrationPending || $registrationApproved ? 'bg-[#E7F3EE] text-[#39755F]' : 'bg-white text-slate-500' }}">1<br>Gửi đăng ký</span>
                            <span class="rounded-lg p-1.5 {{ $registrationApproved ? 'bg-[#E7F3EE] text-[#39755F]' : ($registrationPending ? 'bg-[#FFF3D9] text-[#9A741E]' : 'bg-white text-slate-500') }}">2<br>BTC duyệt</span>
                            <span class="rounded-lg p-1.5 {{ $registrationApproved ? 'bg-[#EAF2F8] text-[#356782]' : 'bg-white text-slate-500' }}">3<br>Vào phòng</span>
                        </div>

                        @if ($registrationApproved)
                            <a href="{{ route('student.competitions.room', $competition->id) }}"
                               class="mt-2.5 block rounded-lg bg-[#43876F] px-3 py-2 text-center text-xs font-semibold text-white hover:bg-[#3A7561]">
                                Đã duyệt · Vào không gian thi
                            </a>
                        @elseif ($registrationPending)
                            <p class="mt-2.5 rounded-lg bg-[#FFF0C7] px-3 py-2 text-center text-xs font-semibold text-[#8D6A1A]">Đã gửi · Chờ BTC duyệt</p>
                        @else
                            <form method="POST" action="{{ route('student.competitions.requestJoin', $competition->id) }}" class="mt-2.5">
                                @csrf
                                <button type="submit" class="w-full rounded-lg bg-[#2F7890] px-3 py-2 text-xs font-semibold text-white hover:bg-[#286B80]">
                                    Đăng ký tham gia
                                </button>
                            </form>
                            @if ($registrationRejected)
                                <p class="mt-2 rounded-lg border border-rose-100 bg-rose-50 px-2.5 py-2 text-[11px] font-medium text-rose-700">Đơn trước bị từ chối — bạn có thể gửi lại.</p>
                            @endif
                        @endif
                    </div>
                @endif

                @if (session('status') === 'competition-join-requested')
                    <p class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700">Đã gửi đăng ký. Vui lòng chờ ban tổ chức duyệt.</p>
                @endif
                @if ($errors->any())
                    <p class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700">{{ $errors->first() }}</p>
                @endif

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
                                    {{-- SỬA 19/9 (2) — phòng thi RIÊNG của cuộc thi, định danh bằng id vòng thi. --}}
                                    <a href="{{ route('student.competitions.exam', ['competition' => $competition->id, 'exam' => $exam['id']]) }}" class="block text-center px-3 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
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
                    {{-- $directExamId null = cuộc thi cũ chưa có vòng thi nào trỏ tới đề này; khi đó
                         vẫn dùng màn làm bài chung để nút không bị chết. --}}
                    <a href="{{ $directExamId !== null ? route('student.competitions.exam', ['competition' => $competition->id, 'exam' => $directExamId]) : route('student.assessment.take', $competition->assessment_id) }}" class="block text-center px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700">
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
