{{--
    PHÒNG THI CUỘC THI — ĐỀ DẠNG PDF + PHIẾU ĐÁP ÁN.

    SỬA 19/9 (5) (khách: "UI này đang xấu quá, xây lại cho giống ảnh cho đẹp") — trước đây
    cuộc thi dùng đề PDF bị rơi về student/assessment/take-pdf.blade.php, vốn chạy trong
    layouts.student nên còn nguyên sidebar + banner khu học sinh, nhìn lệch hẳn so với phòng
    thi câu lập trình. Giờ có màn RIÊNG: giữ đúng bố cục 2 khung của ảnh khách gửi (đề PDF bên
    trái, phiếu đáp án bên phải, thanh chuyển câu ở đáy) nhưng khoác vỏ modal toàn màn hình +
    nhận diện cuộc thi.

    LOGIC GIỮ NGUYÊN TUYỆT ĐỐI — không viết lại một luật nghiệp vụ nào:
      · Dữ liệu do Student\AssessmentService::buildTakeData() (nhánh PDF) dựng, y như đường cũ.
      · Tên trường của <input> giữ NGUYÊN: answers[answer_keys][...] / answers[coding_items][...]
        — đổi một ký tự là nộp bài mất câu trả lời.
      · Tự lưu -> student.assessment.take.save; Nộp -> student.assessment.take.submit.
      · JS dùng CHUNG partials/exam-pdf-workspace-script (tách nguyên văn từ take-pdf).
--}}
@extends('layouts.exam')

@section('title', 'Thi đấu · '.($contestTitle ?? 'Cuộc thi'))

