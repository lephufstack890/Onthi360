@extends('layouts.student')

@section('title', 'Kết quả')
@section('page-title', 'Kết quả bài làm')

@section('content')
    {{--
      SỬA 24/9 (khách: "xây lại màn UI cho đẹp, đúng với phong cách style hiện tại").

      DỰNG LẠI GIAO DIỆN, KHÔNG đổi logic: vẫn đúng các khoá dữ liệu của
      Student\AssessmentService::buildResultData(), không thêm truy vấn nào ở view.

      Ngôn ngữ thiết kế bê nguyên từ student/dashboard.blade.php: thẻ đầu trang nền chuyển sắc
      xanh, dải thẻ số liệu bo 2xl có ô icon vuông, khối nội dung nền trắng viền sky-100, nhãn
      nhỏ IN HOA giãn chữ, chữ chính #123B68 / phụ #61798B. Mọi biểu tượng là <x-lucide>.

      Bảng "Chi tiết từng câu" đổi từ <table> sang danh sách dòng: bảng 4 cột trên điện thoại bị
      bóp lại, chữ xuống dòng loạn xạ; danh sách thì màn nào cũng đọc được.
    --}}
    @php
        $isFinal = $isFinal ?? false;
        $score = $score ?? null;
        $total = $total ?? null;
        $breakdown = $breakdown ?? [];
        $eligibleForReview = $eligibleForReview ?? false;
        $reviewType = $reviewType ?? 'material';
        $reviewTargetId = $reviewTargetId ?? null;

        $assessmentTitle = $attemptModel->assessment->title ?? 'Bài làm';

        // Thời gian làm bài — cùng luật với mục Lịch sử làm bài (Student\PracticeService) để hai
        // nơi không nói hai kiểu về cùng một lượt làm.
        $durationLabel = null;
        if (isset($attemptModel) && $attemptModel->started_at && $attemptModel->submitted_at) {
            $seconds = max(0, (int) round($attemptModel->started_at->diffInSeconds($attemptModel->submitted_at)));
            $minutes = intdiv($seconds, 60);
            $durationLabel = match (true) {
                $minutes < 1 => $seconds.' giây',
                $minutes < 60 => $minutes.' phút',
                default => intdiv($minutes, 60).' giờ '.($minutes % 60).' phút',
            };
        }

        $submittedAtLabel = isset($attemptModel) && $attemptModel->submitted_at
            ? $attemptModel->submitted_at->format('H:i d/m/Y')
            : null;

        $percent = ($total !== null && $total > 0 && $score !== null) ? (int) round($score / $total * 100) : null;
        $barPercent = $percent === null ? 0 : max(0, min(100, $percent));

        $questionCount = count($breakdown);
        $correctCount = collect($breakdown)->where('tone', 'success')->count();
        $pendingCount = collect($breakdown)->where('tone', 'info')->count();

        [$resultIcon, $resultHeadline] = match (true) {
            ! $isFinal => ['clock', 'Đang chấm phần bài lập trình'],
            $percent === null => ['file-check-2', 'Đã ghi nhận bài làm'],
            $percent >= 90 => ['trophy', 'Xuất sắc!'],
            $percent >= 70 => ['medal', 'Làm tốt lắm!'],
            $percent >= 50 => ['trending-up', 'Khá ổn, cố thêm chút nữa nhé!'],
            default => ['book-open', 'Cần ôn luyện thêm — đừng nản nhé!'],
        };

        // Bộ màu thẻ số liệu, dùng chung khuôn với bảng điều khiển cá nhân.
        $statTones = [
            'sky' => ['border-sky-100', 'to-[#EAF5F8]', 'text-[#61798B]', 'bg-[#DDF2F5]', 'text-[#126F91]'],
            'emerald' => ['border-emerald-100', 'to-[#EDF8F3]', 'text-[#5E7B6E]', 'bg-[#DFF2E9]', 'text-[#2F8F6F]'],
            'violet' => ['border-violet-100', 'to-[#F4F0FA]', 'text-[#7657A5]', 'bg-[#EEE8F7]', 'text-[#7657A5]'],
            'amber' => ['border-amber-100', 'to-[#FFF8E7]', 'text-[#8F7A58]', 'bg-[#FFF0C7]', 'text-[#B57A2B]'],
        ];

        $stats = [
            ['Điểm đạt được', ($score ?? '—').' / '.($total ?? '—'), $percent === null ? 'đề chưa đặt điểm' : $percent.'% số điểm', 'target', 'sky'],
            ['Câu làm đúng', $correctCount.' / '.$questionCount, $pendingCount > 0 ? $pendingCount.' câu còn đang chấm' : 'trên tổng số câu của đề', 'check-circle', 'emerald'],
            ['Thời gian làm', $durationLabel ?? '—', $submittedAtLabel !== null ? 'nộp lúc '.$submittedAtLabel : 'chưa nộp', 'clock', 'violet'],
            ['Trạng thái', $isFinal ? 'Đã chấm xong' : 'Đang chấm', $isFinal ? 'điểm đã là điểm cuối' : 'điểm còn tạm tính', $isFinal ? 'file-check-2' : 'refresh-cw', 'amber'],
        ];

        $typeLabels = ['mcq' => 'Trắc nghiệm', 'fill_blank' => 'Điền đáp án', 'coding' => 'Lập trình', 'composite' => 'Câu nhiều phần'];
        $typeIcons = ['mcq' => 'list-checks', 'fill_blank' => 'pencil', 'coding' => 'code-2', 'composite' => 'layers'];
        $rowTones = [
            'success' => ['bg-[#DFF2E9]', 'text-[#2F8F6F]', 'check'],
            'danger' => ['bg-[#FEF3F2]', 'text-[#B42318]', 'x'],
            'info' => ['bg-[#DDF2F5]', 'text-[#126F91]', 'clock'],
            'neutral' => ['bg-[#EEF4FA]', 'text-[#8FA3B3]', 'minus'],
        ];
    @endphp

    {{-- ⚠ TỪ ĐÂY TỚI CUỐI LÀ KHỐI ĐƯỢC THAY MỚI KHI CHẤM XONG (AJAX) — xem script cuối trang.
         data-final: 1 = đã chấm xong, thôi hỏi lại. --}}
    <div id="result-container" data-final="{{ $isFinal ? '1' : '0' }}">

    {{-- ══════ THẺ ĐẦU TRANG ══════ --}}
    <header class="relative overflow-hidden rounded-2xl border border-[#0B4E6B] bg-gradient-to-r from-[#064C99] via-[#0066CC] to-[#0891B2] px-4 py-4 shadow-[0_2px_10px_rgba(18,59,104,0.08)] sm:px-5 sm:py-5">
        <span class="pointer-events-none absolute -right-7 -top-7 h-24 w-24 rounded-full bg-white/10"></span>

        <div class="relative flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-0">
                <span class="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] font-bold uppercase tracking-[.08em] text-sky-50">
                    <x-lucide :name="$resultIcon" class="h-3 w-3" />Kết quả bài làm
                </span>
                <h2 class="mt-2 text-lg font-black tracking-tight text-white sm:text-xl">{{ $resultHeadline }}</h2>
                <p class="mt-1 max-w-xl truncate text-[12px] leading-relaxed text-sky-50">{{ $assessmentTitle }}</p>
            </div>

            <div class="shrink-0 text-right">
                <p class="text-4xl font-black leading-none text-white">
                    {{ $score ?? '—' }}<span class="text-base font-bold text-sky-100"> / {{ $total ?? '—' }}</span>
                </p>
                @if ($percent !== null)
                    <div class="ml-auto mt-2 h-2 w-40 overflow-hidden rounded-full bg-white/20">
                        <div class="h-full rounded-full bg-white transition-all" style="width: {{ $barPercent }}%"></div>
                    </div>
                    <p class="mt-1.5 text-[11px] font-bold text-sky-50">{{ $percent }}% số điểm</p>
                @endif
            </div>
        </div>

        @if (! $isFinal)
            {{--
              SỬA 23/9 (khách: "bấm nộp đề nó đứng luôn") — bài lập trình chấm chạy NỀN, nộp xong
              là thấy trang này ngay. Còn câu đang chấm thì tự tải lại sau 5 giây để điểm nhích
              dần, học sinh không phải tự bấm F5.
            --}}
            <div class="relative mt-3 flex flex-wrap items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-3 py-2">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-white">
                    <span class="oi-dot-pulse" aria-hidden="true"></span>Kết quả tạm tính — còn câu lập trình đang chấm
                </span>
                <span class="text-[11px] text-sky-50">Điểm tự cập nhật ngay tại đây, bạn không cần tải lại trang.</span>
            </div>
        @endif
    </header>

    {{-- ══════ DẢI THẺ SỐ LIỆU ══════ --}}
    <section class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach ($stats as [$label, $value, $note, $icon, $tone])
            @php([$border, $bgTo, $labelColor, $tileBg, $tileText] = $statTones[$tone])
            <article class="group relative overflow-hidden rounded-2xl border {{ $border }} bg-gradient-to-br from-white via-white {{ $bgTo }} p-3.5 shadow-[0_4px_14px_rgba(28,91,121,0.06)]">
                <span class="pointer-events-none absolute -right-7 -top-7 h-20 w-20 rounded-full bg-white/60 transition duration-200 group-hover:scale-110"></span>
                <div class="relative flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-[10px] font-semibold uppercase tracking-[.08em] {{ $labelColor }}">{{ $label }}</p>
                        <p class="mt-1.5 truncate text-2xl font-semibold leading-none tracking-tight text-[#123B68]">{{ $value }}</p>
                        <p class="mt-1.5 truncate text-[11px] {{ $labelColor }}">{{ $note }}</p>
                    </div>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl {{ $tileBg }} {{ $tileText }} shadow-sm">
                        <x-lucide :name="$icon" class="h-4 w-4" />
                    </span>
                </div>
            </article>
        @endforeach
    </section>

    {{-- ══════ CHI TIẾT TỪNG CÂU ══════ --}}
    <section class="mt-3 overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-[0_3px_12px_rgba(28,91,121,0.05)]">
        <div class="flex items-center justify-between gap-2 border-b border-slate-100 px-3 py-3 sm:px-4">
            <div>
                <p class="text-[10px] font-bold uppercase tracking-[.12em] text-[#2D7FA3]">Bài làm của bạn</p>
                <h2 class="mt-1 text-sm font-black text-[#123B68]">Chi tiết từng câu</h2>
            </div>
            @if ($questionCount > 0)
                <span class="shrink-0 rounded-full bg-sky-50 px-2.5 py-1 text-[10px] font-bold text-[#126F91]">{{ $questionCount }} câu</span>
            @endif
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($breakdown as $b)
                @php([$dotBg, $dotText, $dotIcon] = $rowTones[$b['tone']] ?? $rowTones['neutral'])
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-3 transition-colors hover:bg-[#F8FBFC] sm:px-4">
                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl {{ $dotBg }} {{ $dotText }}">
                        <x-lucide :name="$dotIcon" class="h-4 w-4" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-x-2 text-[13px] font-bold text-[#123B68]">
                            Câu {{ $b['no'] }}
                            <span class="inline-flex items-center gap-1 rounded-lg border border-[#D6E3EF] bg-[#EEF4FA] px-2 py-0.5 text-[10px] font-bold text-[#365B7A]">
                                <x-lucide :name="$typeIcons[$b['type']] ?? 'file-text'" class="h-3 w-3" />{{ $typeLabels[$b['type']] ?? $b['type'] }}
                            </span>
                        </p>
                        {{-- Câu Lập trình chấm theo tỉ lệ test nên phải nói rõ qua mấy test. --}}
                        @if (! empty($b['testNote']))
                            <p class="mt-1 text-[11px] text-[#7A92A3]">{{ $b['testNote'] }}</p>
                        @endif
                    </div>

                    <x-ws.badge :tone="$b['tone']">{{ $b['verdict'] }}</x-ws.badge>

                    <span class="shrink-0 text-right text-[15px] font-black text-[#123B68]">
                        {{ $b['points'] }}<span class="text-[11px] font-bold text-[#8FA3B3]"> / {{ $b['maxPoints'] ?? '—' }}</span>
                    </span>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#EAF5F8] text-[#126F91]"><x-lucide name="file-text" class="h-5 w-5" /></span>
                    <p class="mt-3 text-sm font-bold text-[#123B68]">Chưa có dữ liệu câu nào</p>
                    <p class="mt-1 text-[12px] text-[#607A90]">Lượt làm bài này không ghi nhận được câu trả lời nào.</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- ══════ LỐI ĐI TIẾP ══════ --}}
    <div class="mt-3 flex flex-wrap gap-2">
        <a href="{{ route('student.practice.index') }}"
           class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-[#126F91] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0F5E7B]">
            <x-lucide name="chevron-left" class="h-3.5 w-3.5" />Quay lại Luyện tập
        </a>
        <a href="{{ route('student.practice.index', ['tab' => 'history']) }}"
           class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl border border-sky-100 bg-white px-4 py-2 text-xs font-bold text-slate-700 transition-colors hover:border-sky-200 hover:bg-sky-50">
            <x-lucide name="history" class="h-3.5 w-3.5" />Lịch sử làm bài
        </a>
    </div>

    {{-- CTA đánh giá nhẹ nhàng — không chặn hành trình học (10.1, 9.6); chỉ hiện khi đủ điều
         kiện, và trỏ ĐÚNG tài liệu/lớp học sinh vừa làm. --}}
    @if ($eligibleForReview && $reviewTargetId !== null)
        <div class="mt-3 mb-8 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-100 bg-amber-50 p-4 sm:p-5">
            <div class="min-w-0">
                <p class="text-sm font-black text-[#123B68]">{{ $reviewType === 'class' ? 'Bạn thấy lớp học này thế nào?' : 'Bạn thấy tài liệu này thế nào?' }}</p>
                <p class="mt-1 text-[12px] text-[#8F7A58]">Chia sẻ trải nghiệm giúp học sinh khác chọn đúng {{ $reviewType === 'class' ? 'lớp' : 'tài liệu' }} hơn.</p>
            </div>
            <a href="{{ route('reviews.form', ['type' => $reviewType, 'id' => $reviewTargetId]) }}"
               class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-amber-500 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-amber-600">
                <x-lucide name="star" class="h-3.5 w-3.5" />{{ $reviewType === 'class' ? 'Đánh giá lớp này' : 'Đánh giá tài liệu này' }}
            </a>
        </div>
    @else
        <div class="mb-8"></div>
    @endif

    </div>{{-- /#result-container --}}
@endsection

@push('scripts')
    {{-- CSS thường: bản CSS trên máy chủ là bản build sẵn (VPS không chạy được vite). --}}
    <style>
        .oi-dot-pulse {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #fff;
            animation: oi-dot-pulse 1s ease-in-out infinite;
        }
        @keyframes oi-dot-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%      { opacity: .35; transform: scale(.7); }
        }
    </style>

    <script>
        /*
         * SỬA 24/9 (khách: "khi chấm đừng có load đi load lại trang mà chấm ngầm xong tự ajax
         * rồi cập nhật điểm đi, chứ load đi load lại trải nghiệm không tốt").
         *
         * TRƯỚC ĐÂY trang này dùng <meta http-equiv="refresh" content="5">: cứ 5 giây tải lại
         * TOÀN BỘ trang — màn hình chớp trắng, vị trí cuộn nhảy về đầu, ai đang đọc dở test sai
         * thì mất chỗ. Mà nó tải lại kể cả khi chưa có gì thay đổi.
         *
         * GIỜ: hỏi lại chính trang này bằng fetch rồi CHỈ thay ruột #result-container. Không
         * chớp, không mất vị trí cuộn, và tự dừng hẳn khi chấm xong (data-final="1").
         *
         * Cố ý tải lại HTML của chính trang thay vì dựng một API JSON riêng: điểm, nhãn verdict,
         * số test, màu sắc... đã có đúng một nơi sinh ra ở Blade. Thêm API nữa là thêm một bản
         * sao của cùng logic, rồi hai bên lệch nhau lúc nào không biết.
         */
        (function () {
            var INTERVAL_MS = 4000;
            var MAX_TRIES = 45; // ~3 phút rồi thôi; lâu hơn thế là máy chấm có trục trặc.

            var tries = 0;
            var timer = null;

            function container() {
                return document.getElementById('result-container');
            }

            function done() {
                var el = container();

                return ! el || el.getAttribute('data-final') === '1';
            }

            async function tick() {
                tries++;

                if (tries > MAX_TRIES || done()) {
                    clearInterval(timer);

                    return;
                }

                try {
                    var res = await fetch(window.location.href, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        cache: 'no-store',
                    });

                    if (! res.ok) return;

                    var html = await res.text();
                    var fresh = new DOMParser().parseFromString(html, 'text/html')
                        .querySelector('#result-container');
                    var current = container();

                    if (! fresh || ! current) return;

                    current.replaceWith(fresh);

                    if (done()) clearInterval(timer);
                } catch (e) {
                    // Mạng chập chờn một nhịp thì thôi, vòng sau hỏi lại.
                }
            }

            if (! done()) {
                timer = setInterval(tick, INTERVAL_MS);
            }
        })();
    </script>
@endpush
