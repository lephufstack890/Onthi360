@extends('layouts.exam')

@section('title', 'Làm bài tập')

{{--
    SỬA 31/8 (3, khách yêu cầu tách riêng UI): "Làm bài" 1 bài tập cụ thể (mở từ "Tài liệu của
    tôi", và TỪ 18/9 cả từ bảng bài tập ở trang Luyện tập công khai — xem
    PracticeByQuestionService::startForQuestion(), luôn mode='single_question') có view RIÊNG,
    KHÔNG dùng chung với student/practice/by-question-play.blade.php. Vẫn cùng route/state/logic
    chấm ở PracticeByQuestionService — chỉ khác template, chọn ở PracticeByQuestionController::play().

    SỬA 18/9 (khách: "sao click nó không ra trang này, update UI này rồi mà") — dựng lại lớp áo
    theo ĐÚNG bản mẫu education-main/src/components/AssessmentModal.jsx ở chế độ singleExercise
    (một bài, nên KHÔNG có dải số câu và nút ghi "Nộp bài" chứ không phải "Nộp đề"): khung toàn
    màn hình, rail 4 tab dọc, nền sáng/tối.

    LOGIC GIỮ NGUYÊN TUYỆT ĐỐI — form trả lời, khối kết quả, script chấm AJAX và CodeMirror đều
    là các khối CŨ chép nguyên văn sang, không sửa một dòng:
      · #practice-container vẫn là khối DUY NHẤT bị thay sau mỗi lần chấm. Nó nằm BÊN TRONG tab
        "Làm bài" (không bọc ra ngoài rail tab) — Alpine 3 KHÔNG khởi tạo DOM được nhét vào bằng
        JS, bọc ra ngoài là mất luôn phần chuyển tab sau lần chấm đầu tiên.
      · Phần bên trong #practice-container không dùng directive Alpine nào, chỉ data-* như cũ.
--}}
@section('content')
    @php
        $finished = $finished ?? false;
        $feedback = $feedback ?? null;
        $options = $options ?? [];
        $compositeParts = $compositeParts ?? [];
        $assets = $assets ?? [];
        $backUrl = $returnUrl ?? route('student.library.index');
        // SỬA 18/9 — nhãn nút quay lại đi theo NƠI MỞ phiên luyện (xem
        // PracticeByQuestionService::startForQuestion). Không truyền thì giữ nguyên nhãn cũ.
        $backLabel = $backLabel ?? 'Quay lại Tài liệu của tôi';

        $statementUrl = null;
        if (! $finished) {
            $hasStatement = isset($question->metadata['attachments']['statement']['path']);
            if ($hasStatement) {
                // SỬA 18/9 — LỖI CŨ: luôn dựng link theo product_id. Phiên luyện 1 câu giờ còn
                // mở được cho câu trong KHO CHUNG (product_id = null) — route() gặp tham số null
                // sẽ ném UrlGenerationException, tức là trắng trang 500 ngay khi câu đó có đính
                // kèm đề bài. Câu không thuộc sản phẩm dùng route statement riêng của học sinh
                // (cùng quyền: chỉ cho kind='statement').
                $statementUrl = $question->product_id !== null
                    ? route('access.resource.exerciseAttachment', [$question->product_id, $question->id, 'statement'])
                    : route('student.practiceByQuestion.statement', $question->id);
            }
        }

        $typeBadge = ! $finished ? match ($question->type->value) {
            'mcq' => 'Trắc nghiệm',
            'fill_blank' => 'Điền đáp án',
            'composite' => 'Câu hỏi nhiều phần',
            default => 'Lập trình',
        } : null;

        // Bản mẫu chia giao diện làm bài làm 2 nhánh: câu Lập trình (trình soạn mã +
        // INPUT/OUTPUT) và các dạng còn lại (một panel "Cách trả lời").
        $isCode = ! $finished && $question->type->value === 'coding';
    @endphp

    {{-- SỬA 18/9 (khách: "copy Assessment Modal á, khi click thì nó hiển thị modal vậy á") —
         lớp vỏ chép ĐÚNG 2 thẻ ngoài cùng của bản mẫu: nền tối phủ kín + khung bo góc thụt vào
         8px mỗi bên. Trang vẫn có URL riêng (bấm F5 hay nút Back của trình duyệt đều đúng,
         không mất bài đang làm) nhưng nhìn y hệt modal của bản mẫu. --}}
    <div class="assessment-modal fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-0 sm:p-2"
         x-data="{
             tab: 'work',
             theme: 'light',
             init() {
                 try { if (window.localStorage.getItem('onthi360-exam-theme') === 'dark') this.setTheme('dark'); } catch (e) {}
             },
             setTheme(next) {
                 this.theme = next;
                 document.documentElement.classList.toggle('theme-dark', next === 'dark');
             },
             toggleTheme() {
                 this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
                 try { window.localStorage.setItem('onthi360-exam-theme', this.theme); } catch (e) {}
             },
         }" x-init="init()">

        <div class="assessment-modal-shell flex h-full w-full max-w-none flex-col overflow-hidden bg-[#F8FBFC] shadow-2xl sm:h-[calc(100dvh-16px)] sm:max-w-[calc(100vw-16px)] sm:rounded-xl">

        {{-- ══════════════════════════ HEADER ══════════════════════════ --}}
        <header class="assessment-modal-header flex shrink-0 items-center gap-2 border-b border-[#DDEAF0] bg-white px-3 py-2 sm:px-4">
            <a href="{{ $backUrl }}" aria-label="{{ $backLabel }}" title="{{ $backLabel }}"
               class="rounded-xl p-2 text-[#607A90] transition hover:bg-[#F4F9FB]"><x-lucide name="x" class="h-5 w-5" /></a>

            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#126F91]">
                    @if ($question->code){{ $question->code }} · @endif{{ $question->points }} điểm
                </p>
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <h2 class="min-w-0 truncate text-sm font-extrabold text-[#123B68] sm:text-base">{{ $question->title }}</h2>
                    @if ($typeBadge)
                        <span class="shrink-0 rounded-lg bg-[#EAF5F8] px-2 py-1 text-[10px] font-bold text-[#126F91]">{{ $typeBadge }}</span>
                    @endif
                </div>
            </div>

            <button type="button" @click="toggleTheme()" aria-label="Đổi nền sáng/tối" title="Đổi nền sáng/tối"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-[#DDEAF0] bg-white text-[#607A90] transition hover:bg-[#EAF5F8]">
                <span x-show="theme === 'dark'" x-cloak><x-lucide name="sun" class="h-4 w-4" /></span>
                <span x-show="theme !== 'dark'"><x-lucide name="moon" class="h-4 w-4" /></span>
            </button>

            {{-- "Thoát bài tập" — form CŨ, giữ nguyên route/hành vi. --}}
            <form method="POST" action="{{ route('student.practiceByQuestion.stop') }}" class="shrink-0">
                @csrf
                <button type="submit" class="hidden items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-[#607A90] transition hover:bg-[#F4F9FB] sm:flex">Thoát bài tập</button>
            </form>

            {{-- Nút chính trên header BẤM HỘ nút submit đang nằm trong #practice-container (khối
                 này bị thay mới sau mỗi lần chấm nên không thể trỏ cứng vào 1 form). Nhãn được
                 một MutationObserver nhỏ ở cuối trang đồng bộ lại — xem script. --}}
            <button type="button" data-header-submit
                    class="flex shrink-0 items-center gap-1.5 rounded-xl bg-[#126F91] px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0D5B77] disabled:cursor-not-allowed disabled:opacity-50">
                <x-lucide name="send" class="h-4 w-4" /><span data-header-submit-label>Nộp bài</span>
            </button>
        </header>

        {{-- ═══════════════════ RAIL 4 TAB + NỘI DUNG ═══════════════════ --}}
        <div class="assessment-modal-main flex min-h-0 flex-1 flex-col md:flex-row">
            <aside class="assessment-modal-tabs shrink-0 border-b border-[#DDEAF0] bg-white md:w-12 md:border-b-0 md:border-r">
                <div class="grid h-full grid-cols-4 gap-1 p-1.5 md:flex md:flex-col md:gap-1 md:p-2">
                    @foreach ([['pdf', 'Đề bài PDF'], ['work', 'Làm bài'], ['guide', 'Hướng dẫn'], ['sample', 'Bài mẫu']] as [$tabId, $tabLabel])
                        <button type="button" @click="tab = '{{ $tabId }}'" title="{{ $tabLabel }}" aria-label="{{ $tabLabel }}"
                                class="flex min-h-9 min-w-0 items-center justify-center rounded-lg px-1.5 py-1.5 text-center transition md:min-h-[56px] md:w-full md:flex-col md:justify-center"
                                :class="tab === '{{ $tabId }}' ? 'bg-[#126F91] text-white shadow-sm' : 'text-[#45657D] hover:bg-[#F4F9FB]'">
                            <span class="min-w-0"><span class="block text-[10px] font-extrabold leading-tight md:rotate-180 md:[writing-mode:vertical-rl]">{{ $tabLabel }}</span></span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <main class="assessment-modal-content min-w-0 flex-1 overflow-hidden">

                {{-- ───────── TAB: ĐỀ BÀI PDF ─────────
                     SỬA 18/9 — tự vẽ bằng pdf.js cho VỪA CHIỀU NGANG khung; trình xem PDF của
                     trình duyệt dùng kiểu "vừa cả trang" nên đề khổ lớn hiện bé tí, mà ép bằng
                     tham số mở tệp thì Chrome không nghe. Xem partials/pdf-fit-viewer. --}}
                <section x-show="tab === 'pdf'" x-cloak class="assessment-pdf-surface h-full min-h-0 overflow-y-auto bg-[#EAF4F8] p-2 sm:p-3">
                    @if ($statementUrl)
                        <div data-pdf-fit data-pdf-url="{{ $statementUrl }}" class="min-h-full"></div>
                    @else
                        <div class="grid h-full place-items-center p-8 text-center">
                            <div>
                                <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-white text-[#126F91]"><x-lucide name="file-text" class="h-5 w-5" /></span>
                                <p class="mt-3 text-sm font-extrabold text-[#123B68]">Bài này không có bản PDF</p>
                                <p class="mt-1 text-[11px] text-[#607A90]">Toàn bộ nội dung đề nằm ở tab <span class="font-bold">Làm bài</span>.</p>
                            </div>
                        </div>
                    @endif
                </section>

                {{-- ───────── TAB: LÀM BÀI ─────────
                     SỬA 18/9 (khách: "tab làm bài copy UI in đúc source mới, đang sai UI") —
                     dựng lại theo ĐÚNG <WorkPanel> của AssessmentModal.jsx:
                       · câu Lập trình  -> lưới [trình soạn mã 1.6fr | INPUT + OUTPUT 0.9fr]
                       · các dạng khác  -> một panel "Cách trả lời" + thanh nút nộp bên dưới
                       · đã chấm xong   -> khối kết quả chiếm trọn khung (bản mẫu cũng thay cả
                                           màn bằng <SubmissionResult> sau khi nộp)
                     Toàn bộ TÊN TRƯỜNG, thuộc tính required và khối kết quả giữ y như cũ. --}}
                <section x-show="tab === 'work'" class="assessment-work-panel flex h-full min-h-0 flex-col overflow-hidden p-1.5 sm:p-2">
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
                        <span class="truncate text-[10px] font-bold uppercase tracking-[.08em] text-[#7A92A3]">{{ $isCode ? 'Soạn mã' : 'Trả lời câu hỏi' }}</span>
                        @if (! $finished)
                            <span class="shrink-0 text-[10px] font-bold text-[#7A92A3]">{{ $question->points }} điểm</span>
                        @endif
                    </div>

                    <div class="mt-2 min-h-0 flex-1 overflow-y-auto lg:overflow-hidden">
                        {{-- ⚠ TỪ ĐÂY TRỞ XUỐNG LÀ KHỐI BỊ THAY MỚI SAU MỖI LẦN CHẤM (AJAX).
                             Không đặt directive Alpine nào bên trong. --}}
                        <div id="practice-container">
                        @if ($finished)
                            <div class="mx-auto mt-6 max-w-2xl rounded-2xl border border-[#DDEAF0] bg-white p-8 text-center shadow-sm">
                                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#EAF5F8] text-2xl">🎉</span>
                                <h2 class="mt-4 text-lg font-extrabold text-[#123B68]">Đã ghi nhận bài làm!</h2>
                                <p class="mt-2 text-[13px] leading-6 text-[#607A90]">Bài tập của bạn đã được ghi nhận — phần nào tự chấm được đã báo đúng/sai ngay, phần tự luận/lập trình (nếu có) chờ chấm sau.</p>
                                <a href="{{ $backUrl }}" class="mt-5 inline-flex items-center gap-1.5 rounded-xl bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77]">‹ {{ $backLabel }}</a>
                            </div>
                        @elseif ($feedback !== null)
                            {{-- ĐÃ CHẤM — khối kết quả CŨ, chép nguyên văn, chiếm trọn khung. --}}
                            <div class="mx-auto max-w-3xl rounded-xl bg-white p-4 sm:p-5">
                        {{-- Đã trả lời — hiện kết quả đúng/sai + đáp án đúng, khoá form lại. --}}
                        <div class="space-y-3">
                            @if ($question->type->value === 'mcq')
                                @foreach ($options as $i => $opt)
                                    @if ($opt !== '' && $opt !== null)
                                        @php
                                            $isCorrectOpt = in_array((int) $i, array_map('intval', $feedback['correctOptions']), true);
                                            $isYourPick = (string) $feedback['yourSelectedOption'] === (string) $i;
                                        @endphp
                                        <div @class([
                                            'flex items-center gap-2 p-4 rounded-xl border text-base',
                                            'border-emerald-300 bg-emerald-50 text-emerald-700' => $isCorrectOpt,
                                            'border-blue-300 bg-blue-50 text-blue-600' => $isYourPick && ! $isCorrectOpt,
                                            'border-sky-100 text-slate-500' => ! $isCorrectOpt && ! $isYourPick,
                                        ])>
                                            <span>{{ $isCorrectOpt ? '✓' : ($isYourPick ? '✕' : '') }}</span>
                                            <span>{{ $opt }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            @elseif ($question->type->value === 'fill_blank')
                                <div class="p-4 rounded-xl border border-sky-100 text-base text-slate-500">
                                    Bạn trả lời: <span class="font-medium text-slate-700">{{ $feedback['yourText'] }}</span>
                                </div>
                                <div class="p-4 rounded-xl border border-emerald-300 bg-emerald-50 text-base text-emerald-700">
                                    Đáp án đúng: {{ implode(', ', $feedback['acceptedAnswers']) }}
                                </div>
                            @elseif ($question->type->value === 'composite')
                                @foreach (($feedback['compositeParts'] ?? []) as $part)
                                    <div @class([
                                        'p-4 rounded-xl border text-base',
                                        'border-emerald-300 bg-emerald-50 text-emerald-700' => $part['gradable'] && $part['isCorrect'],
                                        'border-blue-300 bg-blue-50 text-blue-600' => $part['gradable'] && ! $part['isCorrect'],
                                        'border-sky-200 bg-sky-50 text-sky-700' => ! $part['gradable'],
                                    ])>
                                        <p class="font-medium mb-1">Phần {{ strtoupper($part['code']) }} ({{ $part['points'] }} điểm)</p>
                                        <p>Bạn trả lời: {{ is_bool($part['yourAnswer']) ? ($part['yourAnswer'] ? 'Đúng' : 'Sai') : ($part['yourAnswer'] ?: '—') }}</p>
                                        @if ($part['gradable'])
                                            <p>{{ $part['isCorrect'] ? '✓ Chính xác' : '✕ Chưa đúng — đáp án đúng: '.$part['correctAnswer'] }}</p>
                                        @else
                                            <p><x-lucide name="mail" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> Đã ghi nhận — phần tự luận chưa có chấm tự động.</p>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="flex items-center gap-2 px-3.5 py-2 rounded-xl border border-sky-100 text-[13px] text-slate-500 mb-2">
                                    <span>Ngôn ngữ:</span> <span class="font-semibold text-slate-700">{{ $feedback['yourLanguage'] ?: '—' }}</span>
                                </div>
                                <pre class="p-4 rounded-xl border border-slate-700 bg-[#272822] text-[13px] text-slate-100 font-mono overflow-x-auto whitespace-pre-wrap">{{ $feedback['yourCode'] }}</pre>
                            @endif

                            {{-- SỬA 3/9 (3, khách yêu cầu: "logic ok hết rồi, xây lại UI cho đẹp")
                                 — banner verdict đổi từ khối chữ căn giữa trơn sang bố cục ngang
                                 icon tròn + chữ (rõ ràng/hiện đại hơn), cùng kiểu áp dụng ở
                                 by-question-play.blade.php. --}}
                            @if ($feedback['gradable'])
                                <div @class([
                                    'rounded-xl p-4 flex items-center gap-3',
                                    'bg-emerald-50 border border-emerald-200' => $feedback['isCorrect'],
                                    'bg-blue-50 border border-blue-200' => ! $feedback['isCorrect'],
                                ])>
                                    <span @class([
                                        'w-9 h-9 rounded-full flex items-center justify-center text-base font-bold shrink-0',
                                        'bg-emerald-100 text-emerald-700' => $feedback['isCorrect'],
                                        'bg-blue-100 text-blue-600' => ! $feedback['isCorrect'],
                                    ])>{{ $feedback['isCorrect'] ? '✓' : '✕' }}</span>
                                    {{-- SỬA 3/9 (2, đồng bộ với by-question-play.blade.php) — câu
                                         Lập trình hiện nhãn verdict CỤ THỂ (VerdictStatus::label(),
                                         vd "Sai kết quả (Wrong Answer)"/"Lỗi biên dịch (Compilation
                                         Error)"/"Quá thời gian (Time Limit Exceeded)") thay vì luôn
                                         "✕ Chưa đúng" chung chung — MCQ/điền đáp án/composite giữ
                                         nguyên câu cũ (không có nhiều dạng verdict như Lập trình). --}}
                                    <span @class([
                                        'text-base font-semibold',
                                        'text-emerald-700' => $feedback['isCorrect'],
                                        'text-blue-600' => ! $feedback['isCorrect'],
                                    ])>
                                        @if ($question->type->value === 'coding' && ! $feedback['isCorrect'] && $feedback['codingVerdictLabel'])
                                            {{ $feedback['codingVerdictLabel'] }}
                                        @else
                                            {{ $feedback['isCorrect'] ? 'Chính xác!' : 'Chưa đúng — xem đáp án ở trên.' }}
                                        @endif
                                    </span>
                                </div>
                                {{-- SỬA 3/9 (2, khách yêu cầu: hiện chi tiết từng test đúng/sai +
                                     cho tải test sai về) — danh sách ĐẦY ĐỦ từng test case
                                     (PracticeByQuestionService::judgeCodingAnswer() trả
                                     'codingTestCases', xem CodeJudgingService::judge()). Test
                                     ĐÚNG chỉ hiện 1 dòng khoá cứng — test SAI bấm vào mới xổ chi
                                     tiết (script cuối trang, cùng logic by-question-play.blade.php).
                                     SỬA 3/9 (3) — gộp các dòng test vào 1 khung chung (divide-y)
                                     thay vì mỗi dòng 1 khung riêng rời rạc, thêm icon tròn ✓/✕
                                     thay ký tự trơn, đồng bộ phong cách với banner verdict trên. --}}
                                @if ($question->type->value === 'coding' && ! empty($feedback['codingTestCases']))
                                    @php
                                        $tcs = $feedback['codingTestCases'];
                                        $tcPassed = collect($tcs)->where('isAccepted', true)->count();
                                        $tcFailed = collect($tcs)->reject(fn ($t) => $t['isAccepted'])->values();
                                    @endphp
                                    <div class="mt-3 rounded-xl border border-sky-100 overflow-hidden divide-y divide-slate-100">
                                        <div class="px-3.5 py-2 bg-slate-50 flex items-center justify-between">
                                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Kết quả từng test</p>
                                            <span class="text-xs font-semibold text-slate-600">Đúng {{ $tcPassed }}/{{ count($tcs) }}</span>
                                        </div>
                                        @foreach ($tcs as $tc)
                                            <div data-test-case-row>
                                                <button type="button"
                                                        @class([
                                                            'w-full flex items-center justify-between gap-2 px-3.5 py-2 text-[13px] text-left transition-colors',
                                                            'text-emerald-700' => $tc['isAccepted'],
                                                            'text-blue-600 hover:bg-sky-50' => ! $tc['isAccepted'],
                                                        ])
                                                        @if ($tc['isAccepted']) disabled @else data-test-case-toggle @endif>
                                                    <span class="inline-flex items-center gap-2">
                                                        <span @class([
                                                            'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                                            'bg-emerald-100 text-emerald-700' => $tc['isAccepted'],
                                                            'bg-blue-100 text-blue-600' => ! $tc['isAccepted'],
                                                        ])>{{ $tc['isAccepted'] ? '✓' : '✕' }}</span>
                                                        Test {{ $tc['index'] }} — {{ $tc['statusLabel'] }}
                                                    </span>
                                                    @if (! $tc['isAccepted'])
                                                        <span data-test-case-arrow class="text-slate-400">▾</span>
                                                    @endif
                                                </button>
                                                @if (! $tc['isAccepted'])
                                                    <div class="hidden px-3.5 py-2.5 text-xs text-slate-600 bg-slate-50 border-t border-slate-100 space-y-2" data-test-case-detail>
                                                        <div>
                                                            <p class="font-semibold text-slate-500 mb-1">Dữ liệu vào</p>
                                                            <pre class="p-2 rounded-xl bg-white border border-sky-100 overflow-x-auto whitespace-pre-wrap">{{ $tc['input'] !== '' ? $tc['input'] : '(rỗng)' }}</pre>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold text-slate-500 mb-1">Kết quả mong đợi</p>
                                                            <pre class="p-2 rounded-xl bg-white border border-sky-100 overflow-x-auto whitespace-pre-wrap">{{ $tc['expectedOutput'] }}</pre>
                                                        </div>
                                                        <div>
                                                            <p class="font-semibold text-slate-500 mb-1">Chương trình của bạn in ra</p>
                                                            <pre class="p-2 rounded-xl bg-white border border-sky-100 overflow-x-auto whitespace-pre-wrap">{{ $tc['actualOutput'] !== null && $tc['actualOutput'] !== '' ? $tc['actualOutput'] : '(không có gì)' }}</pre>
                                                        </div>
                                                        @if ($tc['compileOutput'] || $tc['stderr'])
                                                            <div>
                                                                <p class="font-semibold text-blue-500 mb-1">Lỗi</p>
                                                                <pre class="p-2 rounded-xl bg-blue-50 border border-blue-200 text-blue-700 overflow-x-auto whitespace-pre-wrap">{{ trim(($tc['compileOutput'] ?? '')."\n".($tc['stderr'] ?? '')) }}</pre>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    @if ($tcFailed->isNotEmpty())
                                        <button type="button"
                                                class="mt-2 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold text-blue-600 bg-blue-50 hover:text-blue-700 transition-colors"
                                                data-download-failed-tests
                                                data-question-id="{{ $question->id }}"
                                                data-tests="{{ $tcFailed->toJson() }}">
                                            ⬇️ Tải test sai (.txt)
                                        </button>
                                    @endif
                                @endif
                            @elseif (! empty($feedback['codingError']))
                                {{-- SỬA 18/9 (khách: "ghi nhận bài làm máy chấm vẫn không chấm được") — LỖI CŨ:
                                     máy chấm chết thì rơi vào nhánh @else bên dưới và hiện "chưa có chấm tự
                                     động cho phần này", nghe như hệ thống CỐ Ý không chấm bài Lập trình — trong
                                     khi thật ra là máy chấm không tới được. Giờ nói đúng bản chất + trấn an là
                                     bài KHÔNG bị tính sai, xem PracticeByQuestionService::judgeCodingAnswer(). --}}
                                <div class="rounded-xl p-4 flex items-start gap-3 bg-amber-50 border border-amber-200">
                                    <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 bg-amber-100 text-amber-700"><x-lucide name="alert-triangle" class="h-4 w-4" /></span>
                                    <span class="min-w-0">
                                        <span class="block text-[13px] font-bold text-amber-800">Chưa chấm được bài</span>
                                        <span class="mt-0.5 block text-[13px] leading-relaxed text-amber-800">{{ $feedback['codingError'] }}</span>
                                    </span>
                                </div>
                            @else
                                <div class="rounded-xl p-4 flex items-center gap-3 bg-sky-50 border border-sky-200">
                                    <span class="w-9 h-9 rounded-full flex items-center justify-center text-base shrink-0 bg-sky-100 text-sky-700"><x-lucide name="mail" class="h-4 w-4" /></span>
                                    <span class="text-[13px] font-medium text-sky-700">Đã ghi nhận bài làm — chưa có chấm tự động cho phần này.</span>
                                </div>
                            @endif

                            <form method="POST" action="{{ route('student.practiceByQuestion.next') }}" class="mt-1">
                                @csrf
                                <button type="submit" class="w-full px-4 py-3 rounded-xl bg-blue-600 hover:bg-blue-700 transition-colors text-white text-base font-semibold shadow-sm">
                                    Hoàn tất bài tập ›
                                </button>
                            </form>
                        </div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('student.practiceByQuestion.answer') }}" class="min-h-full" data-ajax-answer>
                                @csrf
                                @if ($isCode)
                                    {{-- data-work-panel: mốc để script "Chạy test" tìm 4 ô (mã nguồn / ngôn ngữ /
                                         Input / Output) trong CÙNG panel này thay vì dò cả trang — xem cuối tệp. --}}
                                    <div data-work-panel class="grid min-h-full gap-2 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,0.9fr)]">
                                        {{-- ── Trình soạn mã (CodeEditorPanel của bản mẫu) ── --}}
                                        <section class="flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-xl bg-[#F4F9FB]">
                                            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-white px-3 py-2.5 text-[#123B68] sm:px-4">
                                                {{-- Chỉ 2 ngôn ngữ vì máy chấm CHỈ nhận 2 (config/judge0.php) — bày
                                                     thêm là hứa cái hệ thống không chấm được. Giá trị gửi lên giữ
                                                     nguyên 'cpp'/'python' như cũ. --}}
                                                <select name="language" aria-label="Chọn ngôn ngữ lập trình"
                                                        class="rounded-lg bg-[#F4F9FB] px-2 py-1.5 text-[10px] font-bold text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                                                    <option value="cpp" selected>C++17</option>
                                                    <option value="python">Python 3</option>
                                                </select>
                                                <div class="flex items-center gap-1.5">
                                                    <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-[#EAF5F8] px-2 py-1.5 text-[10px] font-bold text-[#126F91] transition hover:bg-[#D9EFF3]">
                                                        <x-lucide name="upload" class="h-3.5 w-3.5" />Nộp bằng file
                                                        <input type="file" accept=".cpp,.cc,.cxx,.h,.hpp,.py,.txt" class="hidden" data-code-file>
                                                    </label>
                                                    <button type="button" data-code-reset
                                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[10px] font-bold text-[#607A90] transition hover:bg-[#F4F9FB]"><x-lucide name="rotate-ccw" class="h-3.5 w-3.5" />Đặt lại</button>
                                                </div>
                                            </div>

                                            {{-- SỬA 18/9 (khách: "tab làm bài là để làm bài chứ không cần hiển thị đề") —
                                                 có bản PDF thì ô soạn mã KHÔNG in lại đề nữa (trước đây đề chiếm nửa
                                                 khung, đẩy chỗ gõ code xuống dưới), chỉ để một dòng chỉ chỗ đọc.
                                                 Bài KHÔNG có PDF thì vẫn phải in đề ở đây — bỏ luôn là học sinh không
                                                 còn chỗ nào đọc được đề mà làm. --}}
                                            @if ($statementUrl)
                                                <p class="shrink-0 px-4 pb-2 pt-2 text-[11px] text-[#7A92A3]">Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                            @elseif ($question->body)
                                                <div class="max-h-[30%] shrink-0 overflow-y-auto px-4 pb-3 pt-3 text-xs leading-5 text-[#45657D]">
                                                    <div class="rich-content">{!! $question->body !!}</div>
                                                </div>
                                            @endif

                                            {{-- Lớp tô màu nằm dưới, textarea trong suốt nằm trên — đúng cách bản mẫu làm. --}}
                                            <div class="relative min-h-[240px] flex-1 overflow-hidden">
                                                <pre aria-hidden="true" data-code-highlight
                                                     class="pointer-events-none absolute inset-0 z-20 overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6"></pre>
                                                <textarea name="code_source" data-code-source spellcheck="false"
                                                          aria-label="Trình soạn mã có tô màu cú pháp"
                                                          style="color: transparent; -webkit-text-fill-color: transparent;"
                                                          class="absolute inset-0 z-10 h-full w-full resize-none overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6 outline-none selection:bg-[#2F8A6B]/40"></textarea>
                                            </div>
                                        </section>

                                        {{-- ── INPUT / OUTPUT (TestInputPanel + TestOutputPanel) ── --}}
                                        <div class="grid min-h-[420px] min-w-0 grid-rows-2 gap-2 overflow-hidden">
                                            <section class="flex min-h-0 flex-col overflow-hidden rounded-xl bg-[#EEF6F8]">
                                                <div class="flex shrink-0 items-center justify-between gap-2 px-3 py-2.5">
                                                    <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#126F91]">Input</span>
                                                    {{-- SỬA 18/9 (khách: "chỗ chạy test không được") — nút này trước
                                                         đây bị khoá cứng vì CHƯA có route chạy thử. Giờ đã có
                                                         student.practiceByQuestion.run (chạy thật trên Judge0 với dữ
                                                         liệu vào tự gõ, không chấm điểm) — xem script cuối trang. --}}
                                                    <button type="button" data-run-test
                                                            data-run-url="{{ route('student.practiceByQuestion.run') }}"
                                                            title="Chạy thử mã với dữ liệu vào bên dưới (không tính điểm)"
                                                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#2F8A6B] px-2.5 py-1.5 text-[10px] font-bold text-white shadow-sm transition hover:bg-[#256F56] disabled:cursor-not-allowed disabled:opacity-50"><x-lucide name="play" class="h-3.5 w-3.5" /><span data-run-test-label>Chạy test</span></button>
                                                </div>
                                                {{-- KHÔNG đổ sẵn test từ database: test_cases là test CHẤM ĐIỂM, không có
                                                     cờ phân biệt test mẫu/test ẩn. --}}
                                                <textarea data-run-input spellcheck="false" placeholder="Nhập dữ liệu vào để thử nghiệm…" aria-label="Dữ liệu đầu vào test"
                                                          class="min-h-0 flex-1 resize-none bg-white/80 px-3 py-3 font-mono text-[11px] leading-5 text-[#123B68] outline-none"></textarea>
                                            </section>

                                            <section class="flex min-h-0 flex-col overflow-hidden rounded-xl bg-[#F7F9FA]">
                                                <div class="flex shrink-0 items-center justify-between gap-2 px-3 py-2.5">
                                                    <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#607A90]">Output</span>
                                                    {{-- Nhãn trạng thái lần chạy gần nhất: "Chạy xong · 0.03s" hoặc lý do lỗi. --}}
                                                    <span data-run-status class="text-[10px] font-bold text-[#7A92A3]"></span>
                                                </div>
                                                <pre data-run-output class="min-h-0 flex-1 overflow-y-auto whitespace-pre-wrap bg-white/80 px-3 py-3 font-mono text-[11px] leading-5 text-[#45657D]">Chưa chạy test</pre>
                                                <div class="shrink-0 border-t border-[#DDEAF0] bg-white p-2.5">
                                                    <button type="submit" class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-[#126F91] px-4 py-2.5 text-[11px] font-bold text-white shadow-sm transition hover:bg-[#0F5E7B]">
                                                        <x-lucide name="send" class="h-3.5 w-3.5" />Ghi nhận bài làm
                                                    </button>
                                                    <p data-ajax-error class="mt-2 hidden text-center text-[11px] text-[#B42318]"></p>
                                                </div>
                                            </section>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex min-h-full flex-col gap-2">
                                        {{-- ── ResponsePanel của bản mẫu ── --}}
                                        <section class="flex min-h-[420px] min-w-0 flex-1 flex-col overflow-hidden rounded-xl bg-[#EEF6F8]">
                                            <div class="shrink-0 px-3 py-2.5 text-[10px] font-black uppercase tracking-[.12em] text-[#126F91]">Cách trả lời</div>
                                            <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                                                {{-- Cùng luật với ô soạn mã: có PDF thì đề đọc ở tab riêng, không in lại. --}}
                                                @if ($statementUrl)
                                                    <p class="mb-3 text-[11px] text-[#7A92A3]">Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                                @elseif ($question->body)
                                                    <div class="mb-3 rounded-lg bg-white px-3 py-3">
                                                        <div class="rich-content text-xs leading-6 text-[#45657D]">{!! $question->body !!}</div>
                                                    </div>
                                                @endif

                                                {{-- Học liệu CẦN để trả lời (vd nghe audio nghe-hiểu) — khối CŨ, chép nguyên văn. --}}
                                                @if (! empty($assets))
                                                    <div class="mb-3 space-y-3">
                                                        @foreach ($assets as $asset)
                                                            <div class="rounded-lg bg-white p-3">
                                                                @if ($asset['kind'] === 'audio')
                                                                    <audio controls preload="none" class="w-full" src="{{ $asset['url'] }}"></audio>
                                                                @elseif ($asset['kind'] === 'image')
                                                                    <img src="{{ $asset['url'] }}" alt="{{ $asset['altText'] ?? '' }}" class="rounded-lg">
                                                                @else
                                                                    <a href="{{ $asset['url'] }}" class="text-[12px] font-semibold text-[#126F91]"><x-lucide name="file-text" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $asset['filename'] ?? 'Tệp đính kèm' }}</a>
                                                                @endif
                                                                @if (! empty($asset['altText']))
                                                                    <p class="mt-1 text-[10px] text-[#7A92A3]"><x-lucide name="headphones" class="inline h-3.5 w-3.5 shrink-0 align-[-2px]" /> {{ $asset['altText'] }}</p>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif

                                                @if ($question->type->value === 'mcq')
                                                    <div class="space-y-2">
                                                        @foreach ($options as $i => $opt)
                                                            @if ($opt !== '' && $opt !== null)
                                                                <label class="flex cursor-pointer items-center gap-3 rounded-lg bg-white px-3 py-2.5 text-xs font-semibold text-[#45657D] transition hover:bg-[#F8FBFC] has-[:checked]:bg-[#EAF5F8] has-[:checked]:text-[#126F91] has-[:checked]:shadow-sm">
                                                                    <input type="radio" name="selected_option" value="{{ $i }}" required class="h-4 w-4 accent-[#126F91]">
                                                                    <span>{{ chr(65 + (int) $i) }}. {{ $opt }}</span>
                                                                </label>
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @elseif ($question->type->value === 'fill_blank')
                                                    <div class="space-y-2">
                                                        <label class="block text-xs font-bold text-[#123B68]" for="fill-answer">Đáp án của bạn <span class="text-rose-500">*</span></label>
                                                        <input id="fill-answer" type="text" name="text" required maxlength="500" placeholder="Nhập đáp án"
                                                               class="w-full rounded-lg bg-white px-3 py-3 text-sm text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                                                        <p class="text-[10px] leading-5 text-[#607A90]">Hệ thống sẽ chuẩn hóa khoảng trắng thừa khi chấm.</p>
                                                    </div>
                                                @else
                                                    <div class="space-y-2">
                                                        @foreach ($compositeParts as $part)
                                                            <div class="rounded-lg bg-white p-3">
                                                                <p class="mb-2 text-[11px] font-bold text-[#123B68]">Phần {{ strtoupper($part['code']) }} <span class="font-normal text-[#7A92A3]">({{ $part['points'] }} điểm)</span></p>
                                                                @if ($part['responseType'] === 'single_choice')
                                                                    <div class="flex flex-wrap gap-2">
                                                                        @foreach ($part['choices'] as $choice)
                                                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-semibold text-[#45657D] ring-1 ring-inset ring-[#DDEAF0] has-[:checked]:bg-[#EAF5F8] has-[:checked]:text-[#126F91]">
                                                                                <input type="radio" name="parts[{{ $part['code'] }}]" value="{{ $choice }}" required class="h-3.5 w-3.5 accent-[#126F91]"> {{ $choice }}
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                @elseif ($part['responseType'] === 'true_false')
                                                                    <div class="flex gap-2">
                                                                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-semibold text-[#45657D] ring-1 ring-inset ring-[#DDEAF0] has-[:checked]:bg-[#EAF5F8] has-[:checked]:text-[#126F91]">
                                                                            <input type="radio" name="parts[{{ $part['code'] }}]" value="true" required class="h-3.5 w-3.5 accent-[#126F91]"> Đúng
                                                                        </label>
                                                                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg px-3 py-1.5 text-[11px] font-semibold text-[#45657D] ring-1 ring-inset ring-[#DDEAF0] has-[:checked]:bg-[#EAF5F8] has-[:checked]:text-[#126F91]">
                                                                            <input type="radio" name="parts[{{ $part['code'] }}]" value="false" required class="h-3.5 w-3.5 accent-[#126F91]"> Sai
                                                                        </label>
                                                                    </div>
                                                                @elseif ($part['responseType'] === 'short_answer')
                                                                    <input type="text" name="parts[{{ $part['code'] }}]" maxlength="500" placeholder="Nhập đáp án"
                                                                           class="w-full rounded-lg bg-[#F8FBFC] px-3 py-2.5 text-xs text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                                                                @else
                                                                    {{-- 'essay' hoặc dạng lạ chưa hỗ trợ — chỉ ghi nhận. --}}
                                                                    <textarea name="parts[{{ $part['code'] }}]" rows="4" maxlength="5000" placeholder="Viết câu trả lời của bạn…"
                                                                              class="w-full rounded-lg bg-[#F8FBFC] px-3 py-2.5 text-xs text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]"></textarea>
                                                                    <p class="mt-1 text-[10px] text-[#7A92A3]">Phần tự luận chưa có chấm tự động — chỉ được ghi nhận.</p>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </section>

                                        <div class="flex shrink-0 flex-col items-end gap-2 rounded-xl border border-[#DDEAF0] bg-white p-2.5">
                                            <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg bg-[#126F91] px-4 py-2 text-[11px] font-bold text-white shadow-sm transition hover:bg-[#0F5E7B]">
                                                <x-lucide name="send" class="h-3.5 w-3.5" />Kiểm tra đáp án
                                            </button>
                                            <p data-ajax-error class="hidden text-[11px] text-[#B42318]"></p>
                                        </div>
                                    </div>
                                @endif
                            </form>
                        @endif
                        </div>
                    </div>
                </section>

                {{-- ───────── TAB: HƯỚNG DẪN ───────── --}}
                <section x-show="tab === 'guide'" x-cloak class="h-full min-h-0 overflow-y-auto p-2 sm:p-3">
                    <article class="min-h-full rounded-xl bg-white p-4 sm:p-6">
                        <h3 class="text-sm font-extrabold text-[#123B68]">Hướng dẫn làm bài</h3>
                        <ul class="mt-3 space-y-2 text-[13px] leading-7 text-[#45657D]">
                            <li>· Đọc đề ở cột trái (hoặc tab <span class="font-bold">Đề bài PDF</span> cho dễ nhìn), trả lời ở cột phải.</li>
                            <li>· Bấm <span class="font-bold">Nộp bài</span> để chấm — trang không tải lại, kết quả hiện ngay tại chỗ.</li>
                            <li>· Câu lập trình được chấm bằng máy chấm thật; mỗi test đúng/sai đều hiện ra, test sai bấm vào xem chi tiết và tải về được.</li>
                            <li>· Chấm xong bấm <span class="font-bold">Hoàn tất bài tập</span> để kết thúc phiên luyện.</li>
                        </ul>
                        <p class="mt-4 text-[11px] leading-6 text-[#607A90]">Gợi ý riêng cho từng bài chưa được nhập vào hệ thống — khi kho câu hỏi có trường hướng dẫn, phần này sẽ hiện đúng nội dung của bài đang làm.</p>
                    </article>
                </section>

                {{-- ───────── TAB: BÀI MẪU ───────── --}}
                <section x-show="tab === 'sample'" x-cloak class="assessment-sample-panel h-full min-h-0 overflow-y-auto p-2 sm:p-3">
                    <article class="grid min-h-full place-items-center rounded-xl bg-white p-6 text-center">
                        <div>
                            <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-[#EAF5F8] text-[#126F91]"><x-lucide name="book-open" class="h-5 w-5" /></span>
                            <p class="mt-3 text-sm font-extrabold text-[#123B68]">Đáp án hiện sau khi chấm</p>
                            {{-- Không dựng thêm nguồn "bài mẫu" nào: đáp án đúng đã được khối kết quả
                                 cũ in ra ngay tại tab "Làm bài" sau khi bấm nộp. Bày ra ở đây trước
                                 khi làm là đưa luôn đáp án cho học sinh. --}}
                            <p class="mx-auto mt-1 max-w-sm text-[11px] leading-6 text-[#607A90]">Bấm Nộp bài xong, đáp án đúng và kết quả từng test sẽ hiện ngay ở tab <span class="font-bold">Làm bài</span>.</p>
                        </div>
                    </article>
                </section>
            </main>
        </div>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content p { margin-bottom: 0.5rem; }
    </style>

    {{-- SỬA 3/9 (khách yêu cầu nút loading xoay xoay lúc chấm) — CSS thường (không dùng class
         Tailwind), cùng lý do đã giải thích ở by-question-play.blade.php: màu trắng-trên-nền-
         rose-600 cho spinner này chưa có sẵn trong CSS đã build (public/build/assets/*.css là
         build JIT theo class thực tế đang dùng), CSS thường luôn hoạt động ngay không cần build
         lại gì. --}}
    <style>
        .oi-btn-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            margin-right: 6px;
            vertical-align: -3px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: oi-btn-spin 0.7s linear infinite;
        }
        @keyframes oi-btn-spin {
            to { transform: rotate(360deg); }
        }
    </style>

    {{-- SỬA 18/9 — GỠ 4 tệp CDN CodeMirror + style .CodeMirror: ô soạn mã giờ là trình soạn
         của bản mẫu (textarea trong suốt + lớp tô màu), không còn thẻ [data-code-editor] nào
         nên CodeMirror chỉ là ~200KB tải về rồi nằm không. initCodeEditor() bên dưới GIỮ
         NGUYÊN và tự thoát ở dòng đầu (không có textarea, và typeof CodeMirror === 'undefined'),
         nên script chấm bài cũ chạy y hệt. --}}
    <script>
        // SỬA 3/9 — tách hàm init CodeMirror ra tên riêng (initCodeEditor) để gọi LẠI được sau
        // mỗi lần thay #practice-container bằng AJAX (xem submit handler bên dưới) — cùng cách
        // đã làm ở by-question-play.blade.php (dù trang "Làm bài" 1 bài tập này không có khái
        // niệm "câu tiếp theo", vẫn cần init lại nếu học sinh bấm nộp mà kết quả trả về vẫn còn
        // hiện lại form — vd form composite/coding submit lỗi validate phía Judge0/mạng).
        function initCodeEditor() {
            var textarea = document.querySelector('textarea[data-code-editor]');
            if (!textarea || typeof CodeMirror === 'undefined') return;

            var langSelect = document.querySelector('select[data-code-language]');
            var MODE_MAP = {
                cpp: 'text/x-c++src',
                c: 'text/x-csrc',
                java: 'text/x-java',
                python: 'text/x-python',
                csharp: 'text/x-csharp',
            };

            var editor = CodeMirror.fromTextArea(textarea, {
                mode: MODE_MAP[langSelect ? langSelect.value : 'cpp'] || 'text/x-c++src',
                theme: 'monokai',
                lineNumbers: true,
                indentUnit: 4,
                tabSize: 4,
                lineWrapping: false,
                viewportMargin: Infinity,
                placeholder: 'Viết code ở đây...',
            });

            if (langSelect) {
                langSelect.addEventListener('change', function () {
                    editor.setOption('mode', MODE_MAP[langSelect.value] || 'text/plain');
                });
            }

            var form = textarea.closest('form');
            if (form) {
                // Gắn editor lên chính form để submit handler AJAX bên dưới gọi save() (đồng
                // bộ nội dung đang gõ ngược lại textarea gốc) TRƯỚC KHI fetch() gửi đi — thay
                // cho cách cũ nghe sự kiện 'submit' thật (giờ submit thật đã bị preventDefault).
                form.__codeEditor = editor;
            }
        }

        document.addEventListener('DOMContentLoaded', initCodeEditor);

        // SỬA 3/9 (khách yêu cầu: bấm chấm bài KHÔNG reload cả trang, chỉ hiện spinner ở nút rồi
        // hiện kết quả tại chỗ) — nghe sự kiện submit kiểu delegation trên toàn trang (không gắn
        // trực tiếp vào 1 form cố định) vì form thật sự tồn tại lúc chạy đoạn script này có thể
        // bị THAY MỚI hoàn toàn sau mỗi lần nộp bài (xem replaceWith() bên dưới) — gắn listener
        // kiểu delegation thì luôn bắt được form MỚI mà không cần gắn lại tay.
        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax-answer]')) return;

            event.preventDefault();

            if (form.__codeEditor) {
                form.__codeEditor.save();
            }

            var button = form.querySelector('button[type="submit"]');
            var errorEl = form.querySelector('[data-ajax-error]');
            var originalButtonHtml = button ? button.innerHTML : '';
            if (errorEl) errorEl.classList.add('hidden');
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="oi-btn-spinner"></span>Đang chấm...';
            }

            fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(function (response) {
                    // fetch() tự đi theo redirect (Laravel vẫn redirect sang GET .play như cũ,
                    // KHÔNG đổi controller/route nào) — response.ok ở đây là của trang .play sau
                    // redirect, không phải của route .answer.
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.text();
                })
                .then(function (html) {
                    var newContainer = new DOMParser().parseFromString(html, 'text/html')
                        .querySelector('#practice-container');
                    var oldContainer = document.querySelector('#practice-container');

                    if (!newContainer || !oldContainer) {
                        // Không thấy #practice-container trong HTML trả về — có thể phiên đăng
                        // nhập đã hết hạn (bị chuyển sang trang login) hoặc lỗi lạ khác. An toàn
                        // nhất là điều hướng thật để học sinh thấy đúng trạng thái thật sự, tránh
                        // hiện trang trống/kẹt spinner mãi.
                        window.location.reload();
                        return;
                    }

                    oldContainer.replaceWith(newContainer);
                    initCodeEditor();
                })
                .catch(function () {
                    if (button) {
                        button.disabled = false;
                        button.innerHTML = originalButtonHtml;
                    }
                    if (errorEl) {
                        errorEl.textContent = 'Không gửi được bài làm — kiểm tra lại kết nối mạng rồi thử lại.';
                        errorEl.classList.remove('hidden');
                    }
                });
        });

        // SỬA 3/9 (2, khách yêu cầu: bấm mở rộng xem chi tiết test sai + tải file test sai) —
        // delegation trên toàn trang (giống submit handler trên) vì các nút này nằm TRONG
        // #practice-container, có thể bị thay mới sau mỗi lần AJAX — gắn 1 lần ở document là đủ,
        // luôn bắt được nút mới mà không cần gọi lại hàm init nào khác. Cùng logic hệt
        // by-question-play.blade.php.
        document.addEventListener('click', function (event) {
            var toggle = event.target.closest('[data-test-case-toggle]');
            if (toggle) {
                var row = toggle.closest('[data-test-case-row]');
                var detail = row ? row.querySelector('[data-test-case-detail]') : null;
                var arrow = toggle.querySelector('[data-test-case-arrow]');
                if (detail) {
                    var willShow = detail.classList.contains('hidden');
                    detail.classList.toggle('hidden');
                    if (arrow) arrow.textContent = willShow ? '▴' : '▾';
                }
                return;
            }

            var downloadBtn = event.target.closest('[data-download-failed-tests]');
            if (downloadBtn) {
                var tests = [];
                try {
                    tests = JSON.parse(downloadBtn.getAttribute('data-tests') || '[]');
                } catch (e) {
                    tests = [];
                }

                var lines = [];
                tests.forEach(function (t) {
                    lines.push('=== Test ' + t.index + ' (' + t.statusLabel + ') ===');
                    lines.push('--- Dữ liệu vào ---');
                    lines.push(t.input !== '' ? t.input : '(rỗng)');
                    lines.push('--- Kết quả mong đợi ---');
                    lines.push(String(t.expectedOutput));
                    lines.push('--- Chương trình của bạn in ra ---');
                    lines.push(t.actualOutput ? t.actualOutput : '(không có gì)');
                    if (t.compileOutput || t.stderr) {
                        lines.push('--- Lỗi ---');
                        lines.push(((t.compileOutput || '') + '\n' + (t.stderr || '')).trim());
                    }
                    lines.push('');
                });

                var blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'test-sai-cau-' + (downloadBtn.getAttribute('data-question-id') || 'x') + '.txt';
                document.body.appendChild(a);
                a.click();
                a.remove();
                URL.revokeObjectURL(url);
            }
        });
    </script>
@endpush

@push('scripts')
    <script>
        // SỬA 18/9 — nút chính trên header bấm hộ nút submit đang nằm trong #practice-container.
        // Khối đó bị thay mới sau mỗi lần chấm (AJAX) nên KHÔNG trỏ cứng vào form được: tra DOM
        // ngay lúc bấm, và dùng MutationObserver để đổi nhãn nút cho khớp trạng thái hiện tại.
        // Đoạn này ĐỘC LẬP hoàn toàn với script chấm bài cũ — không sửa một dòng nào của nó.
        (function () {
            var headerBtn = document.querySelector('[data-header-submit]');
            var labelEl = document.querySelector('[data-header-submit-label]');
            if (!headerBtn || !labelEl) return;

            function panelButton() {
                var container = document.querySelector('#practice-container');
                return container ? container.querySelector('form button[type="submit"]') : null;
            }

            function sync() {
                var container = document.querySelector('#practice-container');
                var answering = container ? container.querySelector('form[data-ajax-answer]') : null;
                var target = panelButton();
                headerBtn.disabled = target === null;
                labelEl.textContent = answering ? 'Nộp bài' : (target ? 'Hoàn tất bài tập' : 'Đã xong');
            }

            headerBtn.addEventListener('click', function () {
                var target = panelButton();
                if (target) target.click();
            });

            document.addEventListener('DOMContentLoaded', sync);
            sync();

            // #practice-container bị replaceWith() nên phải theo dõi CHA của nó, không phải nó.
            var host = document.querySelector('#practice-container');
            if (host && host.parentNode) {
                new MutationObserver(sync).observe(host.parentNode, { childList: true, subtree: true });
            }
        })();
    </script>
@endpush


@push('scripts')
    @include('partials.pdf-fit-viewer')

    {{-- Bộ tô màu cú pháp + mã khởi tạo, dùng chung với phòng thi. --}}
    @include('partials.code-editor-runtime')

    <script>
        // SỬA 18/9 — nối trình soạn mã kiểu bản mẫu (textarea trong suốt chồng lên lớp <pre> tô
        // màu) cho màn luyện 1 bài. KHÔNG dùng CodeMirror nữa để đúng giao diện bản mẫu; textarea
        // name="code_source" giờ là ô nhập THẬT nên form gửi thẳng giá trị đang gõ — script chấm
        // bài cũ không phải đổi gì: initCodeEditor() không tìm thấy [data-code-editor] nên tự
        // thoát, form.__codeEditor để trống và handler bỏ qua bước save() như thiết kế sẵn của nó.
        (function () {
            function wire() {
                var ta = document.querySelector('[data-code-source]');
                var pre = document.querySelector('[data-code-highlight]');
                if (!ta || !pre || ta.dataset.wired === '1') return;
                ta.dataset.wired = '1';

                var form = ta.closest('form');
                var langSelect = form ? form.querySelector('select[name="language"]') : null;
                var lang = function () { return langSelect ? langSelect.value : 'cpp'; };

                if (ta.value === '') ta.value = STARTER_CODE[lang()] || STARTER_CODE.cpp;

                // SỬA 18/9 — bảng màu phải BÁM theo nền đang bật, trước đây ghi cứng 'light'
                // nên bật nền tối là chữ sáng trên ô tối, đọc không ra.
                function themeNow() { return document.documentElement.classList.contains('theme-dark') ? 'dark' : 'light'; }
                function paint() { pre.innerHTML = '<code>' + highlightCode(ta.value, lang(), themeNow()) + '</code>'; }
                function sync() { pre.scrollTop = ta.scrollTop; pre.scrollLeft = ta.scrollLeft; }

                ta.addEventListener('input', paint);
                ta.addEventListener('scroll', sync);

                if (langSelect) {
                    langSelect.addEventListener('change', function () {
                        // Chỉ thay mã mẫu khi học sinh CHƯA viết gì khác — không xoá bài đang viết dở.
                        var cur = ta.value.trim();
                        if (cur === '' || cur === String(STARTER_CODE.cpp).trim() || cur === String(STARTER_CODE.python).trim()) {
                            ta.value = STARTER_CODE[lang()] || STARTER_CODE.cpp;
                        }
                        paint();
                    });
                }

                var reset = form ? form.querySelector('[data-code-reset]') : null;
                if (reset) reset.addEventListener('click', function () {
                    ta.value = STARTER_CODE[lang()] || STARTER_CODE.cpp;
                    paint();
                });

                var file = form ? form.querySelector('[data-code-file]') : null;
                if (file) file.addEventListener('change', function (event) {
                    var f = event.target.files && event.target.files[0];
                    if (!f) return;
                    if (/\.py$/i.test(f.name) && langSelect) langSelect.value = 'python';
                    f.text().then(function (content) { ta.value = content; paint(); });
                    event.target.value = '';
                });

                paint();

                // Bấm nút đổi nền sáng/tối -> tô lại ngay, không phải tải lại trang.
                new MutationObserver(paint).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            }

            document.addEventListener('DOMContentLoaded', wire);
            wire();

            // #practice-container bị thay mới sau mỗi lần chấm -> nối lại cho ô mã mới.
            var host = document.querySelector('#practice-container');
            if (host && host.parentNode) new MutationObserver(wire).observe(host.parentNode, { childList: true, subtree: true });
        })();
    </script>

    {{-- ══════ SỬA 18/9 — NÚT "CHẠY TEST" (khách: "chỗ chạy test không được") ══════
         Gửi mã + dữ liệu vào ô Input lên student.practiceByQuestion.run, in stdout ra ô Output.
         Chạy thử KHÔNG chấm điểm, không đụng tiến trình phiên luyện.

         Nghe click kiểu DELEGATION trên document (không gắn thẳng vào nút): #practice-container
         bị thay mới hoàn toàn sau mỗi lần chấm bài (xem script AJAX ở trên), gắn thẳng thì sau
         lần chấm đầu tiên nút mới sẽ không còn listener. Cùng lý do không dùng Alpine ở đây:
         Alpine 3 không tự khởi tạo DOM do JS chèn vào. --}}
    <script>
        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-run-test]') : null;
            if (!button || button.disabled) return;

            event.preventDefault();

            // 4 ô này luôn nằm cùng trong panel "Làm bài" -> tìm từ chính vùng chứa nút, không
            // querySelector toàn trang (tránh vớ nhầm nếu sau này có 2 khối cùng kiểu).
            var panel = button.closest('[data-work-panel]') || document;
            var codeEl = panel.querySelector('[data-code-source]');
            var langEl = panel.querySelector('select[name="language"]');
            var inputEl = panel.querySelector('[data-run-input]');
            var outputEl = panel.querySelector('[data-run-output]');
            var statusEl = panel.querySelector('[data-run-status]');
            var labelEl = button.querySelector('[data-run-test-label]');

            if (!outputEl) return;

            var meta = document.querySelector('meta[name="csrf-token"]');
            var body = new FormData();
            body.append('_token', meta ? meta.getAttribute('content') : '');
            body.append('code_source', codeEl ? codeEl.value : '');
            body.append('language', langEl ? langEl.value : 'cpp');
            body.append('stdin', inputEl ? inputEl.value : '');

            button.disabled = true;
            if (labelEl) labelEl.textContent = 'Đang chạy…';
            if (statusEl) statusEl.textContent = '';
            outputEl.textContent = 'Đang chạy…';

            fetch(button.getAttribute('data-run-url'), {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            })
                .then(function (response) {
                    // SỬA 18/9 (2) — KHÔNG gọi thẳng response.json(). Máy chủ trả 404 (route chưa
                    // nạp lại sau khi cập nhật mã), 419 (phiên hết hạn) hay 500 đều là HTML, khi
                    // đó response.json() ném lỗi và rơi xuống catch() -> báo "lỗi mạng" SAI SỰ
                    // THẬT, người dùng đi kiểm tra wifi trong khi lỗi nằm ở máy chủ. Đọc mã HTTP
                    // trước để nói đúng chuyện gì đã xảy ra.
                    if (!response.ok) throw new Error('HTTP ' + response.status);
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) {
                        outputEl.textContent = (data && data.message) ? data.message : 'Chạy thử thất bại.';
                        if (statusEl) statusEl.textContent = 'Không chạy được';
                        return;
                    }

                    // Ưu tiên hiện lỗi biên dịch/lỗi chạy — đó mới là cái học sinh cần đọc;
                    // stdout rỗng mà không nói gì thì người dùng tưởng nút hỏng.
                    var text = '';
                    if (data.compileOutput) text += 'Lỗi biên dịch:\n' + data.compileOutput + '\n';
                    if (data.stderr) text += 'Lỗi khi chạy:\n' + data.stderr + '\n';
                    if (data.output) text += data.output;
                    outputEl.textContent = text !== '' ? text : '(chương trình không in ra gì)';

                    if (statusEl) {
                        var parts = [data.statusLabel || ''];
                        if (data.time) parts.push(data.time + 's');
                        if (data.memory) parts.push(Math.round(data.memory / 1024) + 'MB');
                        statusEl.textContent = parts.filter(Boolean).join(' · ');
                    }
                })
                .catch(function (error) {
                    var reason = String((error && error.message) || '');

                    if (reason === 'HTTP 419') {
                        outputEl.textContent = 'Phiên làm việc đã hết hạn — tải lại trang rồi bấm chạy lại (bài đang gõ sẽ mất, nhớ chép mã ra trước).';
                    } else if (reason === 'HTTP 404') {
                        outputEl.textContent = 'Máy chủ chưa nhận ra chức năng chạy thử (404) — báo quản trị viên nạp lại máy chủ sau khi cập nhật mã.';
                    } else if (reason.indexOf('HTTP ') === 0) {
                        outputEl.textContent = 'Máy chủ báo lỗi (' + reason + ') — báo quản trị viên xem storage/logs/laravel.log.';
                    } else {
                        // Chỉ ĐẾN ĐÂY mới thật sự là không gửi đi được (mất mạng, server sập hẳn).
                        outputEl.textContent = 'Không gửi được yêu cầu chạy thử — kiểm tra kết nối mạng rồi thử lại.';
                    }

                    if (statusEl) statusEl.textContent = reason !== '' ? reason : 'Không gửi được';
                })
                .finally(function () {
                    button.disabled = false;
                    if (labelEl) labelEl.textContent = 'Chạy test';
                });
        });
    </script>
@endpush