@section('content')
    @php
        $answerRows = $answerRows ?? [];
        $codingRows = $codingRows ?? [];
        $assessmentTitle = $assessmentModel->title ?? 'Đề thi';
        $maxAttempts = $assessmentModel->resubmission_policy['max_attempts'] ?? null;
        // Cuộc thi mặc định 1 lượt (AttemptService::assertResubmissionAllowed) — nói đúng sự
        // thật ở đây thay vì "không giới hạn" như màn tự luyện.
        $resubmissionNote = $maxAttempts ? 'Nộp lại tối đa '.$maxAttempts.' lần' : 'Mỗi thí sinh nộp 1 lần';
        $totalCount = count($answerRows) + count($codingRows);

        // Nhãn/biểu tượng dạng câu lấy thẳng từ enum — màn làm bài và màn soạn đáp án không
        // bao giờ lệch tên dạng câu.
        $typeMeta = collect(\App\Enums\AnswerSheetQuestionType::cases())
            ->mapWithKeys(fn ($t) => [$t->value => ['label' => $t->label(), 'icon' => $t->icon()]])
            ->all();

        $contestTitle = $contestTitle ?? 'Cuộc thi';
        $contestRoundLabel = $contestRoundLabel ?? 'Vòng thi';
        $contestTimeRange = $contestTimeRange ?? 'Chưa xếp lịch';
        $contestStatusLabel = $contestStatusLabel ?? 'Đang diễn ra';
        $contestRoomUrl = $contestRoomUrl ?? route('competitions.index');

        // Số thứ tự chạy suốt cả phiếu: câu trắc nghiệm trước, bài lập trình sau — khớp đúng
        // thứ tự thẻ câu hiển thị, để thanh chuyển câu ở đáy đánh số không nhảy cóc.
        $navItems = [];
        foreach ($answerRows as $row) {
            // 'doneExpr' = biểu thức Alpine cho biết câu này ĐÃ TRẢ LỜI CHƯA. Dựng sẵn ở đây
            // thay vì viết thêm hàm trong JS, vì chỉ có Blade mới biết câu số N là dòng phiếu
            // đáp án (answer_keys) hay bài lập trình (coding_items) — hai bản đồ khác nhau.
            $navItems[] = [
                'no' => $row['no'],
                'doneExpr' => 'answeredAnswerMap['.$row['answerKeyId'].']',
            ];
        }
        $codingStartNo = count($answerRows) > 0 ? (max(array_column($answerRows, 'no')) + 1) : 1;
        foreach (array_values($codingRows) as $idx => $row) {
            $navItems[] = [
                'no' => $codingStartNo + $idx,
                'doneExpr' => 'answeredCodingMap['.$row['codingItemId'].']',
            ];
        }
    @endphp

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="assessment-modal fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-0 sm:p-2"
         x-data="pdfExamTake({
             deadlineAt: @js($deadlineAt ?? null),
             serverNow: @js($serverNow ?? null),
             saveUrl: @js(route('student.assessment.take.save', $attempt->id)),
             initialAnswerStatus: @js(collect($answerRows)->mapWithKeys(fn ($r) => [$r['answerKeyId'] => $r['answered']])),
             initialCodingStatus: @js(collect($codingRows)->mapWithKeys(fn ($r) => [$r['codingItemId'] => $r['answered']])),
             questionNos: @js(array_column($navItems, 'no')),
             warnOnLeave: true,
         })"
         x-init="init()">

        <div class="assessment-modal-shell flex h-full w-full max-w-none flex-col overflow-hidden bg-[#F8FBFC] shadow-2xl sm:h-[calc(100dvh-16px)] sm:max-w-[calc(100vw-16px)] sm:rounded-xl">

        {{-- ══════ LỚP PHỦ HẾT GIỜ (chặn thật + tự nộp, y như màn cũ) ══════ --}}
        <div x-cloak x-show="expired" x-transition.opacity
             class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-[#FFF5DE] text-[#A4621B]"><x-lucide name="clock" class="h-6 w-6" /></span>
                <h2 class="mt-3 text-base font-extrabold text-[#123B68]">Hết giờ vòng thi</h2>
                <p class="mt-2 text-[12px] text-[#607A90]">Bài thi của bạn đang được tự động nộp cho ban tổ chức, vui lòng đợi trong giây lát…</p>
                <span class="mt-4 inline-block h-6 w-6 animate-spin rounded-full border-2 border-[#CBEAF1] border-t-[#126F91]"></span>
            </div>
        </div>

        {{-- ══════ HỎI LẠI TRƯỚC KHI NỘP ══════ --}}
        <div x-cloak x-show="confirmOpen" x-transition.opacity @keydown.escape.window="confirmOpen = false"
             class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <h2 class="text-base font-extrabold text-[#123B68]">Nộp bài thi ngay?</h2>
                <p class="mt-2 text-[12px] leading-5 text-[#607A90]">
                    Bạn đã trả lời <span class="font-bold text-[#126F91]" x-text="answeredCount()"></span>/{{ $totalCount }} câu.
                    <span x-show="answeredCount() < {{ max($totalCount, 1) }}" class="font-bold text-[#A4621B]">Vẫn còn câu chưa trả lời.</span>
                </p>
                <p class="mt-2 rounded-lg border border-[#EAD9A8] bg-[#FFF8E8] px-3 py-2 text-[11px] leading-5 text-[#7C541C]">
                    Cuộc thi chỉ cho nộp <span class="font-bold">một lần</span> — nộp xong không mở lại vòng thi này được nữa.
                </p>
                <div class="mt-5 flex gap-2">
                    <button type="button" @click="confirmOpen = false" class="flex-1 rounded-xl border border-[#DDEAF0] bg-white px-4 py-2.5 text-[12px] font-bold text-[#45657D] transition hover:bg-[#F4F9FB]">Làm tiếp</button>
                    <button type="button" @click="confirmOpen = false; doSubmit()" class="flex-1 rounded-xl bg-blue-600 px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-blue-700">Nộp bài thi</button>
                </div>
            </div>
        </div>

        {{-- ══════ SỬA 19/9 (6) — HỎI LẠI TRƯỚC KHI RỜI PHÒNG THI ══════
             Câu chữ cố ý nói THẲNG điều quan trọng nhất: bài đã lưu, NHƯNG ĐỒNG HỒ VẪN CHẠY.
             Đây là luật thật ở máy chủ (AttemptService::deadlineFor() tính từ lúc BẮT ĐẦU
             làm bài, không phải từ lúc mở trang), nên nếu chỉ ghi "bài đã được lưu" là nói
             nửa sự thật, thí sinh sẽ tưởng rời đi bao lâu cũng được. --}}
        <div x-cloak x-show="leaveOpen" x-transition.opacity @keydown.escape.window="leaveOpen = false"
             class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-[#FFF3D9] text-[#A4621B]">
                        <x-lucide name="alert-triangle" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-base font-extrabold text-[#123B68]">Rời phòng thi khi đang làm bài?</h2>
                        <p class="mt-1 text-[12px] leading-5 text-[#607A90]">Bài làm của bạn đã được lưu, nhưng vòng thi vẫn tiếp tục chạy.</p>
                    </div>
                </div>

                <template x-if="deadlineAt !== null">
                    <div class="mt-4 flex items-center justify-between gap-3 rounded-xl border border-[#EAD9A8] bg-[#FFF8E8] px-3 py-2.5">
                        <span class="text-[11px] font-bold text-[#7C541C]">Thời gian còn lại</span>
                        <span class="text-base font-black tabular-nums text-[#A4621B]" x-text="remainingLabel"></span>
                    </div>
                </template>

                <ul class="mt-3 space-y-1.5 text-[12px] leading-5 text-[#45657D]">
                    <li class="flex gap-2"><x-lucide name="check" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#2F8A6B]" /><span>Câu trả lời đã nhập <span class="font-bold">được giữ nguyên</span>, quay lại là làm tiếp.</span></li>
                    <li class="flex gap-2"><x-lucide name="clock" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#A4621B]" /><span><span class="font-bold">Đồng hồ vẫn chạy</span> trong lúc bạn rời đi — thời gian không được cộng bù.</span></li>
                    <li class="flex gap-2"><x-lucide name="lock-keyhole" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-[#9B2C2C]" /><span>Hết giờ, hệ thống <span class="font-bold">tự nộp bài</span> và bạn <span class="font-bold">không vào lại được</span>.</span></li>
                </ul>

                <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                    <button type="button" @click="leaveOpen = false"
                            class="flex-1 rounded-xl bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77]">
                        Ở lại làm bài
                    </button>
                    <button type="button" @click="leaveNow()"
                            class="flex-1 rounded-xl border border-[#DDEAF0] bg-white px-4 py-2.5 text-[12px] font-bold text-[#45657D] transition hover:bg-[#F4F9FB]">
                        Vẫn rời phòng thi
                    </button>
                </div>
            </div>
        </div>

        {{-- Form bọc TOÀN BỘ phiếu trả lời — tên trường giữ nguyên như màn cũ. --}}
        <form method="POST" action="{{ route('student.assessment.take.submit', $attempt->id) }}" id="take-pdf-form"
              x-ref="examForm" class="flex min-h-0 flex-1 flex-col">
            @csrf

            {{-- ══════════════════ 1. THẺ TIÊU ĐỀ ══════════════════ --}}
            <header class="shrink-0 p-2 sm:p-3">
                <div class="rounded-2xl border border-sky-100 bg-white px-3 py-2.5 shadow-[0_3px_12px_rgba(28,91,121,0.05)] sm:px-4 sm:py-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ $contestRoomUrl }}" @click.prevent="askLeave('{{ $contestRoomUrl }}')"
                           aria-label="Rời phòng thi" title="Rời phòng thi"
                           class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600 transition hover:bg-blue-100">
                            <x-lucide name="clipboard-check" class="h-5 w-5" />
                        </a>

                        <div class="min-w-0 flex-1">
                            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                <h1 class="min-w-0 truncate text-base font-extrabold text-[#123B68] sm:text-lg">{{ $assessmentTitle }}</h1>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-md bg-[#FFF1CF] px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-[#8D6A1A]">
                                    <x-lucide name="trophy" class="h-3 w-3" />Thi đấu
                                </span>
                            </div>
                            <p class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-[#607A90]">
                                <span class="min-w-0 truncate font-semibold text-[#45657D]">{{ $contestTitle }} · {{ $contestRoundLabel }}</span>
                                <span class="text-slate-300">·</span>
                                <span>{{ $resubmissionNote }}</span>
                                <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-[#2F8A6B]">
                                    <x-lucide name="save" class="h-3 w-3" />
                                    <span x-text="saving ? 'Đang lưu…' : 'Đã lưu tự động ✓'">Đã lưu tự động ✓</span>
                                </span>
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <template x-if="deadlineAt !== null">
                                <div class="inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-sm font-black tabular-nums transition-colors"
                                     :class="tone === 'danger' ? 'bg-[#F8D7DA] text-[#9B2C2C]' : (tone === 'warning' ? 'bg-[#FFF3D9] text-[#A4621B]' : 'bg-blue-50 text-blue-600')">
                                    <x-lucide name="clock" class="h-4 w-4" /><span x-text="remainingLabel"></span>
                                </div>
                            </template>

                            <a href="{{ $contestRoomUrl }}" @click.prevent="askLeave('{{ $contestRoomUrl }}')"
                               class="hidden items-center rounded-xl px-2 py-2 text-xs font-bold text-[#45657D] transition hover:bg-[#F4F9FB] lg:flex">
                                Thoát phòng thi
                            </a>

                            <button type="button" @click="confirmOpen = true" :disabled="expired || submitting"
                                    class="inline-flex min-h-10 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-wait disabled:opacity-60">
                                <x-lucide name="send" class="h-4 w-4" />Nộp bài thi
                            </button>
                        </div>
                    </div>

                    {{-- Thanh tiến độ: tỉ lệ câu đã trả lời --}}
                    <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-blue-600 transition-all duration-300"
                             :style="`width: ${(answeredCount() / {{ max($totalCount, 1) }}) * 100}%`"></div>
                    </div>
                </div>
            </header>

            {{-- ══════════════════ 2. HAI KHUNG ══════════════════ --}}
            <div class="grid min-h-0 flex-1 grid-cols-1 gap-2 p-2 sm:gap-3 sm:p-3 lg:grid-cols-2">

                {{-- ── Khung trái: đề PDF ── --}}
                <section class="order-1 flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-2xl border border-sky-100 bg-slate-800 shadow-[0_3px_12px_rgba(28,91,121,0.05)]">
                    {{-- Trình xem PDF của trình duyệt tự vẽ thanh công cụ riêng (số trang, thu
                         phóng, tải về) nên ở đây chỉ cần khung chứa — không tự dựng lại. --}}
                    <iframe src="{{ $pdfUrl }}" title="Đề thi PDF" class="h-full min-h-0 w-full flex-1 border-0"></iframe>
                </section>

                {{-- ── Khung phải: phiếu trả lời ── --}}
                <section class="order-2 flex min-h-0 min-w-0 flex-col overflow-hidden rounded-2xl border border-sky-100 bg-white shadow-[0_3px_12px_rgba(28,91,121,0.05)]">
                    <div class="flex shrink-0 flex-wrap items-center gap-2 border-b border-sky-100 px-3 py-2.5">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600"><x-lucide name="file-text" class="h-4 w-4" /></span>
                        <p class="min-w-0 text-[12px] text-[#607A90]">
                            <span class="font-extrabold text-[#123B68]"><span x-text="answeredCount()">0</span>/{{ $totalCount }} câu đã trả lời</span>
                            <span class="mx-1 text-slate-300">·</span>Xem đề PDF ở khung bên trái, chọn/nhập đáp án ở đây.
                        </p>
                        @if (count($navItems) > 1)
                            {{-- Chú giải màu cho thanh số câu ở đáy — không có chú giải thì xanh lá
                                 với xanh dương dễ bị hiểu nhầm là cùng một ý nghĩa. --}}
                            <span class="ml-auto hidden shrink-0 items-center gap-2 text-[10px] font-semibold text-[#7A92A3] sm:flex">
                                <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-blue-600"></span>Đang xem</span>
                                <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-[#2F8A6B]"></span>Đã trả lời</span>
                                <span class="inline-flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-full bg-slate-200"></span>Chưa</span>
                            </span>
                        @endif
                    </div>

                    <div class="min-h-0 flex-1 space-y-2.5 overflow-y-auto bg-[#F8FBFC] p-2.5 sm:p-3">

                        {{-- ── Câu trong phiếu đáp án ── --}}
                        @foreach ($answerRows as $row)
                            @php $meta = $typeMeta[$row['type']] ?? ['label' => $row['type'], 'icon' => 'pen-line']; @endphp
                            <article data-cau="{{ $row['no'] }}"
                                     class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] transition"
                                     :class="activeNo === {{ $row['no'] }} ? 'ring-2 ring-blue-200' : ''">
                                <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700">
                                        <x-lucide :name="$meta['icon']" class="h-3.5 w-3.5 shrink-0" />Câu {{ $row['no'] }} · {{ $meta['label'] }} · {{ $row['points'] }} điểm
                                    </span>
                                    <span x-show="answeredAnswerMap[{{ $row['answerKeyId'] }}]" x-cloak
                                          class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-bold text-[#2F8A6B]">
                                        <x-lucide name="check" class="h-3 w-3" />Đã lưu
                                    </span>
                                </div>

                                @if ($row['type'] === 'single_choice')
                                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @foreach (['A', 'B', 'C', 'D'] as $letter)
                                            <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-sky-100 px-3 py-2.5 text-[13px] font-semibold text-[#45657D] transition-colors hover:border-blue-300 has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                                                <input type="radio" name="answers[answer_keys][{{ $row['answerKeyId'] }}]" value="{{ $letter }}"
                                                       :disabled="expired"
                                                       @checked($row['submittedAnswer'] === $letter)
                                                       @change="activeNo = {{ $row['no'] }}; onAnswerKey({{ $row['answerKeyId'] }}, $event.target.value, true)"
                                                       class="h-3.5 w-3.5 accent-blue-600">
                                                {{ $letter }}
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($row['type'] === 'true_false_group')
                                    <div class="space-y-1.5" x-data="{ parts: @js(collect(['a', 'b', 'c', 'd'])->mapWithKeys(fn ($p) => [$p => $row['submittedAnswer'][$p] ?? null])->all()) }">
                                        @foreach (['a', 'b', 'c', 'd'] as $part)
                                            <div class="flex items-center justify-between gap-3 rounded-xl border border-sky-100 px-3 py-2">
                                                <span class="text-[13px] font-semibold text-[#45657D]">Ý {{ strtoupper($part) }}</span>
                                                <div class="flex shrink-0 gap-1.5">
                                                    <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-sky-100 px-3 py-1.5 text-[12px] font-semibold text-[#45657D] has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                                                        <input type="radio" name="answers[answer_keys][{{ $row['answerKeyId'] }}][{{ $part }}]" value="1"
                                                               :disabled="expired" :checked="parts.{{ $part }} === true"
                                                               @change="activeNo = {{ $row['no'] }}; parts.{{ $part }} = true; onAnswerKey({{ $row['answerKeyId'] }}, parts, true)"
                                                               class="h-3 w-3 accent-blue-600">Đúng
                                                    </label>
                                                    <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-sky-100 px-3 py-1.5 text-[12px] font-semibold text-[#45657D] has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                                                        <input type="radio" name="answers[answer_keys][{{ $row['answerKeyId'] }}][{{ $part }}]" value="0"
                                                               :disabled="expired" :checked="parts.{{ $part }} === false"
                                                               @change="activeNo = {{ $row['no'] }}; parts.{{ $part }} = false; onAnswerKey({{ $row['answerKeyId'] }}, parts, true)"
                                                               class="h-3 w-3 accent-blue-600">Sai
                                                    </label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @elseif ($row['type'] === 'true_false')
                                    <div class="flex gap-2">
                                        {{-- Lớp CSS viết NGUYÊN VĂN: Tailwind quét chuỗi tĩnh, ghép chuỗi sẽ không được sinh ra. --}}
                                        @foreach ([
                                            ['1', 'Đúng', 'has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-700'],
                                            ['0', 'Sai', 'has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 has-[:checked]:text-rose-700'],
                                        ] as [$value, $label, $checkedClass])
                                            <label class="flex flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-sky-100 px-3 py-2.5 text-[13px] font-semibold text-[#45657D] transition-colors {{ $checkedClass }}">
                                                <input type="radio" name="answers[answer_keys][{{ $row['answerKeyId'] }}]" value="{{ $value }}"
                                                       :disabled="expired"
                                                       @checked($row['submittedAnswer'] === ($value === '1'))
                                                       @change="activeNo = {{ $row['no'] }}; onAnswerKey({{ $row['answerKeyId'] }}, $event.target.value, true)"
                                                       class="h-3.5 w-3.5 accent-blue-600">
                                                {{ $label }}
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($row['type'] === 'multi_part')
                                    @php
                                        $submittedParts = is_array($row['submittedAnswer']) ? $row['submittedAnswer'] : [];
                                        $initialParts = collect($row['parts'])
                                            ->mapWithKeys(fn ($p) => [$p['part'] => $submittedParts[$p['part']] ?? null])
                                            ->all();
                                    @endphp
                                    <div class="space-y-1.5" x-data="{ vals: @js($initialParts) }">
                                        @foreach ($row['parts'] as $p)
                                            @php $part = $p['part']; @endphp
                                            <div class="flex flex-wrap items-center gap-2.5 rounded-xl border border-sky-100 px-3 py-2">
                                                <span class="w-10 shrink-0 text-[13px] font-semibold text-[#45657D]">Ý {{ strtoupper($part) }}</span>

                                                @if ($p['type'] === 'single_choice')
                                                    <div class="flex gap-1.5">
                                                        @foreach (['A', 'B', 'C', 'D'] as $letter)
                                                            <label class="cursor-pointer rounded-lg border border-sky-100 px-3 py-1.5 text-[12px] font-semibold text-[#45657D] has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                                                                <input type="radio" class="hidden" name="answers[answer_keys][{{ $row['answerKeyId'] }}][{{ $part }}]" value="{{ $letter }}"
                                                                       :disabled="expired"
                                                                       @checked(($submittedParts[$part] ?? null) === $letter)
                                                                       @change="activeNo = {{ $row['no'] }}; vals['{{ $part }}'] = '{{ $letter }}'; onAnswerKey({{ $row['answerKeyId'] }}, vals, true)">
                                                                {{ $letter }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @elseif ($p['type'] === 'true_false')
                                                    <div class="flex gap-1.5">
                                                        @foreach ([['1', 'Đúng'], ['0', 'Sai']] as [$value, $label])
                                                            <label class="cursor-pointer rounded-lg border border-sky-100 px-3 py-1.5 text-[12px] font-semibold text-[#45657D] has-[:checked]:border-blue-400 has-[:checked]:bg-blue-50">
                                                                <input type="radio" class="hidden" name="answers[answer_keys][{{ $row['answerKeyId'] }}][{{ $part }}]" value="{{ $value }}"
                                                                       :disabled="expired"
                                                                       @checked(($submittedParts[$part] ?? null) === ($value === '1'))
                                                                       @change="activeNo = {{ $row['no'] }}; vals['{{ $part }}'] = '{{ $value }}'; onAnswerKey({{ $row['answerKeyId'] }}, vals, true)">
                                                                {{ $label }}
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <input type="text" name="answers[answer_keys][{{ $row['answerKeyId'] }}][{{ $part }}]"
                                                           value="{{ $submittedParts[$part] ?? '' }}" placeholder="Nhập số…"
                                                           :disabled="expired"
                                                           @input.debounce.700ms="activeNo = {{ $row['no'] }}; vals['{{ $part }}'] = $event.target.value; onAnswerKey({{ $row['answerKeyId'] }}, vals, true)"
                                                           class="min-w-[120px] flex-1 rounded-xl border border-sky-100 p-2 text-[13px] text-[#123B68] transition focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50">
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <input type="text" name="answers[answer_keys][{{ $row['answerKeyId'] }}]" value="{{ $row['submittedAnswer'] }}"
                                           placeholder="Nhập đáp án (số)…"
                                           :disabled="expired"
                                           @input.debounce.700ms="activeNo = {{ $row['no'] }}; onAnswerKey({{ $row['answerKeyId'] }}, $event.target.value, $event.target.value.trim() !== '')"
                                           class="w-full rounded-xl border border-sky-100 p-2.5 text-[13px] text-[#123B68] transition focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50 disabled:text-slate-400">
                                @endif
                            </article>
                        @endforeach

                        {{-- ── Bài lập trình đính kèm đề PDF ── --}}
                        @foreach (array_values($codingRows) as $idx => $row)
                            @php $codingNo = $codingStartNo + $idx; @endphp
                            <article data-cau="{{ $codingNo }}"
                                     class="rounded-2xl border border-sky-100 bg-white p-3 shadow-[0_3px_12px_rgba(28,91,121,0.05)] transition"
                                     :class="activeNo === {{ $codingNo }} ? 'ring-2 ring-blue-200' : ''"
                                     x-data="{ language: @js($row['language'] ?? ($row['allowedLanguages'][0] ?? 'cpp')), code: @js($row['codeSource'] ?? '') }">
                                <div class="mb-2.5 flex flex-wrap items-center justify-between gap-2">
                                    <span class="inline-flex min-w-0 items-center gap-1.5 rounded-lg bg-blue-50 px-2.5 py-1 text-[11px] font-bold text-blue-700">
                                        <x-lucide name="code-2" class="h-3.5 w-3.5 shrink-0" /><span class="truncate">Câu {{ $codingNo }} · {{ $row['code'] }} · {{ $row['title'] }} · {{ $row['points'] }} điểm{{ $row['pdfPage'] ? ' · Trang '.$row['pdfPage'] : '' }}</span>
                                    </span>
                                    <span x-show="answeredCodingMap[{{ $row['codingItemId'] }}]" x-cloak
                                          class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-emerald-50 px-2 py-1 text-[11px] font-bold text-[#2F8A6B]">
                                        <x-lucide name="check" class="h-3 w-3" />Đã lưu
                                    </span>
                                </div>

                                <select name="answers[coding_items][{{ $row['codingItemId'] }}][language]" x-model="language"
                                        :disabled="expired"
                                        @change="activeNo = {{ $codingNo }}; onCodingItem({{ $row['codingItemId'] }}, { code_source: code, language }, code.trim() !== '')"
                                        class="mb-2 w-full rounded-xl border border-sky-100 p-2.5 text-[13px] font-semibold text-[#123B68] disabled:bg-slate-50 disabled:text-slate-400">
                                    @foreach (($row['allowedLanguages'] ?: ['cpp', 'python']) as $lang)
                                        <option value="{{ $lang }}">{{ $lang }}</option>
                                    @endforeach
                                </select>
                                <textarea name="answers[coding_items][{{ $row['codingItemId'] }}][code_source]" x-model="code" rows="10"
                                          placeholder="Viết code ở đây…" spellcheck="false"
                                          :disabled="expired"
                                          @input.debounce.700ms="activeNo = {{ $codingNo }}; onCodingItem({{ $row['codingItemId'] }}, { code_source: code, language }, code.trim() !== '')"
                                          class="w-full rounded-xl border border-sky-100 bg-white p-2.5 font-mono text-[12px] leading-5 text-[#123B68] transition focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:bg-slate-50 disabled:text-slate-400"></textarea>
                                <p class="mt-2 text-[11px] leading-5 text-[#7A92A3]">Bài lập trình được chấm sau khi ban tổ chức chạy máy chấm — điểm công bố cùng kết quả vòng thi.</p>
                            </article>
                        @endforeach

                        @if ($totalCount === 0)
                            <div class="rounded-2xl border border-dashed border-sky-100 bg-white px-3 py-10 text-center text-[12px] text-[#7A92A3]">
                                Đề này chưa có phiếu đáp án — báo ban tổ chức kiểm tra lại đề của vòng thi.
                            </div>
                        @endif

                        <p class="px-1 pb-1 text-center text-[11px] leading-5 text-[#7A92A3]">
                            Câu trả lời tự động lưu ngay khi bạn chọn/nhập — chỉ cần bấm <span class="font-bold text-blue-600">Nộp bài thi</span> khi làm xong.
                        </p>
                    </div>

                    {{-- ── Thanh chuyển câu ở đáy ── --}}
                    @if (count($navItems) > 1)
                        <nav aria-label="Chuyển câu" class="flex shrink-0 items-center justify-between gap-2 border-t border-sky-100 bg-white px-2.5 py-2">
                            <button type="button" @click="step(-1)" :disabled="isFirst()"
                                    class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-xl border border-sky-100 px-3 py-2 text-[12px] font-bold text-[#45657D] transition hover:bg-[#F4F9FB] disabled:cursor-not-allowed disabled:opacity-40">
                                <x-lucide name="chevron-left" class="h-4 w-4" />Câu trước
                            </button>

                            <div class="no-scrollbar flex min-w-0 flex-1 items-center justify-center gap-1 overflow-x-auto px-1">
                                {{-- 3 trạng thái, khớp đúng quy ước của phòng thi câu lập trình
                                     (take.blade.php): xanh dương đặc = câu ĐANG XEM, xanh lá đặc =
                                     ĐÃ TRẢ LỜI, chữ xám = CHƯA. Trạng thái "đã trả lời" đọc thẳng
                                     bản đồ answeredAnswerMap/answeredCodingMap nên nó sáng lên NGAY
                                     lúc thí sinh chọn đáp án, không phải đợi tải lại trang. --}}
                                @foreach ($navItems as $item)
                                    <button type="button" @click="goTo({{ $item['no'] }})"
                                            :aria-current="activeNo === {{ $item['no'] }} ? 'true' : 'false'"
                                            :title="'Câu {{ $item['no'] }} · ' + ({{ $item['doneExpr'] }} ? 'đã trả lời' : 'chưa trả lời')"
                                            class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[12px] font-bold transition"
                                            :class="activeNo === {{ $item['no'] }}
                                                ? 'bg-blue-600 text-white shadow-sm'
                                                : ({{ $item['doneExpr'] }}
                                                    ? 'bg-[#2F8A6B] text-white hover:bg-[#28795E]'
                                                    : 'text-[#45657D] hover:bg-[#F4F9FB]')">{{ $item['no'] }}</button>
                                @endforeach
                            </div>

                            <button type="button" @click="step(1)" :disabled="isLast()"
                                    class="inline-flex min-h-9 shrink-0 items-center gap-1 rounded-xl bg-blue-600 px-3 py-2 text-[12px] font-bold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40">
                                Câu tiếp <x-lucide name="chevron-right" class="h-4 w-4" />
                            </button>
                        </nav>
                    @endif
                </section>
            </div>
        </form>
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        .no-scrollbar { scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>
    @include('partials.exam-pdf-workspace-script')
@endpush
