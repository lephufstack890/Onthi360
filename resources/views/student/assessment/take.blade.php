@extends('layouts.exam')

@section('title', 'Làm bài')

@section('content')
    @php
        $questions = $questions ?? [];
        $assessmentTitle = $assessmentModel->title ?? 'Đề';
        $examCode = $examCode ?? null;
        $totalPoints = $totalPoints ?? collect($questions)->sum('points');

        // Dữ liệu tối thiểu cho Alpine — chỉ những gì JS cần để điều hướng/đếm/lưu, phần
        // hiển thị (đề bài, phương án...) đã dựng sẵn bằng Blade ở dưới nên không đẩy sang JS.
        $vm = collect($questions)->map(fn ($q) => [
            'id' => $q['questionId'],
            'no' => $q['no'],
            'kind' => $q['kind'],
        ])->values();

        $firstId = $vm->first()['id'] ?? null;
        $lastId = $vm->last()['id'] ?? null;
    @endphp

    {{-- Giữ lại đường báo lỗi của bản cũ (vd nộp bài bị server từ chối rồi quay lại). --}}
    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    {{-- SỬA 18/9 (khách: "copy Assessment Modal á, khi click thì nó hiển thị modal vậy á") —
         lớp vỏ chép ĐÚNG 2 thẻ ngoài cùng của bản mẫu: nền tối phủ kín + khung bo góc thụt vào
         8px mỗi bên. Trang vẫn có URL riêng (bấm F5 hay nút Back của trình duyệt đều đúng,
         không mất bài đang làm) nhưng nhìn y hệt modal của bản mẫu. --}}
    <div class="assessment-modal fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-0 sm:p-2"
         x-data="examWorkspace({
             deadlineAt: @js($deadlineAt ?? null),
             serverNow: @js($serverNow ?? null),
             saveUrl: @js(route('student.assessment.take.save', $attempt->id)),
             runUrl: @js(route('student.assessment.take.run', $attempt->id)),
             {{-- SỬA 24/9 (khách: "hiển thị modal vậy được rồi, không cần chuyển qua trang
                  result đâu") — có 2 khoá này thì doSubmit() nộp bằng AJAX rồi hiện điểm ngay
                  trong hộp, không rời màn hình. Màn nào KHÔNG truyền (phòng thi Cuộc thi) vẫn
                  chạy đường cũ: gửi form thật, chuyển sang trang kết quả. --}}
             submitUrl: @js(route('student.assessment.take.submit', $attempt->id)),
             statusUrl: @js(route('student.assessment.status', $attempt->id)),
             questions: @js($vm),
             answers: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => $q['kind'] === 'fill' ? (string) ($q['textAnswer'] ?? '') : (($q['selectedOption'] === null) ? '' : (string) $q['selectedOption'])])),
             codes: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => (string) ($q['codeSource'] ?? '')])),
             languages: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => $q['language'] ?: 'cpp'])),
             firstId: @js($firstId),
             lastId: @js($lastId),
             {{-- SỬA 1/10 (khách: "mặc định khi bắt đầu làm đề thi ở tab đề bài nha") — mở tab
                  Đề bài khi đề CÓ bản PDF; đề không có PDF nào thì mở thẳng Làm bài. Cùng luật
                  với màn Luyện tập (xem $statementUrl ở exercise-play). --}}
             initialTab: @js(filled($examPdfUrl ?? null) || collect($questions)->contains(fn ($q) => filled($q['statementPdfUrl'] ?? null)) ? 'pdf' : 'work'),
         })"
         x-init="init(); $watch('activeTab', function (value) { if (window.oiWorkLog) window.oiWorkLog.tabChanged(value); }); $watch('activeId', function (id) { scrollToStatement(id); })">

        {{-- SỬA 1/10 — data-activity-key: nhật ký lưu theo TỪNG LƯỢT THI (attempt), không lẫn
             với nhật ký của màn Luyện tập hay của lượt thi khác. --}}
        <div class="assessment-modal-shell flex h-full w-full max-w-none flex-col overflow-hidden bg-[#F8FBFC] shadow-2xl sm:h-[calc(100dvh-16px)] sm:max-w-[calc(100vw-16px)] sm:rounded-xl"
             data-activity-key="exam-{{ $attempt->id }}">

        {{-- ══════ LỚP PHỦ HẾT GIỜ (giữ nguyên hành vi cũ: chặn thật + tự nộp) ══════ --}}
        <div x-cloak x-show="expired" x-transition.opacity
             class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#FFF5DE] text-[#A4621B]"><x-lucide name="clock" class="h-6 w-6" /></span>
                <h2 class="mt-3 text-base font-extrabold text-[#123B68]">Đã hết giờ làm bài</h2>
                <p class="mt-2 text-[12px] text-[#607A90]">Bài làm của bạn đang được tự động nộp, vui lòng đợi trong giây lát…</p>
                <span class="mt-4 inline-block h-6 w-6 animate-spin rounded-full border-2 border-[#CBEAF1] border-t-[#126F91]"></span>
            </div>
        </div>

        {{-- ══════ HỎI LẠI TRƯỚC KHI NỘP ══════ --}}
        <div x-cloak x-show="confirmOpen" x-transition.opacity @keydown.escape.window="confirmOpen = false"
             class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-base font-extrabold text-[#123B68]">Nộp đề ngay?</h2>
                <p class="mt-2 text-[12px] leading-5 text-[#607A90]">
                    Bạn đã trả lời <span class="font-bold text-[#126F91]" x-text="answeredCount()"></span>/{{ count($questions) }} câu.
                    <span x-show="answeredCount() < {{ count($questions) }}" class="font-bold text-[#A4621B]">Vẫn còn câu chưa trả lời.</span>
                    Sau khi nộp sẽ không sửa lại được.
                </p>
                <div class="mt-5 flex gap-2">
                    <button type="button" @click="confirmOpen = false" class="flex-1 rounded-xl border border-[#DDEAF0] bg-white px-4 py-2.5 text-[12px] font-bold text-[#45657D] transition hover:bg-[#F4F9FB]">Làm tiếp</button>
                    <button type="button" @click="confirmOpen = false; doSubmit()" class="flex-1 rounded-xl bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77]">Nộp đề</button>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════ HEADER ══════════════════════════ --}}
        <header class="assessment-modal-header flex shrink-0 items-center gap-2 border-b border-[#DDEAF0] bg-white px-3 py-2 sm:px-4">
            {{-- SỬA 1/10 (khách: "khi thoát làm đề thi thì nó lại nhảy ra trang admin, tôi muốn
                 nhảy ra lại trang luyện tập public ngay tab đề thi luyện tập luôn") — trước đây
                 trỏ về student.practice.index (màn Luyện tập TRONG khu đăng nhập). Màn đó bọc
                 trong khung workspace theo VAI TRÒ của người đang đăng nhập, nên admin/giáo viên
                 bấm Thoát là rơi vào giao diện quản trị. Trang /luyen-tap công khai không có
                 khung đó, ai vào cũng ra đúng một trang. --}}
            <a href="{{ route('practice.index', ['tab' => 'de-thi']) }}" aria-label="Thoát phòng thi" title="Thoát (bài làm đã tự lưu)"
               class="rounded-xl p-2 text-[#607A90] transition hover:bg-[#F4F9FB]"><x-lucide name="x" class="h-5 w-5" /></a>

            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#126F91]">
                    @if ($examCode){{ $examCode }} · @endif{{ $totalPoints }} điểm
                </p>
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <h2 class="min-w-0 truncate text-sm font-extrabold text-[#123B68] sm:text-base">{{ $assessmentTitle }}</h2>
                    @if (count($questions) > 1)
                        <select x-model.number="activeId" :disabled="expired || submitting" aria-label="Chọn câu trong đề"
                                class="min-w-[150px] max-w-[220px] rounded-lg bg-white px-2 py-1 text-[10px] font-bold text-[#45657D] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                            @foreach ($questions as $q)
                                <option value="{{ $q['questionId'] }}">Câu {{ $q['no'] }} · {{ $q['title'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            {{-- Dải số câu: xanh đậm = đang xem, xanh lá = đã trả lời, trắng = chưa --}}
            @if (count($questions) > 1)
                <div class="hidden min-w-0 max-w-full items-center gap-1 rounded-xl bg-[#F4F8FB] px-1.5 py-1.5 md:flex" aria-label="Tiến độ câu hỏi">
                    {{-- SỬA 18/9 (khách: "bấm 2 nút mũi tên không được") — LỖI CŨ: hai nút này chỉ
                         CUỘN dải số câu theo chiều ngang. Đề ít câu thì dải không hề tràn, cuộn
                         không đi đâu cả -> bấm y như không có gì xảy ra, dù tooltip vẫn ghi
                         "Câu trước"/"Câu sau". Giờ cho nó làm ĐÚNG việc ghi trên tooltip: chuyển
                         sang câu trước/câu sau, và mờ đi khi đã ở đầu/cuối đề. --}}
                    <button type="button" @click="goPrev()" :disabled="activeId === firstId || expired || submitting"
                            aria-label="Câu trước" title="Câu trước"
                            class="grid h-6 w-6 shrink-0 place-items-center rounded-full border border-[#D6E3EF] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40"><x-lucide name="chevron-left" class="h-3.5 w-3.5" /></button>
                    <div x-ref="rail" class="no-scrollbar flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto px-0.5 py-1">
                        @foreach ($questions as $q)
                            <button type="button" data-rail-pill="{{ $q['questionId'] }}"
                                    @click="setActive({{ $q['questionId'] }})" :disabled="expired || submitting"
                                    :aria-pressed="activeId === {{ $q['questionId'] }} ? 'true' : 'false'"
                                    title="{{ $q['title'] }}"
                                    class="grid h-7 w-7 shrink-0 place-items-center rounded-full border text-[10px] font-extrabold transition"
                                    :class="activeId === {{ $q['questionId'] }}
                                        ? 'border-[#123B68] bg-[#123B68] text-white ring-2 ring-[#CBEAF1]'
                                        : (isAnswered({{ $q['questionId'] }})
                                            ? 'border-[#2F8A6B] bg-[#2F8A6B] text-white hover:bg-[#28795E]'
                                            : 'border-[#C9DCE4] bg-white text-[#607A90] hover:border-[#126F91] hover:text-[#126F91]')">{{ $q['no'] }}</button>
                        @endforeach
                    </div>
                    <button type="button" @click="goNext()" :disabled="activeId === lastId || expired || submitting"
                            aria-label="Câu sau" title="Câu sau"
                            class="grid h-6 w-6 shrink-0 place-items-center rounded-full border border-[#D6E3EF] bg-white text-[#45657D] transition hover:border-[#9DC8D7] hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40"><x-lucide name="chevron-right" class="h-3.5 w-3.5" /></button>
                </div>
            @endif

            <button type="button" @click="toggleTheme()" aria-label="Đổi nền sáng/tối" title="Đổi nền sáng/tối"
                    class="grid h-8 w-8 shrink-0 place-items-center rounded-xl border border-[#DDEAF0] bg-white text-[#607A90] transition hover:bg-[#EAF5F8]">
                <span x-show="theme === 'dark'" x-cloak><x-lucide name="sun" class="h-4 w-4" /></span>
                <span x-show="theme !== 'dark'"><x-lucide name="moon" class="h-4 w-4" /></span>
            </button>

            <template x-if="deadlineAt !== null">
                <div class="hidden items-center gap-2 rounded-xl px-3 py-2 tabular-nums sm:flex"
                     :class="tone === 'danger' ? 'bg-[#F8D7DA] text-[#9B2C2C]' : 'bg-[#FFF5DE] text-[#A4621B]'">
                    <x-lucide name="clock" class="h-4 w-4" /><span class="text-xs font-black" x-text="remainingLabel"></span>
                </div>
            </template>

            <span class="hidden items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-[#607A90] sm:flex">
                <x-lucide name="save" class="h-4 w-4" /><span x-text="saving ? 'Đang lưu…' : 'Đã lưu đề'"></span>
            </span>

            {{-- SỬA 30/9 (10) (khách: "chuyển chỗ nộp bài xuống dưới") — nút "Nộp đề" ĐÃ CHUYỂN
                 xuống thanh dưới cùng, đúng như modal làm bài tập chuyên đề. --}}
        </header>

        {{-- ═══════════════════ RAIL 4 TAB + NỘI DUNG ═══════════════════ --}}
        <div class="assessment-modal-main flex min-h-0 flex-1 flex-col md:flex-row">
            <aside class="assessment-modal-tabs shrink-0 border-b border-[#DDEAF0] bg-white md:w-12 md:border-b-0 md:border-r">
                {{-- SỬA 1/10 (khách: "các tab chỗ bắt đầu làm đề khi click vào nó phải như này
                     nè cho đồng bộ") — thêm tab thứ 5 "Nhật ký" cho khớp màn Luyện tập. Khung +
                     phần JS dùng CHUNG partial với màn đó (work-activity-panel/-log). --}}
                <div class="oi-tab-rail grid h-full gap-1 p-1.5 md:flex md:flex-col md:gap-1 md:p-2">
                    @foreach ([['pdf', 'Đề bài PDF'], ['work', 'Làm bài'], ['guide', 'Hướng dẫn'], ['sample', 'Bài mẫu'], ['activity', 'Nhật ký']] as [$tabId, $tabLabel])
                        <button type="button" @click="activeTab = '{{ $tabId }}'" title="{{ $tabLabel }}" aria-label="{{ $tabLabel }}"
                                class="flex min-h-9 min-w-0 items-center justify-center rounded-lg px-1.5 py-1.5 text-center transition md:min-h-[56px] md:w-full md:flex-col md:justify-center"
                                :class="activeTab === '{{ $tabId }}' ? 'bg-[#126F91] text-white shadow-sm' : 'text-[#45657D] hover:bg-[#F4F9FB]'">
                            <span class="min-w-0"><span class="block text-[10px] font-extrabold leading-tight md:rotate-180 md:[writing-mode:vertical-rl]">{{ $tabLabel }}</span></span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <main class="assessment-modal-content min-w-0 flex-1 overflow-hidden">

                {{-- ───────── TAB: ĐỀ BÀI PDF ─────────
                     SỬA 18/9 — tự vẽ bằng pdf.js cho VỪA CHIỀU NGANG khung, xem
                     partials/pdf-fit-viewer (lý do đầy đủ ghi trong partial đó). --}}
                {{-- SỬA 1/10 (khách: "đề nó phải hiển thị như này đừng to full nha") — chép ĐÚNG
                     bố cục tab Đề bài của màn Luyện tập: khung lõm xanh nhạt bo góc ở ngoài, tờ
                     đề rộng tối đa 820px canh giữa ở trong (data-pdf-max-width + .oi-doc-col).
                     Trước đây thiếu cả hai nên đề kéo căng hết bề ngang màn hình. --}}
                {{-- SỬA 1/10 (khách: "đề bài pdf có thể cho hiển thị đọc hết đề bài của tổng
                     toàn bộ câu trong đề được không") — trước đây mỗi câu bọc trong
                     x-show="activeId === …" nên chỉ thấy đề của ĐÚNG câu đang chọn, muốn đọc
                     trước cả đề phải bấm chuyển từng câu. Giờ xếp đề của MỌI CÂU thành một mạch
                     cuộn, mỗi câu có dải tên ở trên để biết đang đọc câu nào.

                     Đi kèm 2 thứ, thiếu là hỏng:
                       · pdf-fit-viewer đã thêm TẢI LƯỜI (IntersectionObserver): đề 20 câu mà vẽ
                         hết ngay lúc mở tab thì vừa chờ lâu vừa ngốn bộ nhớ — lý do đầy đủ ghi
                         trong partial đó.
                       · đổi câu ở thanh đầu thì tự cuộn tới khối đề của câu đó
                         (examWorkspace.scrollToStatement, nối bằng $watch ở x-init). --}}
                <section x-show="activeTab === 'pdf'" x-cloak class="h-full min-h-0 overflow-hidden p-1 sm:p-2">
                    <div class="assessment-pdf-surface h-full min-h-0 overflow-auto rounded-xl border border-[#DDEAF0] bg-[#EAF4F8] p-1.5 shadow-inner sm:p-3">
                        {{-- SỬA 2/10 lần 5 (khách: "đổ dữ liệu file PDF xem trước vô tab này…
                             chỉ cần hiển thị Tệp PDF xem trước là được rồi, không cần hiển thị
                             gì khác") — đề đã có tệp PDF do người ra đề tải lên thì tab này CHỈ
                             có đúng tệp đó: không dải tên câu, không đề bài PDF của từng câu,
                             không ô "câu này không có bản PDF". Trang đề đọc liền một mạch.

                             Đề KHÔNG có tệp đó (mọi đề cũ, đề của bài giao/cuộc thi) thì giữ
                             nguyên cách cũ bên dưới, không đụng gì. --}}
                        @if ($examPdfUrl ?? null)
                            <div class="oi-doc-col">
                                <div data-pdf-fit data-pdf-url="{{ $examPdfUrl }}" data-pdf-max-width="820" class="min-h-[320px]"></div>
                            </div>
                        @else
                        <div class="oi-doc-col flex flex-col gap-3">
                            @foreach ($questions as $q)
                                <section data-exam-statement="{{ $q['questionId'] }}" class="scroll-mt-2">
                                    {{-- Dải tên câu: không có nó thì cuộn một mạch qua chục câu
                                         là mất dấu đang đọc câu nào. Đổi màu theo câu đang chọn
                                         để khớp với dải số câu trên thanh đầu. --}}
                                    <div class="mb-2 flex items-center gap-2 rounded-xl border px-3 py-2"
                                         :class="activeId === {{ $q['questionId'] }} ? 'border-[#123B68] bg-[#123B68] text-white' : 'border-[#DDEAF0] bg-white text-[#123B68]'">
                                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] font-extrabold"
                                              :class="activeId === {{ $q['questionId'] }} ? 'bg-white/20 text-white' : 'bg-[#EAF5F8] text-[#126F91]'">{{ $q['no'] }}</span>
                                        <span class="min-w-0 flex-1 truncate text-[12px] font-bold">{{ $q['title'] }}</span>
                                        <span class="shrink-0 text-[11px] font-bold opacity-80">{{ $q['points'] }} điểm</span>
                                    </div>

                                    @if ($q['statementPdfUrl'])
                                        <div data-pdf-fit data-pdf-url="{{ $q['statementPdfUrl'] }}" data-pdf-max-width="820" class="min-h-[320px]"></div>
                                    @else
                                        <div class="rounded-xl border border-[#DDEAF0] bg-white px-4 py-6 text-center">
                                            <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-[#EAF5F8] text-[#126F91]"><x-lucide name="file-text" class="h-5 w-5" /></span>
                                            <p class="mt-3 text-sm font-extrabold text-[#123B68]">Câu này không có bản PDF</p>
                                            <p class="mt-1 text-[11px] text-[#607A90]">Toàn bộ nội dung đề nằm ở tab <span class="font-bold">Làm bài</span>.</p>
                                        </div>
                                    @endif
                                </section>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </section>

                {{-- ───────── TAB: LÀM BÀI ───────── --}}
                <section x-show="activeTab === 'work'" class="assessment-work-panel flex h-full min-h-0 flex-col overflow-hidden p-1.5 sm:p-2">
                    {{-- SỬA 30/9 (10) — ĐÃ BỎ cặp mũi tên nhỏ ở đây. Chuyển câu giờ có hai nút
                         CÓ NHÃN ở thanh dưới cùng, cộng với dải số câu trên thanh đầu (giữ
                         nguyên theo yêu cầu) — ba chỗ làm cùng một việc là thừa. --}}
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
                        <span class="truncate text-[10px] font-bold uppercase tracking-[.08em] text-[#7A92A3]" x-text="currentKind() === 'code' ? 'Soạn mã' : 'Trả lời câu hỏi'"></span>
                    </div>

                    <div class="oi-exam-body mt-2 min-h-0 flex-1 overflow-y-auto lg:overflow-hidden">
                        @foreach ($questions as $q)
                            @php $qid = $q['questionId']; @endphp
                            <div x-show="activeId === {{ $qid }}" class="oi-exam-fill min-h-full">
                                @if ($q['kind'] === 'code')
                                    <div class="oi-exam-grid grid min-h-full gap-2 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,0.9fr)]">
                                        {{-- ── Trình soạn mã ── --}}
                                        <section class="flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-xl bg-[#F4F9FB]">
                                            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-white px-3 py-2.5 text-[#123B68] sm:px-4">
                                                {{-- SỬA 9/10 (khách: "thêm C++14") — máy chấm vẫn chỉ có 2 họ ngôn ngữ (cpp,
                                                     python); C++14 là 'cpp14' = cùng trình biên dịch C++ nhưng thêm cờ
                                                     -std=c++14 (xem CodeJudgingService::compilerOptions()). 'cpp' vẫn là C++17. --}}
                                                <select x-model="languages[{{ $qid }}]" data-code-language @change="onLanguageChange({{ $qid }})" :disabled="expired"
                                                        aria-label="Chọn ngôn ngữ lập trình"
                                                        class="rounded-lg bg-[#F4F9FB] px-2 py-1.5 text-[10px] font-bold text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                                                    <option value="cpp">C++17</option>
                                                    <option value="cpp14">C++14</option>
                                                    <option value="python">Python 3</option>
                                                </select>
                                                <div class="flex items-center gap-1.5">
                                                    <label class="inline-flex cursor-pointer items-center gap-1 rounded-lg bg-[#EAF5F8] px-2 py-1.5 text-[10px] font-bold text-[#126F91] transition hover:bg-[#D9EFF3]">
                                                        <x-lucide name="upload" class="h-3.5 w-3.5" />Nộp bằng file
                                                        <input type="file" accept=".cpp,.cc,.cxx,.h,.hpp,.py,.txt" class="hidden"
                                                               :disabled="expired" @change="loadCodeFile({{ $qid }}, $event)">
                                                    </label>
                                                    <button type="button" @click="resetCode({{ $qid }})" :disabled="expired"
                                                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[10px] font-bold text-[#607A90] transition hover:bg-[#F4F9FB] disabled:cursor-not-allowed disabled:opacity-50"><x-lucide name="rotate-ccw" class="h-3.5 w-3.5" />Đặt lại</button>
                                                </div>
                                            </div>

                                            {{-- SỬA 18/9 (khách: "tab làm bài là để làm bài chứ không cần hiển thị đề") —
                                                 câu CÓ bản PDF thì chỉ để tên câu + chỉ chỗ đọc đề, dành hết chiều cao
                                                 cho chỗ gõ code. Câu KHÔNG có PDF vẫn in đề ở đây, vì đó là chỗ DUY
                                                 NHẤT học sinh đọc được đề. ($q['body'] là HTML do CKEditor lưu — giữ
                                                 nguyên cách render RAW như bản cũ.) --}}
                                            {{-- SỬA 1/10 (khách: "UI này nó không khớp với UI của khi làm bài luyện tập,
                                                 check lại UI + logic chỗ này cho giống khi click làm bài") — khối này
                                                 trước đây chiếm 3 DÒNG trên đầu ô soạn mã (số câu + tên câu + chỉ chỗ
                                                 đọc đề), đẩy chỗ gõ code xuống thấp hơn hẳn màn Luyện tập (ở đó chỉ có
                                                 ĐÚNG MỘT dòng mảnh). Gộp còn một dòng cho khớp.

                                                 GIỮ "Câu N · điểm" trong dòng đó, cố ý: phòng thi có nhiều câu, bỏ hẳn
                                                 là học sinh không biết câu đang làm được mấy điểm. Tên câu thì đã có ở
                                                 ô chọn câu trên thanh đầu nên không lặp lại nữa.

                                                 Câu KHÔNG có PDF vẫn in đề ở đây (chỗ DUY NHẤT đọc được đề), bố cục
                                                 chép đúng màn Luyện tập: khối cuộn riêng, cao tối đa 30% khung. --}}
                                            @if ($q['statementPdfUrl'])
                                                <p class="shrink-0 px-4 pb-2 pt-2 text-[11px] text-[#7A92A3]">Câu {{ $q['no'] }} · {{ $q['points'] }} điểm · Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                            @elseif ($examPdfUrl ?? null)
                                                {{-- Đề có tệp PDF chung: chỉ cột mốc, đề bài đọc ở tab Đề bài PDF. --}}
                                                <p class="shrink-0 px-4 pb-2 pt-2 text-[11px] text-[#7A92A3]">Câu {{ $q['no'] }} · {{ $q['points'] }} điểm · Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                            @else
                                                <p class="shrink-0 px-4 pt-2 text-[11px] text-[#7A92A3]">Câu {{ $q['no'] }} · {{ $q['points'] }} điểm</p>
                                                @if ($q['body'])
                                                    <div class="max-h-[30%] shrink-0 overflow-y-auto px-4 pb-3 pt-2 text-xs leading-5 text-[#45657D]">
                                                        <div class="rich-content">{!! $q['body'] !!}</div>
                                                    </div>
                                                @endif
                                            @endif

                                            {{-- Lớp tô màu cú pháp nằm dưới, textarea trong suốt nằm trên — đúng cách bản mẫu làm.
                                                 SỬA 1/10 — min-h-[240px] cho khớp màn Luyện tập: min-h-0 làm ô gõ code
                                                 co lại rất thấp khi đề dài, đúng chỗ khách thấy "không khớp". --}}
                                            <div class="relative min-h-[240px] flex-1 overflow-hidden">
                                                <pre aria-hidden="true" x-ref="hl{{ $qid }}"
                                                     class="pointer-events-none absolute inset-0 z-20 overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6"><code x-html="highlight(codes[{{ $qid }}], languages[{{ $qid }}])"></code></pre>
                                                {{-- data-code-source: DẤU NHẬN BIẾT cho CSS, không có JS nào đọc.
                                                     Chế độ tối có luật "textarea nền #172a35" (chép từ bản mẫu) — ô này
                                                     phải trong suốt để lộ lớp tô màu bên dưới, xem app.css. --}}
                                                <textarea data-code-source x-model="codes[{{ $qid }}]" :disabled="expired" spellcheck="false"
                                                          @scroll="syncScroll($event, 'hl{{ $qid }}')"
                                                          @input.debounce.700ms="onCode({{ $qid }})"
                                                          aria-label="Trình soạn mã có tô màu cú pháp"
                                                          title="Tab thụt dòng · Shift+Tab lùi · Enter tự giữ mức thụt · Ctrl+/ chú thích · Alt+↑↓ đẩy dòng · Shift+Alt+↑↓ nhân đôi dòng · Esc rồi Tab để nhảy ô"
                                                          style="color: transparent; -webkit-text-fill-color: transparent;"
                                                          class="absolute inset-0 z-10 h-full w-full resize-none overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6 outline-none selection:bg-[#2F8A6B]/40"></textarea>
                                            </div>
                                        </section>

                                        {{-- ── CỘT KẾT QUẢ CHẤM ──
                                             SỬA 30/9 (10) (khách: "xoá chỉ chạy test") — ĐÃ BỎ hai ô
                                             INPUT/OUTPUT và nút "Chạy test". Hai ô đó chỉ tồn tại để
                                             phục vụ nút chạy thử; bỏ nút mà giữ ô thì còn lại hai
                                             khung trống không làm gì.

                                             Chỗ này giờ là cột kết quả, đúng bố cục modal làm bài tập
                                             chuyên đề. Khác một điểm: phòng thi chấm CẢ ĐỀ một lượt
                                             khi bấm "Nộp đề" rồi chuyển sang trang kết quả, nên ở đây
                                             chỉ nói trước điều đó chứ không có kết quả tại chỗ. --}}
                                        <div class="flex min-h-[420px] min-w-0 flex-col gap-2 overflow-hidden">
                                            <div class="min-h-0 flex-1 overflow-y-auto rounded-xl border border-[#DDEAF0] bg-white px-3 py-3">
                                                <p class="text-[11px] font-bold text-[#45657D]">Kết quả chấm hiện sau khi nộp đề.</p>
                                                <p class="mt-2 text-[11px] leading-5 text-[#7A92A3]">
                                                    Viết mã ở cột bên trái. Bài làm được
                                                    <span class="font-bold text-[#126F91]">tự động lưu</span> ngay khi bạn gõ, nên
                                                    chuyển qua câu khác rồi quay lại vẫn còn nguyên.
                                                </p>
                                                <p class="mt-2 text-[11px] leading-5 text-[#7A92A3]">
                                                    Bấm <span class="font-bold text-[#126F91]">Nộp đề</span> ở thanh dưới khi làm xong
                                                    toàn bộ đề — máy chấm chạy từng bộ test của các câu lập trình rồi đưa bạn sang
                                                    trang kết quả.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="oi-exam-grid flex min-h-full flex-col gap-2 lg:grid lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,0.9fr)]">
                                        <section class="flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-xl bg-[#EEF6F8]">
                                            <div class="shrink-0 px-3 py-2.5 text-[10px] font-black uppercase tracking-[.12em] text-[#126F91]">Cách trả lời</div>
                                            <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                                                <div class="mb-3 rounded-lg bg-white px-3 py-3">
                                                    <p class="text-[10px] font-bold uppercase tracking-wide text-[#7A92A3]">Câu {{ $q['no'] }} · {{ $q['typeLabel'] }} · {{ $q['points'] }} điểm</p>
                                                    <p class="mt-1 text-sm font-bold text-[#123B68]">{{ $q['title'] }}</p>
                                                    @if ($q['statementPdfUrl'] || ($examPdfUrl ?? null))
                                                        <p class="mt-1 text-[11px] text-[#7A92A3]">Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                                    @endif
                                                    {{-- Câu không có PDF riêng thì phần chữ vẫn in ở đây: đề có tệp PDF
                                                         chung không có nghĩa là câu này đã nằm trong đó, bỏ đi là học
                                                         sinh mất chỗ DUY NHẤT đọc được nội dung câu. --}}
                                                    @if (! $q['statementPdfUrl'] && $q['body'])
                                                        <div class="rich-content mt-1 text-xs leading-6 text-[#45657D]">{!! $q['body'] !!}</div>
                                                    @endif
                                                </div>

                                                @if ($q['kind'] === 'choice')
                                                    <div class="space-y-2">
                                                        @foreach ($q['options'] as $i => $opt)
                                                            <label class="flex cursor-pointer items-center gap-3 rounded-lg bg-white px-3 py-2.5 text-xs font-semibold text-[#45657D] transition hover:bg-[#F8FBFC]"
                                                                   :class="answers[{{ $qid }}] === '{{ $i }}' ? 'bg-[#EAF5F8] text-[#126F91] shadow-sm' : ''">
                                                                <input type="radio" value="{{ $i }}" x-model="answers[{{ $qid }}]" :disabled="expired"
                                                                       @change="onAnswer({{ $qid }})" class="h-4 w-4 accent-[#126F91]">
                                                                <span>{{ chr(65 + $i) }}. {{ $opt }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="space-y-2">
                                                        <label class="block text-xs font-bold text-[#123B68]" for="fill-{{ $qid }}">Đáp án của bạn <span class="text-rose-500">*</span></label>
                                                        <input id="fill-{{ $qid }}" type="text" x-model="answers[{{ $qid }}]" :disabled="expired"
                                                               @input.debounce.700ms="onAnswer({{ $qid }})" placeholder="Nhập đáp án"
                                                               class="w-full rounded-lg bg-white px-3 py-3 text-sm text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                                                        <p class="text-[10px] leading-5 text-[#607A90]">Hệ thống sẽ chuẩn hóa khoảng trắng thừa khi chấm.</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </section>

                                        <section class="flex min-h-0 flex-col overflow-hidden rounded-xl bg-[#FFF8E8] p-3">
                                            <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#A4621B]">Trạng thái</span>
                                            <p class="mt-3 text-sm font-bold text-[#7C541C]" x-text="isAnswered({{ $qid }}) ? 'Đã nhập câu trả lời' : 'Chưa trả lời'"></p>
                                            <p class="mt-2 text-[11px] leading-5 text-[#967342]">{{ $q['kind'] === 'choice' ? 'Chọn một phương án phù hợp nhất.' : 'Kiểm tra lại đáp án trước khi nộp bài.' }}</p>
                                        </section>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- ───────── TAB: HƯỚNG DẪN ───────── --}}
                <section x-show="activeTab === 'guide'" x-cloak class="h-full min-h-0 overflow-y-auto p-2 sm:p-3">
                    <article class="min-h-full rounded-xl bg-white p-4 sm:p-6">
                        <h3 class="text-sm font-extrabold text-[#123B68]">Hướng dẫn làm bài</h3>
                        <ul class="mt-3 space-y-2 text-[13px] leading-7 text-[#45657D]">
                            <li>· Câu trả lời được <span class="font-bold">tự động lưu</span> ngay khi bạn nhập — không cần bấm nút lưu.</li>
                            <li>· Dải số câu trên đầu: xanh lá là câu đã trả lời, xanh đậm là câu đang xem.</li>
                            @if ($deadlineAt ?? null)
                                <li>· Hết giờ hệ thống sẽ <span class="font-bold">tự nộp</span> bài; đồng hồ tính theo giờ máy chủ.</li>
                            @endif
                            <li>· Bấm <span class="font-bold">Nộp đề</span> khi làm xong; sau khi nộp sẽ không sửa lại được.</li>
                        </ul>
                        <p class="mt-4 text-[11px] leading-6 text-[#607A90]">Gợi ý riêng cho từng câu chưa được nhập vào hệ thống — khi kho câu hỏi có trường hướng dẫn, phần này sẽ hiện đúng nội dung của câu đang làm.</p>
                    </article>
                </section>

                {{-- ───────── TAB: BÀI MẪU ───────── --}}
                <section x-show="activeTab === 'sample'" x-cloak class="assessment-sample-panel h-full min-h-0 overflow-y-auto p-2 sm:p-3">
                    <article class="grid min-h-full place-items-center rounded-xl bg-white p-6 text-center">
                        <div>
                            <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-[#EAF5F8] text-[#126F91]"><x-lucide name="book-open" class="h-5 w-5" /></span>
                            <p class="mt-3 text-sm font-extrabold text-[#123B68]">Bài mẫu mở sau khi nộp</p>
                            {{-- CỐ Ý không hiện đáp án trong lúc đang làm bài. Lời giải là dữ liệu nhạy cảm
                                 (Question::attachmentInfo ghi rõ 'solution' không bao giờ lộ cho học sinh) và
                                 việc công bố đáp án đã có luật riêng: Assessment::publish_answer_rule, xem
                                 AssessmentService::answersPublishedNow(). --}}
                            <p class="mx-auto mt-1 max-w-sm text-[11px] leading-6 text-[#607A90]">Đáp án tham khảo chỉ hiển thị ở trang kết quả, và chỉ khi đề cho phép công bố đáp án.</p>
                        </div>
                    </article>
                </section>
                {{-- SỬA 1/10 — tab thứ 5 "Nhật ký", dùng CHUNG partial với màn Luyện tập. --}}
                @include('partials.work-activity-panel', ['activityTabExpr' => "activeTab === 'activity'"])

            </main>
        </div>

        {{-- ═══════════════════ THANH DƯỚI CÙNG ═══════════════════
             SỬA 30/9 (10) (khách: "chuyển chỗ nộp bài xuống dưới vs thêm bài làm trước, bài tiếp
             theo") — dựng đúng thanh dưới của modal làm bài tập chuyên đề.

             Hai nút chuyển câu gọi lại ĐÚNG goPrev()/goNext() mà dải số câu trên thanh đầu vẫn
             đang dùng, nên ba chỗ luôn cùng một trạng thái — không có đường đi nào mới.

             Viên "Đã làm · Điểm gần nhất" ở đây CỐ Ý KHÔNG có phần "x/y test đúng" (khách dặn
             rõ): đây là điểm của CẢ ĐỀ gồm nhiều câu đủ dạng, cộng số test của các câu lập trình
             lại với nhau thì con số chẳng có nghĩa gì. --}}
        @php $last = $lastResult ?? null; @endphp
        <footer class="flex shrink-0 items-center justify-between gap-3 border-t border-[#DDEAF0] bg-white px-3 py-2.5 sm:px-4">
            <div class="min-w-0">
                <p class="truncate text-[11px] font-bold text-[#123B68]">{{ $assessmentTitle }}</p>
                <p class="hidden text-[10px] text-[#607A90] sm:block"
                   {{-- SỬA 1/10 — ở tab khác thì nút nộp bị khoá, câu nhắc phải nói rõ lý do. --}}
                   x-text="activeTab === 'work' ? 'Bài làm tự lưu — bấm Nộp đề khi xong.' : 'Chuyển sang tab Làm bài để nộp đề.'">Bài làm tự lưu — bấm Nộp đề khi xong.</p>
            </div>

            <div class="flex shrink-0 items-center gap-1.5 sm:gap-2">
                @if (count($questions) > 1)
                    <nav aria-label="Chuyển câu" class="flex items-center gap-1">
                        <button type="button" @click="goPrev()" :disabled="activeId === firstId || expired || submitting"
                                aria-label="Câu trước" title="Câu trước"
                                class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-[#DDEAF0] px-2 text-[11px] font-semibold text-[#45657D] transition hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                            <x-lucide name="chevron-left" class="h-4 w-4" /><span class="hidden sm:inline">Câu trước</span>
                        </button>
                        <button type="button" @click="goNext()" :disabled="activeId === lastId || expired || submitting"
                                aria-label="Câu tiếp theo" title="Câu tiếp theo"
                                class="inline-flex min-h-9 items-center gap-1 rounded-lg border border-[#DDEAF0] px-2 text-[11px] font-semibold text-[#126F91] transition hover:bg-[#EAF5F8] disabled:cursor-not-allowed disabled:opacity-40">
                            <span class="hidden sm:inline">Câu tiếp theo</span><x-lucide name="chevron-right" class="h-4 w-4" />
                        </button>
                    </nav>
                @endif

                <span @class(['oi-done-chip', 'hidden' => $last === null])>
                    @if ($last)
                        <span class="oi-done-chip__dot"></span>
                        <span>Đã làm · Điểm gần nhất: <span class="font-black">{{ $last['scoreLabel'] }}</span>/{{ $last['maxLabel'] }}</span>
                    @endif
                </span>

                {{-- SỬA 1/10 (khách: "button nộp bài và chấm lại khi ở tab bài làm thì mới click
                     được thôi, sang tab khác thì disabled đi") — ở đây đơn giản hơn màn Luyện tập
                     vì nút này do Alpine quản hoàn toàn, thêm 1 điều kiện là xong. --}}
                <button type="button" @click="confirmOpen = true"
                        :disabled="expired || submitting || activeTab !== 'work'"
                        :title="activeTab === 'work' ? null : 'Chuyển sang tab Làm bài để nộp đề'"
                        class="inline-flex min-h-10 shrink-0 items-center gap-1.5 rounded-xl bg-[#126F91] px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0D5B77] disabled:cursor-wait disabled:opacity-60 sm:px-4">
                    <x-lucide name="send" class="h-4 w-4" />Nộp đề
                </button>
            </div>
        </footer>

        {{-- ══════ FORM NỘP THẬT — giữ NGUYÊN hợp đồng tên trường như bản cũ ══════ --}}
        <form method="POST" action="{{ route('student.assessment.take.submit', $attempt->id) }}" id="take-form" x-ref="examForm" class="hidden">
            @csrf
            @foreach ($questions as $q)
                @php $qid = $q['questionId']; @endphp
                @if ($q['kind'] === 'code')
                    <input type="hidden" name="answers[{{ $qid }}][code_source]">
                    <input type="hidden" name="answers[{{ $qid }}][language]">
                @elseif ($q['kind'] === 'fill')
                    <input type="hidden" name="answers[{{ $qid }}][text]">
                @else
                    <input type="hidden" name="answers[{{ $qid }}][selected_option]">
                @endif
            @endforeach
        </form>

        {{-- SỬA 24/9 (khách: "khi nộp đề nó cũng hiển thị modal đang chấm như chỗ làm câu hỏi")
             — doSubmit() đặt submitting = true rồi mới gửi form thật, nên lớp phủ này hiện suốt
             lúc trình duyệt đang chờ máy chủ chấm, tới khi chuyển sang trang kết quả.

             PHẢI nằm TRONG khối x-data thì x-show="submitting" mới đọc được biến. Kiểu dáng
             dùng chung với màn Luyện tập theo câu. --}}
        @include('partials.grading-overlay', [
            'overlayMode' => 'alpine-result',
            'overlayTitle' => 'Đang nộp và chấm bài…',
            'overlayText' => 'Các câu lập trình được máy chấm chạy qua từng bộ test. Đừng đóng cửa sổ này nhé — điểm sẽ hiện ngay khi xong.',
        ])
        </div>
    </div>
@endsection

@push('scripts')
    {{-- SỬA 30/9 (11) (khách khoanh đỏ vùng trống: "chỗ này full ra giống UI modal làm bài tập
         chuyên đề, full chiều cao ra") — NỐI LẠI CHUỖI CHIỀU CAO cho khu làm bài.

         Trước đây hai cột dừng ở 420px rồi chừa một mảng trống to tướng xuống tận thanh dưới.
         Nguyên nhân: khung ngoài có chiều cao thật, nhưng mấy lớp ở giữa chỉ đặt min-height:100%
         — mà min-height KHÔNG làm cho thuộc tính height hết "auto", nên tới lượt cái lưới bên
         trong thì "100%" của nó quy về 0, hai cột rơi về đúng trần 420px của chính mình.

         Cách chữa giống hệt màn làm bài tập chuyên đề: cho từng lớp ở giữa thành flex cột và
         để cái lưới nở bằng flex thay vì bằng phần trăm.

         flex-basis 0 (KHÔNG phải auto) là chỗ quan trọng: có vậy chiều cao lưới mới do KHUNG
         quyết định chứ không do nội dung — để 'auto' thì đề dài sẽ đẩy hai cột tràn xuống dưới
         cả thanh Nộp đề. Chỉ ép ở màn rộng; màn hẹp vẫn xếp dọc và cuộn tự nhiên như cũ. --}}
    <style>
        .oi-exam-body { display: flex; flex-direction: column; }
        /* Màn hẹp: KHÔNG cho co (flex-shrink 0). Khung ngoài mới là chỗ cuộn, để nó co được
           thì đề dài sẽ bị cắt cụt đúng phần nằm ngoài khung. */
        .oi-exam-fill { display: flex; flex-direction: column; flex: 1 0 auto; }

        @media (min-width: 1024px) {
            .oi-exam-fill { min-height: 0; flex: 1 1 auto; }
            .oi-exam-grid { min-height: 0; flex: 1 1 0%; grid-template-rows: minmax(0, 1fr); }
            .oi-exam-grid > * { min-height: 0; }
        }
    </style>

    @include('partials.work-doc-col-style')
    @include('partials.assessment-dark-tune')
    @include('partials.assessment-chip-style')
    @include('partials.exam-workspace-script')
    {{-- SỬA 1/10 — đồng hồ + nhật ký làm bài, dùng CHUNG partial với màn Luyện tập. --}}
    @include('partials.work-activity-log')
@endpush
