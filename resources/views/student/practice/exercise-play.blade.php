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
        // SỬA 18/9 (2) — KHÔNG có $question thì coi như đã xong. Bình thường playData() luôn
        // trả một trong hai hình (đang làm: có $question / đã xong: không có), nên nhánh này
        // không bao giờ chạy — để đây làm van an toàn: thiếu dữ liệu thì hiện màn "đã hoàn tất"
        // chứ tuyệt đối không vỡ trang 500 giữa lúc học sinh đang làm bài.
        $question = $question ?? null;
        $finished = $finished ?? ($question === null);
        $feedback = $feedback ?? null;
        $options = $options ?? [];
        $compositeParts = $compositeParts ?? [];
        $assets = $assets ?? [];
        // SỬA 1/10 (khách: "tab Hướng dẫn thì lấy đổ dữ liệu field lời giải ra có sẵn trong
        // database rồi, tab bài mẫu thì lấy ở field code mẫu ra") — 2 khoá do
        // PracticeByQuestionService::playData() cấp (null khi câu không có tệp, hoặc khi là bài
        // tập trả phí của sản phẩm — xem canSeeGuideDocs()). Màn "đã hoàn tất" không có 2 khoá
        // này nên phải ?? null, nếu không là lỗi biến chưa định nghĩa.
        $guideDoc = $guideDoc ?? null;
        $sampleDoc = $sampleDoc ?? null;
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

        /*
         * SỬA 18/9 (2) — LỖI 500 THẬT (khách vừa gặp lúc 16:20, xem laravel.log: "Undefined
         * variable $question"): thanh tiêu đề LUÔN hiển thị, kể cả ở trạng thái ĐÃ HOÀN TẤT —
         * mà lúc đó PracticeByQuestionService::playData() KHÔNG trả $question nữa (nhánh
         * `$index >= $total` chỉ trả finished/total/correct/answered/...). Đọc thẳng
         * $question->code / ->title / ->points ở thanh tiêu đề là vỡ trang ngay khi học sinh
         * bấm "Hoàn tất bài tập".
         *
         * Rút sẵn 3 giá trị an toàn ở đây, thanh tiêu đề chỉ dùng chúng. Quy tắc chung cho tệp
         * này: MỌI thứ nằm ngoài `@if (! $finished)` không được đụng tới $question.
         */
        $headCode = $finished ? null : $question->code;
        $headPoints = $finished ? null : $question->points;
        $headTitle = $finished ? 'Đã hoàn tất bài tập' : $question->title;
    @endphp

    {{-- SỬA 18/9 (khách: "copy Assessment Modal á, khi click thì nó hiển thị modal vậy á") —
         lớp vỏ chép ĐÚNG 2 thẻ ngoài cùng của bản mẫu: nền tối phủ kín + khung bo góc thụt vào
         8px mỗi bên. Trang vẫn có URL riêng (bấm F5 hay nút Back của trình duyệt đều đúng,
         không mất bài đang làm) nhưng nhìn y hệt modal của bản mẫu. --}}
    <div class="assessment-modal fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-0 sm:p-2"
         x-data="{
             {{-- SỬA 30/9 (4) (khách: "mặc định tab hiển thị đầu tiên là tab đề bài") — bản mẫu
                  mở ở tab Đề bài (AssessmentModal.jsx: useState(\"pdf\")). Bài KHÔNG có bản PDF
                  thì vẫn mở thẳng Làm bài, chứ mở vào một tab trống thì vô duyên. --}}
             tab: '{{ $statementUrl ? 'pdf' : 'work' }}',
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
         }" x-init="init(); $watch('tab', function (value) { if (window.oiWorkLog) window.oiWorkLog.tabChanged(value); })">

        <div class="assessment-modal-shell flex h-full w-full max-w-none flex-col overflow-hidden bg-[#F8FBFC] shadow-2xl sm:h-[calc(100dvh-16px)] sm:max-w-[calc(100vw-16px)] sm:rounded-xl"
             data-activity-key="{{ $headCode ?: 'exercise' }}">

        {{-- ══════════════════════════ HEADER ══════════════════════════ --}}
        <header class="assessment-modal-header flex shrink-0 items-center gap-2 border-b border-[#DDEAF0] bg-white px-3 py-2 sm:px-4">
            <a href="{{ $backUrl }}" aria-label="{{ $backLabel }}" title="{{ $backLabel }}"
               class="rounded-xl p-2 text-[#607A90] transition hover:bg-[#F4F9FB]"><x-lucide name="x" class="h-5 w-5" /></a>

            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#126F91]">
                    @if ($headCode){{ $headCode }} · @endif
                    @if ($headPoints !== null){{ $headPoints }} điểm
                    @endif
                </p>
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <h2 class="min-w-0 truncate text-sm font-extrabold text-[#123B68] sm:text-base">{{ $headTitle }}</h2>
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

            {{-- SỬA 30/9 — đúng bản mẫu mới: thanh trên KHÔNG còn nút "Nộp bài" (đã chuyển
                 xuống thanh dưới cùng), thay vào đó là ĐỒNG HỒ + chip trạng thái lưu bài. --}}
            <div class="hidden shrink-0 items-center gap-2 rounded-xl bg-[#FFF5DE] px-3 py-2 text-[#A4621B] sm:flex"
                 title="Thời gian bạn đã mở bài này (tính từ lúc mở trang)">
                <x-lucide name="clock-3" class="h-4 w-4" />
                <span class="text-xs font-black" data-work-timer>00:00:00</span>
            </div>

            {{-- Bản mẫu để cứng chữ "Đã lưu bài". Ở đây CHỈ hiện sau khi đã nộp — lúc đó bài mới
                 thật sự được ghi vào lịch sử làm bài. Nói "đã lưu" khi chưa nộp là sai sự thật,
                 học sinh tin thế rồi đóng tab là mất mã đang gõ. --}}
            @if ($feedback !== null)
                <span class="hidden shrink-0 items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-[#607A90] sm:flex">
                    <x-lucide name="save" class="h-4 w-4" />Đã lưu bài
                </span>
            @endif
        </header>

        {{-- ═══════════════════ RAIL 4 TAB + NỘI DUNG ═══════════════════ --}}
        <div class="assessment-modal-main flex min-h-0 flex-1 flex-col md:flex-row">
            <aside class="assessment-modal-tabs shrink-0 border-b border-[#DDEAF0] bg-white md:w-12 md:border-b-0 md:border-r">
                {{-- SỬA 30/9 — bản mẫu mới có 5 tab: thêm "Nhật ký" (tín hiệu trong lúc làm bài). --}}
                <div class="grid h-full grid-cols-5 gap-1 p-1.5 md:flex md:flex-col md:gap-1 md:p-2">
                    @foreach ([['pdf', 'Đề bài PDF'], ['work', 'Làm bài'], ['guide', 'Hướng dẫn'], ['sample', 'Bài mẫu'], ['activity', 'Nhật ký']] as [$tabId, $tabLabel])
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
                {{-- SỬA 30/9 (7) (khách: "tab đề, hướng dẫn, bài mẫu hiển thị UI vừa vừa như này
                     thôi") — trang đề KHÔNG kéo căng hết bề ngang nữa mà nằm gọn giữa khung như
                     một tờ A4 đặt trên mặt bàn, đúng <A4Document> của bản mẫu: khung lõm màu
                     xanh nhạt ở ngoài, tờ giấy trắng rộng tối đa 820px canh giữa ở trong. --}}
                <section x-show="tab === 'pdf'" x-cloak class="h-full min-h-0 overflow-hidden p-1 sm:p-2">
                    <div class="assessment-pdf-surface h-full min-h-0 overflow-auto rounded-xl border border-[#DDEAF0] bg-[#EAF4F8] p-1.5 shadow-inner sm:p-3">
                        @if ($statementUrl)
                            <div data-pdf-fit data-pdf-url="{{ $statementUrl }}" data-pdf-max-width="820" class="oi-doc-col"></div>
                        @else
                            <div class="grid h-full place-items-center p-8 text-center">
                                <div>
                                    <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-white text-[#126F91]"><x-lucide name="file-text" class="h-5 w-5" /></span>
                                    <p class="mt-3 text-sm font-extrabold text-[#123B68]">Bài này không có bản PDF</p>
                                    <p class="mt-1 text-[11px] text-[#607A90]">Toàn bộ nội dung đề nằm ở tab <span class="font-bold">Làm bài</span>.</p>
                                </div>
                            </div>
                        @endif
                    </div>
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
                    </div>

                    {{--
                        SỬA 19/9 (7) (khách: "UI này scroll nó không xuống được hết") — BỎ
                        lg:overflow-hidden.

                        Lỗi cũ: trên màn rộng khung này bị khoá overflow-hidden, cố ý cho khu
                        soạn mã vừa khít chiều cao và các ô con tự cuộn riêng. Nhưng SAU KHI
                        CHẤM, khối "Kết quả từng test" được chèn THÊM ngay dưới khu soạn mã ->
                        cột nội dung cao hơn khung -> phần thừa bị CẮT và không có cách nào cuộn
                        tới: danh sách test cuối cùng và cả nút "Hoàn tất bài tập" đều nằm ngoài
                        vùng nhìn thấy.

                        Để overflow-y-auto ở mọi khổ màn hình: lúc chưa chấm nội dung vừa khít
                        nên không có thanh cuộn nào hiện ra (không đổi cảm giác cũ), lúc đã chấm
                        thì cuộn được xuống hết.
                    --}}
                    <div class="mt-2 min-h-0 flex-1 overflow-y-auto">
                        {{-- ⚠ TỪ ĐÂY TRỞ XUỐNG LÀ KHỐI BỊ THAY MỚI SAU MỖI LẦN CHẤM (AJAX).
                             Không đặt directive Alpine nào bên trong. --}}
                        <div id="practice-container">
                        @if ($finished)
                            {{-- SỬA 19/9 (7) (khách: "nộp bài phần lập trình xong nó không hiện tỉ lệ AC")
                                 — màn này trước đây chỉ có 🎉 và một câu chúc mừng, không một con số
                                 nào: học sinh nộp xong không biết mình đúng mấy câu, được bao nhiêu
                                 điểm. Mọi số bên dưới lấy từ attempt_answers ĐÃ LƯU
                                 (PracticeByQuestionService::finishedSummary()), cùng nguồn với Tỷ lệ
                                 AC ngoài trang Luyện tập nên hai chỗ không thể nói khác nhau. --}}
                            @php
                                $summary = $summary ?? null;
                                $total = $total ?? 0;
                                $correct = $correct ?? 0;
                                $answered = $answered ?? 0;
                                // Tỉ lệ hiển thị to nhất: ưu tiên THEO ĐIỂM (đúng bản chất bài lập
                                // trình — có bài 2 điểm, có bài 10 điểm); đề chưa đặt điểm thì lùi
                                // về tỉ lệ số câu đúng để ô này không bao giờ trống.
                                $hasPoints = ($summary['maxPoints'] ?? 0) > 0;
                                $mainPercent = $hasPoints
                                    ? $summary['scorePercent']
                                    : ($total > 0 ? (int) round($correct / $total * 100) : 0);
                                $allCorrect = $total > 0 && $correct === $total;

                                // Phiên chỉ có ĐÚNG MỘT câu lập trình (luồng "Làm bài" từ trang
                                // Luyện tập) — nói thẳng tỉ lệ TEST, vì đó mới là con số học sinh
                                // vừa nhìn thấy ở khối chấm, chứ không phải "0/2 điểm".
                                $soloTest = (count($summary['rows'] ?? []) === 1 && ($summary['rows'][0]['testPercent'] ?? null) !== null)
                                    ? $summary['rows'][0]
                                    : null;
                            @endphp

                            <div class="mx-auto mt-6 max-w-2xl space-y-3">
                                <div class="rounded-2xl border border-[#DDEAF0] bg-white p-6 text-center shadow-sm sm:p-8">
                                    <span @class([
                                        'mx-auto grid h-14 w-14 place-items-center rounded-full text-2xl',
                                        'bg-[#EAF5F8]' => ! $allCorrect,
                                        'bg-[#D4EDE2]' => $allCorrect,
                                    ])>{{ $allCorrect ? '🎉' : '📊' }}</span>
                                    <h2 class="mt-4 text-lg font-extrabold text-[#123B68]">Đã ghi nhận bài làm!</h2>

                                    <p @class([
                                        'mt-3 text-4xl font-black leading-none',
                                        'text-[#2F8A6B]' => $mainPercent >= 50,
                                        'text-[#2C6BB0]' => $mainPercent < 50,
                                    ])>{{ $mainPercent }}%</p>
                                    {{-- SỬA 24/9 (khách: "khi nộp bài trong luyện tập thì không cần hiển
                                         thị số câu 0/.. bỏ cái này đi") — phiên luyện tập chỉ có ĐÚNG MỘT
                                         câu, nên dòng "Đúng 0/1 câu" chẳng nói lên điều gì mà lại là thứ
                                         học sinh đọc thấy đầu tiên sau khi nộp. Bỏ hẳn; giữ tỉ lệ TEST và
                                         số điểm — hai con số thật sự có nghĩa với bài lập trình.

                                         Phiên NHIỀU câu (nếu sau này có) vẫn cần biết đúng mấy câu, nên
                                         dòng đó chỉ ẩn khi phiên đúng 1 câu. --}}
                                    <p class="mt-1 text-[12px] font-bold text-[#607A90]">
                                        @if ($soloTest !== null)
                                            Qua {{ $soloTest['passedTests'] }}/{{ $soloTest['totalTests'] }} test ({{ $soloTest['testPercent'] }}%)
                                        @elseif ($total > 1)
                                            Đúng {{ $correct }}/{{ $total }} câu
                                        @endif
                                        {{ $hasPoints ? trim(($soloTest !== null || $total > 1) ? ' · ' : '').$summary['earned'].'/'.$summary['maxPoints'].' điểm' : '' }}
                                    </p>

                                    <div class="mx-auto mt-3 h-2 w-full max-w-sm overflow-hidden rounded-full bg-[#EAF0F3]">
                                        <div @class([
                                            'h-full rounded-full transition-all',
                                            'bg-[#2F8A6B]' => $mainPercent >= 50,
                                            'bg-[#4C87CE]' => $mainPercent < 50,
                                        ]) style="width: {{ $mainPercent }}%"></div>
                                    </div>

                                    {{-- Cũng vì lý do trên: phiên 1 câu mà báo "Còn 1 câu bạn chưa trả
                                         lời" thì thừa — đã bỏ trống thì tỉ lệ 0% ở trên nói rõ rồi. --}}
                                    @if ($total > 1 && $answered < $total)
                                        <p class="mt-3 text-[11px] font-bold text-[#A4621B]">Còn {{ $total - $answered }} câu bạn chưa trả lời.</p>
                                    @endif

                                    <a href="{{ $backUrl }}" class="mt-5 inline-flex items-center gap-1.5 rounded-xl bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77]">‹ {{ $backLabel }}</a>
                                </div>

                                @if (! empty($summary['rows']))
                                    <div class="overflow-hidden rounded-2xl border border-[#DDEAF0] bg-white shadow-sm">
                                        <p class="border-b border-[#E7EFF3] bg-[#F4F8FB] px-4 py-2 text-[10px] font-black uppercase tracking-[.12em] text-[#365B7A]">Kết quả từng câu</p>
                                        <div class="divide-y divide-[#EEF3F6]">
                                            @foreach ($summary['rows'] as $r)
                                                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                                                    <span class="inline-flex min-w-0 items-center gap-2">
                                                        {{-- 3 trạng thái: đúng / sai / chưa trả lời — câu bỏ trống
                                                             phải khác hẳn câu làm sai, nếu không học sinh tưởng mình
                                                             đã làm hết mà sai. --}}
                                                        <span @class([
                                                            'grid h-5 w-5 shrink-0 place-items-center rounded-full',
                                                            'bg-[#D4EDE2] text-[#2F8A6B]' => $r['isAccepted'],
                                                            'bg-[#DCE8F8] text-[#2C6BB0]' => ! $r['isAccepted'] && $r['answered'],
                                                            'bg-[#EAF0F3] text-[#8AA0B0]' => ! $r['answered'],
                                                        ])><x-lucide :name="! $r['answered'] ? 'minus' : ($r['isAccepted'] ? 'check' : 'x')" class="h-3 w-3" /></span>
                                                        <span class="min-w-0">
                                                            <span class="block truncate text-[12px] font-bold text-[#123B68]">{{ $r['title'] }}</span>
                                                            <span class="block truncate text-[10px] font-semibold text-[#8AA0B0]">{{ $r['code'] }} · {{ $r['verdictLabel'] }}</span>
                                                            {{-- SỬA 19/9 (8) — TỈ LỆ AC của câu lập trình. Chỉ hiện khi có số
                                                                 liệu thật: null (câu không phải lập trình, bài nộp cũ, hoặc
                                                                 máy chủ chưa migrate) thì ẩn hẳn, không hiện "0/0 test". --}}
                                                            @if ($r['testPercent'] !== null)
                                                                <span class="mt-1 flex items-center gap-1.5">
                                                                    <span class="h-1.5 w-16 overflow-hidden rounded-full bg-[#EAF0F3]">
                                                                        <span class="block h-full rounded-full {{ $r['testPercent'] === 100 ? 'bg-[#2F8A6B]' : 'bg-[#4C87CE]' }}" style="width: {{ $r['testPercent'] }}%"></span>
                                                                    </span>
                                                                    <span class="whitespace-nowrap text-[10px] font-bold text-[#607A90]">{{ $r['passedTests'] }}/{{ $r['totalTests'] }} test · {{ $r['testPercent'] }}%</span>
                                                                </span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                    <span @class([
                                                        'shrink-0 text-[12px] font-black',
                                                        'text-[#2F8A6B]' => $r['isAccepted'],
                                                        'text-[#8AA0B0]' => ! $r['isAccepted'],
                                                    ])>{{ $r['score'] }}/{{ $r['points'] }} điểm</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        {{-- SỬA 18/9 (2) (khách: "kiểm tra đáp án xong không cần hiển thị ra màn
                             này mà hiển thị kết quả bên chỗ modal luôn") — ĐÃ BỎ nhánh "màn kết quả
                             riêng chiếm trọn khung" ở đây. Mọi dạng câu giờ giữ nguyên panel trả lời
                             và hiện kết quả TẠI CHỖ:
                               · Lập trình        -> partials.practice-coding-result (CỘT PHẢI khu soạn mã)
                               · 3 dạng còn lại   -> partials.practice-answer-review (trong panel trả lời)
                             Nhờ vậy không còn cảm giác bị nhảy sang một trang khác sau mỗi lần chấm. --}}
                        @else
                            <form method="POST" action="{{ route('student.practiceByQuestion.answer') }}" class="min-h-full" data-ajax-answer>
                                @csrf
                                @if ($isCode)
                                  <div class="oi-work-fill gap-2">
                                    {{-- Lưới 2 cột ĐÚNG như WorkPanel của bản mẫu mới:
                                         [trình soạn mã 1.6fr | kết quả chấm 0.9fr]. --}}
                                    <div class="oi-work-grid grid gap-2 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,0.9fr)]">
                                        {{-- ── Trình soạn mã (CodeEditorPanel của bản mẫu) ── --}}
                                        <section class="flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-xl bg-[#F4F9FB]">
                                            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-white px-3 py-2.5 text-[#123B68] sm:px-4">
                                                {{-- Chỉ 2 ngôn ngữ vì máy chấm CHỈ nhận 2 (config/judge0.php) — bày
                                                     thêm là hứa cái hệ thống không chấm được. Giá trị gửi lên giữ
                                                     nguyên 'cpp'/'python' như cũ. --}}
                                                <select name="language" data-code-language aria-label="Chọn ngôn ngữ lập trình"
                                                        class="rounded-lg bg-[#F4F9FB] px-2 py-1.5 text-[10px] font-bold text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                                                    <option value="cpp" @selected(($feedback['yourLanguage'] ?? 'cpp') === 'cpp')>C++17</option>
                                                    <option value="python" @selected(($feedback['yourLanguage'] ?? null) === 'python')>Python 3</option>
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
                                                {{-- SỬA 18/9 — đổ lại ĐÚNG mã vừa nộp sau khi chấm, để học sinh sửa
                                                     tiếp chứ không phải gõ lại từ đầu. Script tô màu chỉ tự điền mã
                                                     mẫu khi ô đang RỖNG (xem `if (ta.value === '')` ở cuối tệp) nên
                                                     không đè lên bài của học sinh. --}}
                                                <textarea name="code_source" data-code-source spellcheck="false"
                                                          aria-label="Trình soạn mã có tô màu cú pháp"
                                                          title="Tab thụt dòng · Shift+Tab lùi · Enter tự giữ mức thụt · Ctrl+/ chú thích · Alt+↑↓ đẩy dòng · Shift+Alt+↑↓ nhân đôi dòng · Esc rồi Tab để nhảy ô"
                                                          style="color: transparent; -webkit-text-fill-color: transparent;"
                                                          class="absolute inset-0 z-10 h-full w-full resize-none overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6 outline-none selection:bg-[#2F8A6B]/40">{{ $feedback['yourCode'] ?? '' }}</textarea>
                                            </div>
                                        </section>

                                        {{-- ── CỘT KẾT QUẢ CHẤM (JudgingResultPanel) ──
                                             SỬA 30/9 (khách: "giờ họ không muốn để phần chạy test vô, họ có
                                             update lại UI modal trên source mới") — bản mẫu mới
                                             (education-main/src/components/AssessmentModal.jsx, hàm WorkPanel) đã
                                             ĐỔI cột phải của khu soạn mã: trước là TestInputPanel + TestOutputPanel
                                             (ô Input tự gõ + ô Output + nút "Chạy test"), giờ là JudgingResultPanel
                                             — chỗ này chỉ còn KẾT QUẢ CHẤM.

                                             Vì vậy ở đây đã BỎ HẲN: ô Input, ô Output, nút "Chạy test" và đoạn
                                             script gọi student.practiceByQuestion.run. Route + service chạy thử vẫn
                                             còn nguyên trong mã nguồn (không xoá gì của phần logic), chỉ là giao
                                             diện không còn lối vào — khách cần bật lại thì mở lại đúng khối này.

                                             Khối kết quả CHI TIẾT vẫn là partials.practice-coding-result như cũ
                                             (lỗi biên dịch kèm số dòng, cảnh báo freopen, dải ô test, bấm test sai
                                             xem dữ liệu vào/mong đợi/bạn in ra, tải test sai) — chỉ chuyển từ
                                             "nằm dưới khu soạn mã" sang "nằm ở cột phải" cho khớp bản mẫu. --}}
                                        <div class="flex min-h-[420px] min-w-0 flex-col gap-2 overflow-hidden">
                                            {{-- SỬA 1/10 (khách: "nộp bài hoặc chấm lại thì đừng hiện popup xoay xoay
                                                 nữa, hiển thị quá trình đang chấm ngay bên cột kết quả") — data-result-slot
                                                 là CÁI Ô để script thay nội dung lúc đang chấm. Chấm xong thì cả
                                                 #practice-container bị thay mới nên ô này tự mang kết quả thật. --}}
                                            <div data-result-slot class="oi-result-fill min-h-0 flex-1 overflow-hidden">
                                            @if ($feedback !== null)
                                                @include('partials.practice-coding-result')
                                            @else
                                                <div class="h-full overflow-y-auto rounded-xl border border-[#DDEAF0] bg-white px-3 py-3">
                                                    <p class="text-[11px] font-bold text-[#45657D]">Kết quả chấm sẽ hiển thị sau khi nộp bài.</p>
                                                    <p class="mt-2 text-[11px] leading-5 text-[#7A92A3]">
                                                        Viết mã ở cột bên trái rồi bấm <span class="font-bold text-[#126F91]">Nộp bài</span>
                                                        ở thanh dưới cùng. Máy chấm chạy toàn bộ test của bài và trả về
                                                        từng test đúng/sai ngay tại đây.
                                                    </p>
                                                </div>
                                            @endif
                                            </div>

                                            {{-- Nút nộp THẬT của form: ẩn đi nhưng PHẢI còn trong DOM — nút "Nộp bài"
                                                 trên thanh header hoạt động bằng cách bấm hộ nút này (xem panelButton()
                                                 ở script cuối trang), và trình duyệt cũng cần một nút submit để bắt
                                                 phím Enter. Nhãn của nó chính là nhãn mà nút header đồng bộ theo. --}}
                                            <div class="shrink-0">
                                                <button type="submit" class="hidden" tabindex="-1" aria-hidden="true">{{ $feedback !== null ? 'Chấm lại' : 'Nộp bài' }}</button>
                                                <p data-ajax-error class="hidden text-center text-[11px] text-[#B42318]"></p>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- SỬA 30/9 (2) (khách khoanh đỏ: "hiển thị bên cạnh là ok rồi") — ĐÃ BỎ bản
                                         kết quả thứ hai từng nằm ở ĐÂY, dưới khu soạn mã. Lần trước chuyển khối kết
                                         quả sang cột phải nhưng quên xoá bản cũ, thành ra chấm xong hiện y hệt 2 lần.
                                         Kết quả giờ CHỈ nằm ở cột phải — xem khối "CỘT KẾT QUẢ CHẤM" phía trên. --}}
                                  </div>
                                @else
                                    <div class="oi-work-fill gap-2">
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

                                                @if ($feedback !== null)
                                                    {{-- ĐÃ CHẤM — khoá ô nhập, hiện đáp án đúng/sai tại chỗ. --}}
                                                    @include('partials.practice-answer-review')
                                                @elseif ($question->type->value === 'mcq')
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

                                        {{-- Chấm xong thì giấu nút: trắc nghiệm/điền đáp án/nhiều phần đã
                                             thấy đáp án đúng rồi, bấm lại không còn ý nghĩa gì (khác bài
                                             Lập trình — ở đó nút đổi thành "Chấm lại" để sửa code). --}}
                                        @if ($feedback === null)
                                            <div class="flex shrink-0 flex-col items-end gap-2 rounded-xl border border-[#DDEAF0] bg-white p-2.5">
                                                <button type="submit" class="inline-flex min-h-9 items-center justify-center gap-1.5 rounded-lg bg-[#126F91] px-4 py-2 text-[11px] font-bold text-white shadow-sm transition hover:bg-[#0F5E7B]">
                                                    <x-lucide name="send" class="h-3.5 w-3.5" />Kiểm tra đáp án
                                                </button>
                                                <p data-ajax-error class="hidden text-[11px] text-[#B42318]"></p>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </form>

                            {{-- SỬA 18/9 — form "Hoàn tất bài tập" đặt NGOÀI form chấm bài: HTML không
                                 cho lồng <form> vào nhau (trình duyệt sẽ âm thầm bỏ form bên trong).
                                 Nút bấm nằm ở cuối khối kết quả và trỏ ngược lên đây bằng thuộc tính
                                 form="practice-finish-form" — xem partials.practice-answer-review.

                                 SỬA 24/9 — khối kết quả bài LẬP TRÌNH (partials.practice-coding-result)
                                 đã bỏ nút này theo yêu cầu khách; form giữ lại vì các dạng câu còn lại
                                 vẫn dùng. --}}
                            @if ($feedback !== null)
                                <form id="practice-finish-form" method="POST" action="{{ route('student.practiceByQuestion.next') }}" class="hidden">
                                    @csrf
                                </form>
                            @endif
                        @endif
                        </div>
                    </div>
                </section>

                {{-- ───────── TAB: HƯỚNG DẪN ─────────
                     SỬA 1/10 (khách: "tab Hướng dẫn thì lấy đổ dữ liệu field lời giải ra có sẵn
                     trong database rồi") — trước đây tab này in 5 dòng hướng dẫn bấm nút gõ cứng,
                     không liên quan gì tới bài đang làm. Giờ đổ tệp LỜI GIẢI của chính câu đó
                     (metadata.attachments.solution — đúng ô "Lời giải (PDF)" ở form câu hỏi và
                     solution.pdf trong gói ZIP).

                     PDF thì nhúng trình xem y như tab "Đề bài PDF" (cùng data-pdf-fit, cùng khổ
                     820px). Tệp không phải PDF mà đọc được chữ thì in thẳng chữ ra. Còn lại cho
                     link tải về. Chưa có tệp thì vẫn giữ 5 dòng hướng dẫn cũ để tab không trống.

                     MUỐN CHỈ CHO XEM SAU KHI NỘP: bọc nhánh @if ($guideDoc) bên dưới thêm điều
                     kiện của riêng mình (biến $lastResult đã có sẵn ở view này), và sửa kèm
                     PracticeByQuestionService::canSeeGuideDocs() cho route khớp luật — 2 chỗ đó
                     cố ý dùng CHUNG một hàm để không lệch nhau. --}}
                <section x-show="tab === 'guide'" x-cloak class="h-full min-h-0 overflow-hidden p-1 sm:p-2">
                    <div class="assessment-pdf-surface h-full min-h-0 overflow-auto rounded-xl border border-[#DDEAF0] bg-[#EAF4F8] p-1.5 shadow-inner sm:p-3">
                    @if ($guideDoc && $guideDoc['isPdf'])
                        <div data-pdf-fit data-pdf-url="{{ $guideDoc['url'] }}" data-pdf-max-width="820" class="oi-doc-col"></div>
                    @else
                    <article class="assessment-a4-page oi-doc-col oi-doc-page rounded-lg bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-3 border-b-2 border-[#126F91] pb-3">
                            <h3 class="text-sm font-extrabold text-[#123B68]">{{ $guideDoc ? 'Lời giải' : 'Hướng dẫn làm bài' }}</h3>
                            <span class="shrink-0 rounded-lg bg-[#EAF5F8] px-2 py-1 text-[10px] font-bold text-[#126F91]">Hướng dẫn</span>
                        </div>
                        @if ($guideDoc)
                            @if ($guideDoc['text'] !== null)
                                <pre class="oi-guide-pre mt-4">{{ $guideDoc['text'] }}</pre>
                            @else
                                <p class="mt-4 text-[13px] leading-7 text-[#45657D]">Lời giải của bài này là tệp <span class="font-bold">{{ $guideDoc['filename'] }}</span> — tải về để xem.</p>
                            @endif
                            <p class="mt-4">
                                <a href="{{ $guideDoc['url'] }}" target="_blank" rel="noopener"
                                   class="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-[#126F91] px-3 py-1.5 text-[12px] font-bold text-white transition-colors hover:bg-[#0F5E7B]">
                                    <x-lucide name="download" class="h-3.5 w-3.5" />Tải lời giải
                                </a>
                            </p>
                        @else
                            <ul class="mt-4 space-y-2 text-[13px] leading-7 text-[#45657D]">
                                <li>· Đọc đề ở cột trái (hoặc tab <span class="font-bold">Đề bài PDF</span> cho dễ nhìn), trả lời ở cột phải.</li>
                                <li>· Bấm <span class="font-bold">Nộp bài</span> để chấm — trang không tải lại, kết quả hiện ngay tại chỗ.</li>
                                <li>· Câu lập trình được chấm bằng máy chấm thật; mỗi test đúng/sai đều hiện ra, test sai bấm vào xem chi tiết và tải về được.</li>
                                <li>· Kết quả chấm hiện ở <span class="font-bold">cột bên phải</span> khu soạn mã, ngay sau khi bấm Nộp bài.</li>
                                <li>· Làm xong bấm <span class="font-bold">Thoát bài tập</span> ở thanh trên cùng để kết thúc phiên luyện.</li>
                            </ul>
                            <p class="mt-4 text-[11px] leading-6 text-[#607A90]">Bài này chưa được nhập tệp lời giải — thêm ở ô <span class="font-bold">Lời giải (PDF)</span> trong form câu hỏi là phần này tự hiện đúng nội dung.</p>
                        @endif
                    </article>
                    @endif
                    </div>
                </section>

                {{-- ───────── TAB: BÀI MẪU ─────────
                     SỬA 1/10 (khách: "tab bài mẫu thì lấy ở field code mẫu ra nhé trong database
                     có sẵn rồi") — đổ tệp CODE MẪU của chính câu đó
                     (metadata.attachments.reference — đúng ô "Code mẫu / lời giải tham khảo" ở
                     form câu hỏi và reference/official.cpp trong gói ZIP).

                     Ghi chú cũ ở đây viết "không dựng thêm nguồn bài mẫu nào, bày ra trước khi
                     làm là đưa luôn đáp án cho học sinh" — khách đã chốt ĐỔI, nên bỏ. Cách chặn
                     lại (chỉ cho xem sau khi nộp) ghi ở tab Hướng dẫn phía trên. --}}
                <section x-show="tab === 'sample'" x-cloak class="assessment-sample-panel h-full min-h-0 overflow-hidden p-1 sm:p-2">
                    <div class="assessment-pdf-surface h-full min-h-0 overflow-auto rounded-xl border border-[#DDEAF0] bg-[#EAF4F8] p-1.5 shadow-inner sm:p-3">
                    @if ($sampleDoc && $sampleDoc['isPdf'])
                        <div data-pdf-fit data-pdf-url="{{ $sampleDoc['url'] }}" data-pdf-max-width="820" class="oi-doc-col"></div>
                    @elseif ($sampleDoc)
                    <article class="assessment-a4-page oi-doc-col oi-doc-page rounded-lg bg-white shadow-xl">
                        <div class="flex items-start justify-between gap-3 border-b-2 border-[#126F91] pb-3">
                            <h3 class="text-sm font-extrabold text-[#123B68]">Bài mẫu</h3>
                            <span class="shrink-0 rounded-lg bg-[#EAF5F8] px-2 py-1 text-[10px] font-bold text-[#126F91]">{{ $sampleDoc['filename'] }}</span>
                        </div>
                        @if ($sampleDoc['text'] !== null)
                            <pre class="oi-guide-pre mt-4">{{ $sampleDoc['text'] }}</pre>
                        @else
                            <p class="mt-4 text-[13px] leading-7 text-[#45657D]">Bài mẫu của bài này là tệp <span class="font-bold">{{ $sampleDoc['filename'] }}</span> — tải về để xem.</p>
                        @endif
                        <p class="mt-4">
                            <a href="{{ $sampleDoc['url'] }}" target="_blank" rel="noopener"
                               class="inline-flex min-h-9 items-center gap-1.5 rounded-lg bg-[#126F91] px-3 py-1.5 text-[12px] font-bold text-white transition-colors hover:bg-[#0F5E7B]">
                                <x-lucide name="download" class="h-3.5 w-3.5" />Tải bài mẫu
                            </a>
                        </p>
                    </article>
                    @else
                    <article class="assessment-a4-page oi-doc-col oi-doc-page grid place-items-center rounded-lg bg-white text-center shadow-xl">
                        <div>
                            <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-[#EAF5F8] text-[#126F91]"><x-lucide name="book-open" class="h-5 w-5" /></span>
                            <p class="mt-3 text-sm font-extrabold text-[#123B68]">Bài này chưa có bài mẫu</p>
                            <p class="mx-auto mt-1 max-w-sm text-[11px] leading-6 text-[#607A90]">Thêm ở ô <span class="font-bold">Code mẫu / lời giải tham khảo</span> trong form câu hỏi là phần này tự hiện. Đáp án đúng và kết quả từng test vẫn hiện ở tab <span class="font-bold">Làm bài</span> sau khi bấm Nộp bài.</p>
                        </div>
                    </article>
                    @endif
                    </div>
                </section>
                {{-- ───────── TAB: NHẬT KÝ ─────────
                     SỬA 30/9 — tab thứ 5 của bản mẫu mới (ActivityPanel). Ghi lại các mốc trong
                     lúc làm bài ngay TẠI TRÌNH DUYỆT (sessionStorage), KHÔNG gửi gì về máy chủ:
                     mở bài, chuyển tab, dán/sao chép, cửa sổ mất tiêu điểm, phím Print Screen,
                     rời trang, nộp bài. Danh sách do JS ở cuối trang vẽ (không dùng Alpine bên
                     trong để khỏi vướng #practice-container bị thay mới sau mỗi lần chấm). --}}
                <section x-show="tab === 'activity'" x-cloak class="h-full min-h-0 overflow-y-auto p-3 sm:p-4">
                    <div class="mx-auto max-w-3xl rounded-xl border border-[#DDEAF0] bg-white px-3">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-[#DDEAF0] py-2.5">
                            <h3 class="text-xs font-bold text-[#123B68]">Nhật ký làm bài</h3>
                            <span class="text-[10px] text-[#607A90]" data-activity-count>0 sự kiện · 0 dấu hiệu cần xem xét</span>
                            <button type="button" data-activity-download class="ml-auto min-h-8 text-[10px] font-semibold text-[#126F91] underline underline-offset-2">Tải nhật ký</button>
                        </div>
                        <div class="flex gap-3 border-b border-[#DDEAF0] py-2 text-[10px]" aria-label="Lọc nhật ký">
                            <button type="button" data-activity-filter="all" class="font-bold text-[#126F91] underline underline-offset-2">Tất cả</button>
                            <button type="button" data-activity-filter="signals" class="text-[#607A90]">Dấu hiệu (<span data-activity-signal-count>0</span>)</button>
                        </div>
                        <ol class="divide-y divide-[#EEF3F6]" data-activity-list></ol>
                        <p class="border-t border-[#DDEAF0] py-2 text-[10px] text-[#7A92A3]">
                            Đây là tín hiệu để đối chiếu, không tự kết luận vi phạm. Nhật ký chỉ nằm trong phiên trình duyệt này.
                        </p>
                    </div>
                </section>

            </main>
        </div>

        {{-- ═══════════════════ THANH DƯỚI CÙNG ═══════════════════
             SỬA 30/9 — bản mẫu mới có thanh này (footer của AssessmentModal): bên trái là tên
             bài + câu nhắc, bên phải là nút "Nộp bài". Nút nộp đã CHUYỂN từ thanh trên xuống
             đây, giữ NGUYÊN 2 thuộc tính data-header-submit / data-header-submit-label nên
             script bấm hộ + đồng bộ nhãn ở cuối trang không phải sửa một dòng nào.

             SỬA 30/9 (6) (khách: "dưới thiếu nút bài tiếp theo và bài trước") — ĐÃ DỰNG cặp nút
             chuyển bài của bản mẫu. Danh sách bài để chuyển do
             Student\PracticeByQuestionService::siblingIdsFor() dựng: bài tập của sản phẩm thì
             chuyển trong phạm vi sản phẩm đó, câu ở kho chung thì chuyển trong kho chung — xếp
             theo ĐÚNG thứ tự hiển thị (gom theo dạng bài, trong dạng thì theo thứ tự ưu tiên),
             cùng một hàm với danh sách bài tập nên bấm "Bài tiếp theo" đi đúng bài nằm ngay dưới.

             Đây là FORM POST chứ không phải link: bấm là GHI lại phiên luyện (mở bài khác, xoá
             kết quả chấm cũ) — để link GET thì trình duyệt nạp trước hoặc bấm nhầm F5 là đạp
             mất bài đang làm dở. Đầu/cuối danh sách thì nút vẫn hiện nhưng mờ và không bấm được,
             đúng như bản mẫu (disabled khi !canGoPrevious / !canGoNext). --}}
        @php
            $nav = $siblingNav ?? ['prev' => null, 'next' => null, 'position' => 0, 'total' => 0];
            $last = $lastResult ?? null;
        @endphp
        <footer class="flex shrink-0 items-center justify-between gap-3 border-t border-[#DDEAF0] bg-white px-3 py-2.5 sm:px-4">
            <div class="min-w-0">
                <p class="truncate text-[11px] font-bold text-[#123B68]">{{ $headTitle }}@if (($nav['total'] ?? 0) > 1) <span class="font-semibold text-[#607A90]">· bài {{ $nav['position'] }}/{{ $nav['total'] }}</span>@endif</p>
                <p class="hidden text-[10px] text-[#607A90] sm:block" x-text="tab === 'pdf' ? 'Đọc đề rồi chuyển sang Làm bài để trả lời.' : 'Nộp bài để xem kết quả chấm.'">Nộp bài để xem kết quả chấm.</p>
            </div>
            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                @if (($nav['total'] ?? 0) > 1)
                    <nav aria-label="Chuyển bài" class="flex items-center gap-1">
                        @foreach ([['prev', 'Bài trước', 'chevron-left'], ['next', 'Bài tiếp theo', 'chevron-right']] as [$dir, $label, $icon])
                            @php $target = $nav[$dir] ?? null; @endphp
                            @if ($target)
                                <form method="POST" action="{{ route('student.practiceByQuestion.sibling', $target['id']) }}">
                                    @csrf
                                    <button type="submit" aria-label="{{ $label }}" title="{{ $target['title'] }}"
                                            class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-[#DDEAF0] px-2 text-[11px] font-semibold transition hover:bg-[#EAF5F8] {{ $dir === 'next' ? 'text-[#126F91]' : 'text-[#45657D]' }}">
                                        @if ($dir === 'prev')<x-lucide name="chevron-left" class="h-4 w-4" />@endif
                                        <span class="hidden sm:inline">{{ $label }}</span>
                                        @if ($dir === 'next')<x-lucide name="chevron-right" class="h-4 w-4" />@endif
                                    </button>
                                </form>
                            @else
                                <span aria-hidden="true"
                                      class="inline-flex min-h-9 cursor-not-allowed items-center gap-1 rounded-lg border border-[#DDEAF0] px-2 text-[11px] font-semibold text-[#45657D] opacity-40">
                                    @if ($dir === 'prev')<x-lucide name="chevron-left" class="h-4 w-4" />@endif
                                    <span class="hidden sm:inline">{{ $label }}</span>
                                    @if ($dir === 'next')<x-lucide name="chevron-right" class="h-4 w-4" />@endif
                                </span>
                            @endif
                        @endforeach
                    </nav>
                @endif

                {{-- SỬA 30/9 (8) (khách: "hiển thị thêm chỗ đã làm: điểm gần nhất... đúng bao nhiêu
                     test thì hiển thị số test đúng / tổng test") — kết quả LẦN CHẤM GẦN NHẤT của
                     chính người đang học với câu này, đọc từ cơ sở dữ liệu nên mở lại bài sau
                     nhiều ngày vẫn thấy.

                     Viên này LUÔN có trong DOM (chưa làm thì mang lớp hidden): nộp bài xong,
                     script AJAX ở cuối trang thay nó bằng viên mới lấy từ HTML máy chủ vừa trả
                     về — có sẵn chỗ để thay thì lần nộp ĐẦU TIÊN cũng hiện ngay, không phải
                     tải lại trang. --}}
                <span data-done-chip @class(['oi-done-chip', 'hidden' => $last === null])>
                    @if ($last)
                        <span class="oi-done-chip__dot"></span>
                        <span>Đã làm · Điểm gần nhất: <span class="font-black">{{ $last['scoreLabel'] }}</span>/{{ $last['maxLabel'] }}@if ($last['total']) · <span class="font-black">{{ $last['passed'] }}/{{ $last['total'] }}</span> test đúng
                            {{-- @endif PHẢI đứng sau khoảng trắng: Blade dùng \B trước @ nên
                                 directive dính ngay sau một CHỮ CÁI ("đúng@endif") thì nó không
                                 nhận ra, để nguyên văn @endif rồi @if ở trên thành không có
                                 lệnh đóng -> trang 500. --}}
                        @endif</span>
                    @endif
                </span>

                <button type="button" data-header-submit
                        class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl bg-[#126F91] px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0D5B77] disabled:cursor-not-allowed disabled:opacity-50 sm:px-4">
                    <x-lucide name="send" class="h-4 w-4" /><span data-header-submit-label>Nộp bài</span>
                </button>
            </div>
        </footer>
        </div>
    </div>

    {{-- SỬA 1/10 (khách: "khỏi cần hiện popup đang chấm xoay xoay kia") — ĐÃ GỠ lớp phủ
         "đang chấm" khỏi trang này. Quá trình chấm giờ hiện ngay trong cột kết quả (xem
         gradingSlot ở script cuối trang, và khối .oi-judging trong phần style).

         partials/grading-overlay VẪN CÒN và vẫn được phòng thi + cuộc thi dùng: ở đó bấm nộp
         là rời trang sang màn kết quả, không có cột nào để hiện tiến trình tại chỗ. --}}


@endsection

@push('scripts')
    {{-- SỬA 30/9 (3) (khách: "cho full dài ra như tôi vẽ") — KÉO 2 CỘT DÀI HẾT KHUNG.

         Trước đây cột soạn mã và cột kết quả dừng ở 420px, dưới đó là một mảng trống to tướng.
         Nguyên nhân: chuỗi chiều cao bị ĐỨT ở giữa — khung ngoài có chiều cao thật, nhưng
         #practice-container (khối bị thay mới sau mỗi lần chấm) và <form> bên trong lại để cao
         tự động, nên min-h-full của các lớp dưới không bám vào đâu, rơi về đúng 420px tối thiểu.

         Viết bằng CSS thường vì 4 lớp Tailwind cần dùng (grid-rows-1, lg:grid-rows-1,
         lg:min-h-0, lg:h-full) KHÔNG có trong CSS đã build, mà VPS không chạy được vite. --}}
    <style>
        /* min-height (KHÔNG phải height) để màn hẹp — 2 khối xếp dọc, cao hơn khung — vẫn nở
           ra rồi cuộn bình thường, thay vì bị ép đúng 100% rồi cắt mất phần dưới. */
        /* SỬA 30/9 (7) — cột nội dung của ba tab Đề bài / Hướng dẫn / Bài mẫu.
           Viết CSS thường vì max-w-[820px] và sm:py-6 CHƯA CÓ trong public/build/assets/app-*.css
           mà máy chủ thì không chạy được vite — dùng class Tailwind mới là mất tác dụng. */
        .oi-doc-col { margin-left: auto; margin-right: auto; width: 100%; max-width: 820px; }
        .oi-doc-page { padding: 18px 16px; }
        @media (min-width: 640px) { .oi-doc-page { padding: 26px 32px; } }

        /* SỬA 1/10 — khối chữ của tab Hướng dẫn / Bài mẫu khi tệp là MÃ NGUỒN (không phải PDF).
           Viết CSS thường vì các class cần ở đây (whitespace-pre-wrap + break-words + cỡ chữ
           mono 12.5px + nền xám) không có đủ trong app-*.css đã build, mà máy chủ không chạy
           được vite. Dòng dài tự ngắt chứ KHÔNG cuộn ngang: khung tab đã hẹp 820px, thêm thanh
           cuộn ngang là đọc code rất khó. */
        .oi-guide-pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            word-break: break-word;
            margin: 0;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #DDEAF0;
            background: #F7FAFC;
            color: #1F3A52;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12.5px;
            line-height: 1.7;
            tab-size: 4;
        }
        html.theme-dark .assessment-modal .oi-guide-pre {
            border-color: #2C4257;
            background: #16222E;
            color: #D6E4EF;
        }

        /* ══════ KHỐI "ĐANG CHẤM" NẰM NGAY TRONG CỘT KẾT QUẢ ══════
           SỬA 1/10 (khách: "nộp bài hoặc chấm lại thì khỏi cần popup xoay xoay, hiển thị quá
           trình đang chấm bên cột kết quả luôn").

           Viết CSS thường vì máy chủ không chạy được vite. Màu lấy đúng bộ màu sẵn có nên
           nền tối cũng tự khớp (nền #FFF và chữ #45657D/#7A92A3 đều đã có luật lật sẵn). */
        .oi-judging {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
            height: 100%;
            padding: 20px 18px;
            border: 1px solid #DDEAF0;
            border-radius: 12px;
            background: #fff;
            text-align: center;
        }
        .oi-judging__ring {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 4px solid #E3EFF4;
            border-top-color: #126F91;
            border-right-color: #2F8A6B;
            animation: oi-judging-spin .85s linear infinite;
        }
        .oi-judging__title { font-size: 13px; font-weight: 800; color: #123B68; }
        .oi-judging__text { max-width: 320px; font-size: 11px; line-height: 1.6; color: #7A92A3; }
        .oi-judging__time {
            font-size: 11px;
            font-weight: 700;
            color: #45657D;
            font-variant-numeric: tabular-nums;
        }
        /* Thanh chạy KHÔNG phải phần trăm: máy chấm chạy xong cả lượt mới trả về một lần, không
           có mốc nào để báo "đã xong mấy phần trăm". Vẽ thanh trượt qua lại cho đúng sự thật là
           "đang chạy, chưa biết còn bao lâu" — đừng bịa ra con số. */
        .oi-judging__bar {
            position: relative;
            width: 100%;
            max-width: 240px;
            height: 4px;
            overflow: hidden;
            border-radius: 99px;
            background: #E3EFF4;
        }
        .oi-judging__bar::after {
            content: "";
            position: absolute;
            top: 0;
            bottom: 0;
            width: 40%;
            border-radius: 99px;
            background: linear-gradient(90deg, #126F91, #2F8A6B);
            animation: oi-judging-slide 1.15s ease-in-out infinite;
        }
        @keyframes oi-judging-spin { to { transform: rotate(360deg); } }
        @keyframes oi-judging-slide {
            0%   { left: -40%; }
            100% { left: 100%; }
        }
        @media (prefers-reduced-motion: reduce) {
            .oi-judging__ring { animation: none; }
            .oi-judging__bar::after { animation: none; left: 0; width: 100%; opacity: .5; }
        }
        /* Nền tối: khối này dùng màu viết thẳng (không phải class bg-white) nên luật lật màu
           chung của nền tối không với tới — phải tự khai, nếu không nó là một thẻ trắng chói
           nằm giữa giao diện tối. */
        html.theme-dark .assessment-modal .oi-judging {
            border-color: #365361;
            background: #1b2d38;
        }
        html.theme-dark .assessment-modal .oi-judging__title { color: #eaf5f8; }
        html.theme-dark .assessment-modal .oi-judging__text { color: #9eb4c0; }
        html.theme-dark .assessment-modal .oi-judging__time { color: #c3d4dc; }
        html.theme-dark .assessment-modal .oi-judging__ring {
            border-color: #2a4654;
            border-top-color: #4fb6d6;
            border-right-color: #56c69b;
        }
        html.theme-dark .assessment-modal .oi-judging__bar { background: #2a4654; }

        /* Viên "Đã làm · Điểm gần nhất" nằm ở partials/assessment-chip-style (dùng chung
           với phòng thi) — nạp ở cuối tệp này. */

        /* Nền tối: TỜ GIẤY VẪN SÁNG (luật .assessment-a4-page trong app.css — cố ý, giống
           mọi trình đọc PDF), nên chữ nằm trên giấy phải giữ màu tối. Luật chung của nền tối
           đổi mấy màu chữ này sang màu sáng cho hợp nền tối — đúng ở mọi chỗ khác, nhưng ở
           trong tờ giấy thì thành chữ nhạt trên nền sáng, nhìn không ra. Ba dòng dưới kéo
           chúng về lại, chỉ trong phạm vi tờ giấy. */
        html.theme-dark .assessment-modal .assessment-a4-page [class~="text-[#123B68]"] { color: #123b68 !important; }
        html.theme-dark .assessment-modal .assessment-a4-page [class~="text-[#607A90]"],
        html.theme-dark .assessment-modal .assessment-a4-page [class~="text-[#7A92A3]"] { color: #5a7183 !important; }
        html.theme-dark .assessment-modal .assessment-a4-page [class~="bg-[#EAF5F8]"] { background-color: #eaf5f8 !important; }
        html.theme-dark .assessment-modal .assessment-a4-page [class~="text-[#126F91]"] { color: #126f91 !important; }

        #practice-container { display: flex; flex-direction: column; min-height: 100%; }
        #practice-container > form { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }
        .oi-work-fill { display: flex; flex-direction: column; min-height: 0; flex: 1 1 auto; }

        /* Chỉ ép hàng lưới cao bằng khung ở MÀN RỘNG — màn hẹp xếp dọc 2 khối, để cao tự nhiên
           rồi cuộn như cũ.

           flex-basis 0 (không phải auto) là chỗ quan trọng: có vậy chiều cao của lưới mới do
           KHUNG quyết định chứ không do nội dung, nên danh sách 20 test dài không đẩy 2 cột
           phình ra khỏi khung mà cuộn bên trong ô kết quả — đã đo bằng trình duyệt thật, để
           'auto' thì cột tràn xuống dưới cả thanh Nộp bài. min-height:0 cho các cột con để
           chúng co theo khung thay vì giữ cứng 420px. */
        @media (min-width: 1024px) {
            #practice-container { height: 100%; min-height: 0; }
            #practice-container > form { min-height: 0; }
            .oi-work-fill { min-height: 0; }
            .oi-work-grid { min-height: 0; flex: 1 1 0%; grid-template-rows: minmax(0, 1fr); }
            .oi-work-grid > * { min-height: 0; }

            /* SỬA 30/9 (4) (khách: "chấm xong cũng phải hiển thị cho full chiều cao") — chấm
               xong, THẺ KẾT QUẢ phải cao bằng cột chứ không dừng ở chiều cao nội dung rồi chừa
               một mảng trống bên dưới. Danh sách test cũng bỏ trần 288px (max-h-72) để nở hết
               chỗ còn lại — vẫn có thanh cuộn riêng khi nhiều test, nhưng là cuộn TRONG danh
               sách, không phải cuộn cả thẻ. */
            /* SỬA 1/10 — đổi "> section" thành "> *": ô kết quả giờ chứa 3 thứ khác nhau tuỳ
               lúc (lời nhắc khi chưa nộp, khối ĐANG CHẤM, khối kết quả thật). Chỉ nhắm riêng
               thẻ section thì hai thứ đầu không nở hết cột, chừa khoảng trống phía dưới. */
            .oi-result-fill { display: flex; flex-direction: column; }
            .oi-result-fill > * { min-height: 0; flex: 1 1 0%; }
        }

        /* Màn hẹp: thẻ kết quả chỉ cần cao ÍT NHẤT bằng khung, nội dung dài thì cứ nở ra rồi
           cuộn khung ngoài như cũ. */
        .oi-result-fill > * { min-height: 100%; }

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
        /*
         * SỬA 1/10 (khách: "nộp bài hoặc chấm lại giờ không muốn hiển thị popup chấm bài nữa,
         * hiển thị quá trình đang chấm bên cột kết quả luôn").
         *
         * Trước đây là một lớp phủ che kín màn hình kèm vòng xoay. Giờ vẽ thẳng vào CỘT KẾT
         * QUẢ: học sinh vẫn nhìn thấy mã mình vừa viết, vẫn cuộn đọc được, và chỗ báo "đang
         * chấm" nằm ĐÚNG chỗ lát nữa kết quả hiện ra.
         *
         * Chấm xong thì cả #practice-container bị thay mới bằng HTML máy chủ trả về, nên
         * không phải dọn gì — chỉ khi hỏng mới trả lại nội dung cũ (xem gradingSlot.restore).
         */
        var gradingSlot = (function () {
            var saved = null;
            var ticker = null;

            function slot() { return document.querySelector('[data-result-slot]'); }

            return {
                start: function () {
                    var box = slot();
                    if (!box) return;

                    saved = box.innerHTML;
                    box.innerHTML =
                        '<div class="oi-judging" role="status" aria-live="polite">'
                        + '<span class="oi-judging__ring"></span>'
                        + '<p class="oi-judging__title">Đang chấm bài…</p>'
                        + '<p class="oi-judging__text">Máy chấm đang chạy chương trình của bạn qua từng bộ test.'
                        + ' Bài nhiều test có thể mất một lúc — đừng đóng cửa sổ này nhé.</p>'
                        + '<span class="oi-judging__bar"></span>'
                        + '<p class="oi-judging__time" data-judging-time>Đã chờ 0 giây</p>'
                        + '</div>';

                    var startedAt = Date.now();
                    var timeEl = box.querySelector('[data-judging-time]');
                    ticker = setInterval(function () {
                        if (!timeEl || !timeEl.isConnected) return;
                        var s = Math.round((Date.now() - startedAt) / 1000);
                        timeEl.textContent = s < 60
                            ? 'Đã chờ ' + s + ' giây'
                            : 'Đã chờ ' + Math.floor(s / 60) + ' phút ' + (s % 60) + ' giây';
                    }, 1000);
                },

                stop: function () {
                    if (ticker) { clearInterval(ticker); ticker = null; }
                },

                // Chỉ dùng khi KHÔNG chấm được (mạng hỏng): trả cột kết quả về đúng như trước
                // khi bấm, thay vì để nó quay mãi.
                restore: function () {
                    this.stop();
                    var box = slot();
                    if (box && saved !== null) box.innerHTML = saved;
                    saved = null;
                },
            };
        })();

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.matches('[data-ajax-answer]')) return;

            event.preventDefault();

            if (form.__codeEditor) {
                form.__codeEditor.save();
            }

            var button = form.querySelector('button[type="submit"]');
            var errorEl = form.querySelector('[data-ajax-error]');
            // SỬA 1/10 (khách chốt danh sách việc cần ghi nhật ký) — ĐÃ BỎ dòng ghi "Nộp bài".
            // Nộp bài không nằm trong 4 thứ khách muốn theo dõi, mà lịch sử nộp thì đã có sẵn
            // trong cơ sở dữ liệu rồi.
            var originalButtonHtml = button ? button.innerHTML : '';
            if (errorEl) errorEl.classList.add('hidden');
            if (button) {
                button.disabled = true;
                button.innerHTML = '<span class="oi-btn-spinner"></span>Đang chấm...';
            }

            // SỬA 1/10 — báo "đang chấm" NGAY TRONG cột kết quả thay cho lớp phủ cũ.
            gradingSlot.start();

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

                    // SỬA 30/9 (8) — viên "Đã làm · Điểm gần nhất" nằm ở thanh dưới cùng, NGOÀI
                    // #practice-container nên không được thay theo. Lấy luôn viên mới trong
                    // chính trang máy chủ vừa dựng lại (đã có kết quả lần chấm vừa xong) thay
                    // cho viên cũ — khỏi phải tính lại điểm ở phía trình duyệt.
                    var newChip = new DOMParser().parseFromString(html, 'text/html')
                        .querySelector('[data-done-chip]');
                    var oldChip = document.querySelector('[data-done-chip]');
                    if (newChip && oldChip) oldChip.replaceWith(newChip);

                    initCodeEditor();
                    // Cột kết quả đã bị thay bằng kết quả thật rồi, chỉ cần tắt bộ đếm giờ.
                    gradingSlot.stop();
                })
                .catch(function () {
                    // Không chấm được: trả cột kết quả về như trước khi bấm, đừng để nó quay mãi.
                    gradingSlot.restore();

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

            // SỬA 30/9 (6) — phần tải test sai ĐÃ CHUYỂN sang
            // partials/practice-test-download (tải .in/.out thay vì một tệp .txt gộp),
            // nghe cùng kiểu delegation ở document nên không cần gọi gì thêm ở đây.
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
                // SỬA 1/10 — theo luôn trạng thái khoá của nút nộp thật. Trước đây lớp phủ che
                // kín màn hình nên không bấm lại được; giờ bỏ lớp phủ thì nút này phải tự khoá
                // lúc đang chấm, không thì bấm hai lần là gửi hai lượt chấm.
                headerBtn.disabled = target === null || target.disabled;

                // SỬA 24/9 — lấy chữ ngay trên nút nộp thật (giờ đã ẩn) nên chấm xong nút
                // header tự đổi thành "Chấm lại", khớp với việc học sinh sửa code rồi chấm tiếp.
                var targetLabel = target ? target.textContent.trim() : '';

                labelEl.textContent = answering
                    ? (targetLabel !== '' ? targetLabel : 'Nộp bài')
                    : (target ? 'Hoàn tất bài tập' : 'Đã xong');
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
    @include('partials.assessment-dark-tune')
    @include('partials.assessment-chip-style')
    @include('partials.code-editor-runtime')

    {{-- SỬA 30/9 (6) — tải test sai về máy dưới dạng .in/.out. --}}
    @include('partials.practice-test-download')

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

    {{-- ══════ SỬA 30/9 — ĐỒNG HỒ + NHẬT KÝ LÀM BÀI (bản mẫu mới) ══════
         Hai thứ của bản mẫu mới, cả hai đều CHỈ CHẠY Ở TRÌNH DUYỆT — không thêm một lời gọi
         máy chủ nào, không đụng vào luồng chấm bài:
           · đồng hồ đếm lên từ lúc mở trang (thanh trên cùng);
           · nhật ký: mở bài, chuyển tab, dán/sao chép, cửa sổ mất tiêu điểm rồi quay lại, phím
             Print Screen, rời trang, nộp bài. Lưu ở sessionStorage theo mã bài, đóng trình
             duyệt là hết — giống hệt cách bản mẫu làm (assessmentLogKey).

         Dùng thuần JS (không Alpine) vì phần lớn sự kiện đến từ #practice-container — khối bị
         thay mới sau mỗi lần chấm, mà Alpine 3 không khởi tạo DOM do JS chèn vào. --}}
    <script>
        (function () {
            // ── Đồng hồ ──
            var timerEl = document.querySelector('[data-work-timer]');
            if (timerEl) {
                var startedAt = Date.now();
                var pad2 = function (n) { return n < 10 ? '0' + n : String(n); };
                setInterval(function () {
                    var s = Math.max(0, Math.round((Date.now() - startedAt) / 1000));
                    timerEl.textContent = pad2(Math.floor(s / 3600)) + ':' + pad2(Math.floor(s / 60) % 60) + ':' + pad2(s % 60);
                }, 1000);
            }

            // ── Nhật ký ──
            var listEl = document.querySelector('[data-activity-list]');
            var countEl = document.querySelector('[data-activity-count]');
            var signalEl = document.querySelector('[data-activity-signal-count]');
            var shell = document.querySelector('.assessment-modal-shell');
            if (!listEl) return;

            var KEY = 'onthi360:practice-activity:' + (document.querySelector('[data-activity-key]')?.getAttribute('data-activity-key') || 'exercise');
            var filter = 'all';
            var entries = [];
            try {
                var saved = JSON.parse(window.sessionStorage.getItem(KEY) || '[]');
                if (Array.isArray(saved)) entries = saved.filter(function (e) { return e && typeof e.title === 'string'; }).slice(0, 100);
            } catch (e) { entries = []; }

            var fmt = new Intl.DateTimeFormat('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });

            function persist() {
                try { window.sessionStorage.setItem(KEY, JSON.stringify(entries.slice(0, 100))); } catch (e) {}
            }

            function render() {
                var signals = entries.filter(function (e) { return e.kind === 'signal'; }).length;
                if (countEl) countEl.textContent = entries.length + ' sự kiện · ' + signals + ' dấu hiệu cần xem xét';
                if (signalEl) signalEl.textContent = String(signals);

                var visible = filter === 'signals' ? entries.filter(function (e) { return e.kind === 'signal'; }) : entries;
                listEl.innerHTML = '';

                if (visible.length === 0) {
                    var empty = document.createElement('li');
                    empty.className = 'py-3 text-[11px] text-[#607A90]';
                    empty.textContent = filter === 'signals' ? 'Chưa có dấu hiệu nào cần xem xét.' : 'Chưa có sự kiện nào.';
                    listEl.appendChild(empty);
                    return;
                }

                visible.forEach(function (entry) {
                    var li = document.createElement('li');
                    li.className = 'flex flex-wrap gap-x-2 gap-y-1 py-2 text-[11px] leading-5';
                    var time = document.createElement('time');
                    time.className = 'shrink-0 text-[10px] text-[#7A92A3]';
                    time.setAttribute('datetime', entry.occurredAt || '');
                    time.textContent = entry.time || '';
                    var title = document.createElement('span');
                    title.className = entry.kind === 'signal' ? 'font-semibold text-amber-700' : 'font-semibold text-[#123B68]';
                    title.textContent = (entry.kind === 'signal' ? '• ' : '') + entry.title;
                    var detail = document.createElement('span');
                    detail.className = 'text-[#607A90]';
                    detail.textContent = entry.detail || '';
                    li.appendChild(time); li.appendChild(title); li.appendChild(detail);
                    listEl.appendChild(li);
                });
            }

            function add(kind, title, detail) {
                var now = new Date();
                entries.unshift({
                    id: now.getTime() + '-' + Math.random().toString(36).slice(2, 7),
                    kind: kind, title: title, detail: detail,
                    occurredAt: now.toISOString(), time: fmt.format(now),
                });
                entries = entries.slice(0, 100);
                persist();
                render();
            }

            /*
             * SỬA 1/10 (khách: "chỉ muốn lưu nhật ký nếu chuyển tab giữa Hướng dẫn và Bài mẫu,
             * chụp màn hình, bật tab mới, quay chụp ảnh màn hình — chỉ cần vậy thôi").
             *
             * ĐÃ BỎ HẲN: mở bài làm, nộp bài, trở lại bài làm, rời trang, sao chép, dán, và
             * chuyển sang các tab Đề bài / Làm bài / Nhật ký. Mấy thứ đó đẻ ra hàng chục dòng
             * mỗi phiên, lấp mất đúng những dấu hiệu khách cần nhìn.
             *
             * CÒN LẠI ĐÚNG 4 VIỆC:
             *   1. mở tab Hướng dẫn hoặc Bài mẫu;
             *   2. bấm phím chụp màn hình;
             *   3. trang làm bài bị ẩn (mở tab/cửa sổ khác);
             *   4. có yêu cầu quay/chụp màn hình từ trình duyệt.
             */
            window.oiWorkLog = {
                add: add,
                tabChanged: function (tab) {
                    // CHỈ hai tab này. Đề bài / Làm bài / Nhật ký là chỗ phải qua lại liên tục
                    // trong lúc làm, ghi vào thì nhật ký thành một dải vô nghĩa.
                    var label = { guide: 'Hướng dẫn', sample: 'Bài mẫu' }[tab];
                    if (!label) return;
                    add('event', 'Mở tab ' + label, 'Chuyển sang xem "' + label + '".');
                },
            };

            // ── 3. Mở tab/cửa sổ khác ──────────────────────────────────────────────────
            // Trình duyệt KHÔNG cho biết người ta mở trang nào, chỉ cho biết trang này bị ẩn.
            // Nên câu chữ phải nói đúng chừng đó, không được đoán thêm.
            //
            // Lúc quay lại thì KHÔNG thêm dòng mới (khách không xin) mà ghi thẳng khoảng thời
            // gian vắng mặt vào chính dòng cũ — vẫn biết đi bao lâu mà nhật ký không phình.
            var away = null;

            function elapsed(startedAt) {
                var s = Math.max(0, Math.round((Date.now() - startedAt) / 1000));
                return s < 60 ? s + ' giây' : Math.floor(s / 60) + ' phút ' + (s % 60) + ' giây';
            }

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    if (away) return;
                    // add() chèn vào ĐẦU mảng, nên gọi xong thì entries[0] chính là dòng vừa thêm.
                    add('signal', 'Mở tab hoặc cửa sổ khác', 'Trang làm bài bị ẩn đi.');
                    away = { at: Date.now(), entry: entries[0] };
                    return;
                }

                if (!away) return;
                var gone = elapsed(away.at);
                if (away.entry) {
                    away.entry.detail = 'Trang làm bài bị ẩn đi ' + gone + '.';
                    persist();
                    render();
                }
                away = null;
            });

            // ── 2 + 4. Chụp màn hình / quay màn hình ───────────────────────────────────
            document.addEventListener('keydown', function (event) {
                if (event.repeat) return;

                if (event.key === 'PrintScreen' || event.code === 'PrintScreen') {
                    add('signal', 'Bấm phím chụp màn hình', 'Trang nhận được phím Print Screen.');
                    return;
                }

                /*
                 * Tổ hợp chụp/quay màn hình của hệ điều hành:
                 *   · macOS   Cmd+Shift+3 / 4 / 5  (5 là quay màn hình)
                 *   · Windows Win+Shift+S          (Snipping Tool)
                 *
                 * LƯU Ý THẬT LÒNG: hệ điều hành thường nuốt mấy tổ hợp này trước khi tới trang,
                 * nên bắt được là may chứ KHÔNG chắc chắn. Không bắt được cũng không có nghĩa
                 * là người ta không chụp — câu chữ trong nhật ký nói đúng mức đó.
                 */
                if (event.shiftKey && (event.metaKey || event.ctrlKey)
                    && ['3', '4', '5', 'S', 's'].indexOf(event.key) >= 0) {
                    add('signal', 'Tổ hợp phím chụp/quay màn hình', 'Trang nhận được tổ hợp phím chụp hoặc quay màn hình.');
                }
            }, true);

            /*
             * Quay/chụp màn hình bằng chính trình duyệt (chia sẻ màn hình): chỉ bắt được khi
             * lời gọi xuất phát TỪ TRANG NÀY. Phần mềm quay màn hình cài ngoài thì không trang
             * web nào biết được — đó là giới hạn của trình duyệt, không phải thiếu sót ở đây.
             */
            try {
                var media = navigator.mediaDevices;
                if (media && typeof media.getDisplayMedia === 'function') {
                    var original = media.getDisplayMedia.bind(media);
                    media.getDisplayMedia = function () {
                        add('signal', 'Yêu cầu quay/chụp màn hình', 'Trang nhận được yêu cầu chia sẻ hoặc quay màn hình.');
                        return original.apply(null, arguments);
                    };
                }
            } catch (e) {}

            // ── Bộ lọc + tải nhật ký ──
            document.querySelectorAll('[data-activity-filter]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    filter = btn.getAttribute('data-activity-filter');
                    document.querySelectorAll('[data-activity-filter]').forEach(function (other) {
                        var on = other === btn;
                        var signals = other.getAttribute('data-activity-filter') === 'signals';
                        other.className = on
                            ? (signals ? 'font-bold text-amber-700 underline underline-offset-2' : 'font-bold text-[#126F91] underline underline-offset-2')
                            : 'text-[#607A90]';
                    });
                    render();
                });
            });

            var downloadBtn = document.querySelector('[data-activity-download]');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function () {
                    var lines = entries.map(function (e) {
                        return [e.time, e.kind === 'signal' ? 'DẤU HIỆU' : 'Sự kiện', e.title, e.detail].join(' | ');
                    });
                    var blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'nhat-ky-lam-bai.txt';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });
            }

            render();
        })();
    </script>

    {{-- SỬA 30/9 — ĐÃ GỠ đoạn script của nút "Chạy test" (gửi mã + dữ liệu ô Input lên
         student.practiceByQuestion.run rồi in ra ô Output). Bản mẫu mới bỏ hẳn cụm Input/Output
         khỏi khu soạn mã (xem ghi chú ở cột kết quả phía trên), nên script này không còn thẻ nào
         để bám. Route/service chạy thử vẫn còn nguyên trong mã nguồn. --}}
@endpush
