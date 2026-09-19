{{--
    PHÒNG THI CUỘC THI — màn làm bài RIÊNG cho cuộc thi (khách yêu cầu 19/9: "vào thi thì mở
    màn làm bài như bên luyện tập, nhưng xây riêng UI dành cho cuộc thi").

    Lớp vỏ lấy đúng bản mẫu AssessmentModal (giống student/practice/exercise-play.blade.php và
    student/assessment/take.blade.php): khung phủ toàn màn hình, rail tab dọc, nền sáng/tối.

    KHÁC màn làm bài thường ĐÚNG ở phần nhận diện cuộc thi:
      · Thanh tiêu đề ghi tên CUỘC THI + VÒNG THI, có huy hiệu "Đang thi đấu".
      · Thoát ra là về KHÔNG GIAN THI của cuộc thi, không phải trang Luyện tập.
      · Tab "Thể lệ" thay cho "Hướng dẫn"/"Bài mẫu": in thể lệ thật admin nhập + giờ vòng thi.
      · Nút nộp ghi "Nộp bài thi" và hộp xác nhận nói rõ cuộc thi chỉ cho nộp 1 lần.

    LOGIC GIỮ NGUYÊN TUYỆT ĐỐI — không có một luật nghiệp vụ nào viết lại ở đây:
      · Dữ liệu do Student\AssessmentService::buildTakeData() dựng (y như route take cũ).
      · Tự lưu  -> student.assessment.take.save
      · Chạy thử -> student.assessment.take.run
      · Nộp bài  -> student.assessment.take.submit (form ẩn, tên trường giữ nguyên)
      · Toàn bộ JS dùng CHUNG partials/exam-workspace-script (tách ra từ take.blade.php,
        chép nguyên văn) — sửa ở đó là cả hai màn cùng đúng, không có bản sao nào lệch nhau.
--}}
@extends('layouts.exam')

@section('title', 'Thi đấu · '.($contestTitle ?? 'Cuộc thi'))

@section('content')
    @php
        $questions = $questions ?? [];
        $assessmentTitle = $assessmentModel->title ?? 'Đề thi';
        $examCode = $examCode ?? null;
        $totalPoints = $totalPoints ?? collect($questions)->sum('points');

        // Bối cảnh cuộc thi — examContext() luôn truyền đủ; ?? chỉ để view không vỡ nếu ai đó
        // render nhầm từ chỗ khác.
        $contestTitle = $contestTitle ?? 'Cuộc thi';
        $contestRoundLabel = $contestRoundLabel ?? 'Vòng thi';
        $contestRoundShort = $contestRoundShort ?? 'Vòng thi';
        $contestStatusLabel = $contestStatusLabel ?? 'Đang diễn ra';
        $contestTimeRange = $contestTimeRange ?? 'Chưa xếp lịch';
        $contestEndsAtLabel = $contestEndsAtLabel ?? null;
        $contestRules = $contestRules ?? null;
        $contestRoomUrl = $contestRoomUrl ?? route('competitions.index');
        $contestLeaderboardUrl = $contestLeaderboardUrl ?? route('leaderboard.index');

        // Dữ liệu tối thiểu cho Alpine — giống hệt take.blade.php (hợp đồng của
        // partials/exam-workspace-script). Phần hiển thị đã dựng sẵn bằng Blade ở dưới.
        $vm = collect($questions)->map(fn ($q) => [
            'id' => $q['questionId'],
            'no' => $q['no'],
            'kind' => $q['kind'],
        ])->values();

        $firstId = $vm->first()['id'] ?? null;
        $lastId = $vm->last()['id'] ?? null;
        $questionCount = count($questions);
    @endphp

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div class="assessment-modal fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-0 sm:p-2"
         x-data="examWorkspace({
             deadlineAt: @js($deadlineAt ?? null),
             serverNow: @js($serverNow ?? null),
             saveUrl: @js(route('student.assessment.take.save', $attempt->id)),
             runUrl: @js(route('student.assessment.take.run', $attempt->id)),
             questions: @js($vm),
             answers: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => $q['kind'] === 'fill' ? (string) ($q['textAnswer'] ?? '') : (($q['selectedOption'] === null) ? '' : (string) $q['selectedOption'])])),
             codes: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => (string) ($q['codeSource'] ?? '')])),
             languages: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => $q['language'] ?: 'cpp'])),
             firstId: @js($firstId),
             lastId: @js($lastId),
             warnOnLeave: true,
         })"
         x-init="init()">

        <div class="assessment-modal-shell flex h-full w-full max-w-none flex-col overflow-hidden bg-[#F8FBFC] shadow-2xl sm:h-[calc(100dvh-16px)] sm:max-w-[calc(100vw-16px)] sm:rounded-xl">

        {{-- ══════ LỚP PHỦ HẾT GIỜ (hành vi y hệt màn làm bài thường: chặn thật + tự nộp) ══════ --}}
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
                    Bạn đã trả lời <span class="font-bold text-[#126F91]" x-text="answeredCount()"></span>/{{ $questionCount }} câu.
                    <span x-show="answeredCount() < {{ $questionCount }}" class="font-bold text-[#A4621B]">Vẫn còn câu chưa trả lời.</span>
                </p>
                <p class="mt-2 rounded-lg border border-[#EAD9A8] bg-[#FFF8E8] px-3 py-2 text-[11px] leading-5 text-[#7C541C]">
                    Cuộc thi chỉ cho nộp <span class="font-bold">một lần</span> — nộp xong không mở lại vòng thi này được nữa.
                </p>
                <div class="mt-5 flex gap-2">
                    <button type="button" @click="confirmOpen = false" class="flex-1 rounded-xl border border-[#DDEAF0] bg-white px-4 py-2.5 text-[12px] font-bold text-[#45657D] transition hover:bg-[#F4F9FB]">Làm tiếp</button>
                    <button type="button" @click="confirmOpen = false; doSubmit()" class="flex-1 rounded-xl bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77]">Nộp bài thi</button>
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

        {{-- ══════════════════════════ HEADER ══════════════════════════ --}}
        <header class="assessment-modal-header flex shrink-0 items-center gap-2 border-b border-[#DDEAF0] bg-white px-3 py-2 sm:px-4">
            {{-- href giữ nguyên để bấm chuột giữa / mở tab mới vẫn đúng; .prevent chỉ chặn cú bấm thường. --}}
            <a href="{{ $contestRoomUrl }}" @click.prevent="askLeave('{{ $contestRoomUrl }}')"
               aria-label="Rời phòng thi" title="Rời phòng thi"
               class="rounded-xl p-2 text-[#607A90] transition hover:bg-[#F4F9FB]"><x-lucide name="x" class="h-5 w-5" /></a>

            <div class="min-w-0 flex-1">
                <p class="flex min-w-0 flex-wrap items-center gap-x-1.5 text-[10px] font-bold uppercase tracking-wider text-[#126F91]">
                    <span class="inline-flex items-center gap-1 rounded-md bg-[#FFF1CF] px-1.5 py-0.5 text-[9px] text-[#8D6A1A]">
                        <x-lucide name="trophy" class="h-3 w-3" />Thi đấu
                    </span>
                    <span class="min-w-0 truncate">{{ $contestTitle }}</span>
                    <span class="text-[#9DB6C4]">·</span>
                    <span class="min-w-0 truncate text-[#45657D]">{{ $contestRoundLabel }}</span>
                </p>
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <h2 class="min-w-0 truncate text-sm font-extrabold text-[#123B68] sm:text-base">{{ $assessmentTitle }}</h2>
                    <span class="shrink-0 rounded-lg bg-[#EAF5F8] px-2 py-1 text-[10px] font-bold text-[#126F91]">@if ($examCode){{ $examCode }} · @endif{{ $totalPoints }} điểm</span>
                    @if ($questionCount > 1)
                        <select x-model.number="activeId" :disabled="expired || submitting" aria-label="Chọn câu trong đề thi"
                                class="min-w-[150px] max-w-[220px] rounded-lg bg-white px-2 py-1 text-[10px] font-bold text-[#45657D] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                            @foreach ($questions as $q)
                                <option value="{{ $q['questionId'] }}">Câu {{ $q['no'] }} · {{ $q['title'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>

            {{-- Dải số câu: xanh đậm = đang xem, xanh lá = đã trả lời, trắng = chưa --}}
            @if ($questionCount > 1)
                <div class="hidden min-w-0 max-w-full items-center gap-1 rounded-xl bg-[#F4F8FB] px-1.5 py-1.5 md:flex" aria-label="Tiến độ câu hỏi">
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
                <x-lucide name="save" class="h-4 w-4" /><span x-text="saving ? 'Đang lưu…' : 'Đã lưu bài'"></span>
            </span>

            {{-- Nút chữ "Thoát phòng thi" — đứng cạnh nút nộp, giống "Thoát bài tập" của màn luyện tập. --}}
            <a href="{{ $contestRoomUrl }}" @click.prevent="askLeave('{{ $contestRoomUrl }}')"
               class="hidden shrink-0 items-center rounded-xl px-2 py-2 text-xs font-bold text-[#45657D] transition hover:bg-[#F4F9FB] sm:flex">
                Thoát phòng thi
            </a>

            <button type="button" @click="confirmOpen = true" :disabled="expired || submitting"
                    class="flex shrink-0 items-center gap-1.5 rounded-xl bg-[#126F91] px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0D5B77] disabled:cursor-wait disabled:opacity-60">
                <x-lucide name="send" class="h-4 w-4" />Nộp bài thi
            </button>
        </header>

        {{-- ══════ DẢI THÔNG TIN VÒNG THI ══════ --}}
        <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-1 border-b border-[#E4EFF3] bg-[#F4F9FB] px-3 py-1.5 text-[10px] font-semibold text-[#45657D] sm:px-4">
            <span class="inline-flex items-center gap-1"><x-lucide name="calendar-days" class="h-3 w-3 shrink-0 text-[#126F91]" />{{ $contestTimeRange }}</span>
            <span class="inline-flex items-center gap-1"><x-lucide name="target" class="h-3 w-3 shrink-0 text-[#126F91]" />{{ $questionCount }} câu · {{ $totalPoints }} điểm</span>
            <span class="inline-flex items-center gap-1 text-[#2F8A6B]"><x-lucide name="shield-check" class="h-3 w-3 shrink-0" />{{ $contestStatusLabel }}</span>
            <a href="{{ $contestRoomUrl }}" class="ml-auto inline-flex items-center gap-1 text-[#126F91] hover:underline">
                Không gian thi <x-lucide name="chevron-right" class="h-3 w-3 shrink-0" />
            </a>
        </div>

        {{-- ═══════════════════ RAIL TAB + NỘI DUNG ═══════════════════ --}}
        <div class="assessment-modal-main flex min-h-0 flex-1 flex-col md:flex-row">
            <aside class="assessment-modal-tabs shrink-0 border-b border-[#DDEAF0] bg-white md:w-12 md:border-b-0 md:border-r">
                <div class="grid h-full grid-cols-4 gap-1 p-1.5 md:flex md:flex-col md:gap-1 md:p-2">
                    @foreach ([['pdf', 'Đề bài PDF'], ['work', 'Làm bài'], ['guide', 'Hướng dẫn'], ['rules', 'Thể lệ']] as [$tabId, $tabLabel])
                        <button type="button" @click="activeTab = '{{ $tabId }}'" title="{{ $tabLabel }}" aria-label="{{ $tabLabel }}"
                                class="flex min-h-9 min-w-0 items-center justify-center rounded-lg px-1.5 py-1.5 text-center transition md:min-h-[56px] md:w-full md:flex-col md:justify-center"
                                :class="activeTab === '{{ $tabId }}' ? 'bg-[#126F91] text-white shadow-sm' : 'text-[#45657D] hover:bg-[#F4F9FB]'">
                            <span class="min-w-0"><span class="block text-[10px] font-extrabold leading-tight md:rotate-180 md:[writing-mode:vertical-rl]">{{ $tabLabel }}</span></span>
                        </button>
                    @endforeach
                </div>
            </aside>

            <main class="assessment-modal-content min-w-0 flex-1 overflow-hidden">

                {{-- ───────── TAB: ĐỀ BÀI PDF ───────── --}}
                <section x-show="activeTab === 'pdf'" x-cloak class="assessment-pdf-surface h-full min-h-0 overflow-y-auto bg-[#EAF4F8] p-2 sm:p-3">
                    @foreach ($questions as $q)
                        <div x-show="activeId === {{ $q['questionId'] }}" class="min-h-full">
                            @if ($q['statementPdfUrl'])
                                <div data-pdf-fit data-pdf-url="{{ $q['statementPdfUrl'] }}" class="min-h-full"></div>
                            @else
                                <div class="grid h-full min-h-[320px] place-items-center p-8 text-center">
                                    <div>
                                        <span class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-white text-[#126F91]"><x-lucide name="file-text" class="h-5 w-5" /></span>
                                        <p class="mt-3 text-sm font-extrabold text-[#123B68]">Câu này không có bản PDF</p>
                                        <p class="mt-1 text-[11px] text-[#607A90]">Toàn bộ nội dung đề nằm ở tab <span class="font-bold">Làm bài</span>.</p>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </section>

                {{-- ───────── TAB: LÀM BÀI ───────── --}}
                <section x-show="activeTab === 'work'" class="assessment-work-panel flex h-full min-h-0 flex-col overflow-hidden p-1.5 sm:p-2">
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 px-1">
                        <span class="truncate text-[11px] font-bold uppercase tracking-[.12em] text-[#7A92A3]" x-text="currentKind() === 'code' ? 'Soạn mã' : 'Trả lời câu hỏi'"></span>
                        <div class="flex items-center gap-2">
                            @foreach ($questions as $qq)
                                <span x-show="activeId === {{ $qq['questionId'] }}" class="text-[11px] font-bold text-[#7A92A3]">{{ $qq['points'] }} điểm</span>
                            @endforeach
                            <button type="button" @click="goPrev()" :disabled="activeId === firstId"
                                    class="grid h-7 w-7 place-items-center rounded-lg text-[#607A90] transition hover:bg-white disabled:opacity-30" aria-label="Câu trước" title="Câu trước"><x-lucide name="chevron-left" class="h-4 w-4" /></button>
                            <button type="button" @click="goNext()" :disabled="activeId === lastId"
                                    class="grid h-7 w-7 place-items-center rounded-lg bg-white text-[#126F91] transition hover:bg-[#EAF5F8] disabled:opacity-30" aria-label="Câu tiếp" title="Câu tiếp"><x-lucide name="chevron-right" class="h-4 w-4" /></button>
                        </div>
                    </div>

                    <div class="mt-2 min-h-0 flex-1 overflow-y-auto lg:overflow-hidden">
                        @foreach ($questions as $q)
                            @php $qid = $q['questionId']; @endphp
                            <div x-show="activeId === {{ $qid }}" class="min-h-full">
                                @if ($q['kind'] === 'code')
                                    <div class="grid min-h-full gap-2 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,0.9fr)]">
                                        {{-- ── Trình soạn mã ── --}}
                                        <section class="flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-xl bg-[#F4F9FB]">
                                            <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 bg-white px-3 py-2.5 text-[#123B68] sm:px-4">
                                                {{-- Chỉ 2 ngôn ngữ vì máy chấm CHỈ nhận 2 (config/judge0.php). --}}
                                                <select x-model="languages[{{ $qid }}]" @change="onLanguageChange({{ $qid }})" :disabled="expired"
                                                        aria-label="Chọn ngôn ngữ lập trình"
                                                        class="rounded-lg bg-[#F4F9FB] px-2 py-1.5 text-[10px] font-bold text-[#123B68] outline-none ring-1 ring-inset ring-[#DDEAF0] focus:ring-2 focus:ring-[#126F91]">
                                                    <option value="cpp">C++17</option>
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

                                            <div class="shrink-0 px-4 pb-3 pt-3 text-xs leading-5 text-[#45657D]">
                                                <p class="font-bold text-[#123B68]">Câu {{ $q['no'] }} · {{ $q['typeLabel'] }} · {{ $q['points'] }} điểm</p>
                                                <p class="mt-1">{{ $q['title'] }}</p>
                                                @if ($q['statementPdfUrl'])
                                                    <p class="mt-1 text-[11px] text-[#7A92A3]">Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                                @elseif ($q['body'])
                                                    <div class="rich-content mt-1 max-h-[26vh] overflow-y-auto">{!! $q['body'] !!}</div>
                                                @endif
                                            </div>

                                            {{-- Lớp tô màu cú pháp nằm dưới, textarea trong suốt nằm trên. --}}
                                            <div class="relative min-h-0 flex-1 overflow-hidden">
                                                <pre aria-hidden="true" x-ref="hl{{ $qid }}"
                                                     class="pointer-events-none absolute inset-0 z-20 overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6"><code x-html="highlight(codes[{{ $qid }}], languages[{{ $qid }}])"></code></pre>
                                                <textarea data-code-source x-model="codes[{{ $qid }}]" :disabled="expired" spellcheck="false"
                                                          @scroll="syncScroll($event, 'hl{{ $qid }}')"
                                                          @input.debounce.700ms="onCode({{ $qid }})"
                                                          aria-label="Trình soạn mã có tô màu cú pháp"
                                                          style="color: transparent; -webkit-text-fill-color: transparent;"
                                                          class="absolute inset-0 z-10 h-full w-full resize-none overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6 outline-none selection:bg-[#2F8A6B]/40"></textarea>
                                            </div>
                                        </section>

                                        {{-- ── INPUT / OUTPUT / GHI NHẬN ──
                                             Bố cục chép theo ĐÚNG màn luyện tập (bản mẫu khách gửi):
                                             ô INPUT có nút "Chạy test" màu xanh lá ở góc phải, ô OUTPUT
                                             ngay dưới, và nút "Ghi nhận bài làm" chạy hết chiều ngang ở
                                             đáy cột. KHÁC luyện tập ở chỗ nút đó KHÔNG chấm điểm — xem
                                             recordAnswer() trong partials/exam-workspace-script. --}}
                                        <div class="flex min-h-[420px] min-w-0 flex-col gap-2 overflow-hidden">
                                            <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl bg-[#EEF6F8]">
                                                <div class="flex shrink-0 items-center justify-between gap-2 px-3 py-2.5">
                                                    <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#126F91]">Input</span>
                                                    <button type="button" @click="runTest({{ $qid }})"
                                                            :disabled="expired || testRunning[{{ $qid }}]"
                                                            title="Chạy thử mã với dữ liệu vào bên dưới (không tính điểm)"
                                                            class="inline-flex items-center gap-1.5 rounded-lg bg-[#2F8A6B] px-2.5 py-1.5 text-[10px] font-bold text-white shadow-sm transition hover:bg-[#256F56] disabled:cursor-not-allowed disabled:opacity-50"><x-lucide name="play" class="h-3.5 w-3.5" /><span x-text="testRunning[{{ $qid }}] ? 'Đang chạy…' : 'Chạy test'">Chạy test</span></button>
                                                </div>
                                                {{-- KHÔNG đổ sẵn test từ database: test_cases là test CHẤM ĐIỂM, in ra đây
                                                     là đưa luôn dữ liệu chấm cho thí sinh giữa cuộc thi. --}}
                                                <textarea x-model="testInputs[{{ $qid }}]" :disabled="expired" spellcheck="false"
                                                          placeholder="Nhập dữ liệu vào để thử nghiệm…" aria-label="Dữ liệu đầu vào test"
                                                          class="min-h-0 flex-1 resize-none bg-white/80 px-3 py-3 font-mono text-[11px] leading-5 text-[#123B68] outline-none disabled:cursor-not-allowed disabled:opacity-60"></textarea>
                                            </section>

                                            <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl bg-[#F7F9FA]">
                                                <div class="flex shrink-0 items-center justify-between gap-2 px-3 py-2.5">
                                                    <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#607A90]">Output</span>
                                                    <span x-text="testStatus[{{ $qid }}]" class="text-[10px] font-bold text-[#7A92A3]"></span>
                                                </div>
                                                <pre x-text="testOutputs[{{ $qid }}]" class="min-h-0 flex-1 overflow-y-auto whitespace-pre-wrap bg-white/80 px-3 py-3 font-mono text-[11px] leading-5 text-[#45657D]">Chưa chạy test</pre>
                                            </section>

                                            <div class="shrink-0">
                                                <button type="button" @click="recordAnswer({{ $qid }})"
                                                        :disabled="expired || submitting || recording[{{ $qid }}]"
                                                        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77] disabled:cursor-not-allowed disabled:opacity-60">
                                                    <x-lucide name="send" class="h-4 w-4" /><span x-text="recording[{{ $qid }}] ? 'Đang ghi nhận…' : 'Ghi nhận bài làm'">Ghi nhận bài làm</span>
                                                </button>
                                                <p class="mt-1.5 text-center text-[10px] leading-4 text-[#7A92A3]"
                                                   x-text="recordStatus[{{ $qid }}] || 'Bài tự lưu khi bạn gõ. Điểm chỉ công bố sau khi ban tổ chức chấm.'"></p>
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    <div class="flex min-h-full flex-col gap-2 lg:grid lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,0.9fr)]">
                                        <section class="flex min-h-[420px] min-w-0 flex-col overflow-hidden rounded-xl bg-[#EEF6F8]">
                                            <div class="shrink-0 px-3 py-2.5 text-[10px] font-black uppercase tracking-[.12em] text-[#126F91]">Cách trả lời</div>
                                            <div class="min-h-0 flex-1 overflow-y-auto px-3 pb-3">
                                                <div class="mb-3 rounded-lg bg-white px-3 py-3">
                                                    <p class="text-[10px] font-bold uppercase tracking-wide text-[#7A92A3]">Câu {{ $q['no'] }} · {{ $q['typeLabel'] }} · {{ $q['points'] }} điểm</p>
                                                    <p class="mt-1 text-sm font-bold text-[#123B68]">{{ $q['title'] }}</p>
                                                    @if ($q['statementPdfUrl'])
                                                        <p class="mt-1 text-[11px] text-[#7A92A3]">Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                                    @elseif ($q['body'])
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

                                        <div class="flex min-w-0 flex-col gap-2">
                                            <section class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl bg-[#FFF8E8] p-3">
                                                <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#A4621B]">Trạng thái</span>
                                                <p class="mt-3 text-sm font-bold text-[#7C541C]" x-text="isAnswered({{ $qid }}) ? 'Đã nhập câu trả lời' : 'Chưa trả lời'"></p>
                                                <p class="mt-2 text-[11px] leading-5 text-[#967342]">{{ $q['kind'] === 'choice' ? 'Chọn một phương án phù hợp nhất.' : 'Kiểm tra lại đáp án trước khi nộp bài thi.' }}</p>
                                            </section>

                                            {{-- Câu không phải lập trình cũng có nút ghi nhận, để thao tác ở mọi
                                                 dạng câu giống nhau — thí sinh không phải học 2 cách. --}}
                                            <div class="shrink-0">
                                                <button type="button" @click="recordAnswer({{ $qid }})"
                                                        :disabled="expired || submitting || recording[{{ $qid }}]"
                                                        class="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77] disabled:cursor-not-allowed disabled:opacity-60">
                                                    <x-lucide name="send" class="h-4 w-4" /><span x-text="recording[{{ $qid }}] ? 'Đang ghi nhận…' : 'Ghi nhận bài làm'">Ghi nhận bài làm</span>
                                                </button>
                                                <p class="mt-1.5 text-center text-[10px] leading-4 text-[#7A92A3]"
                                                   x-text="recordStatus[{{ $qid }}] || 'Bài tự lưu khi bạn chọn. Điểm chỉ công bố sau khi ban tổ chức chấm.'"></p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- ───────── TAB: HƯỚNG DẪN ───────── --}}
                <section x-show="activeTab === 'guide'" x-cloak class="h-full min-h-0 overflow-y-auto p-2 sm:p-3">
                    <article class="min-h-full rounded-xl bg-white p-4 sm:p-6">
                        <h3 class="text-sm font-extrabold text-[#123B68]">Hướng dẫn làm bài thi</h3>
                        <h4 class="mt-3 text-[11px] font-black uppercase tracking-wide text-[#126F91]">Quy định làm bài</h4>
                        <ul class="mt-2 space-y-2 text-[13px] leading-7 text-[#45657D]">
                            <li>· Câu trả lời được <span class="font-bold">tự động lưu</span> ngay khi bạn nhập — không cần bấm nút lưu.</li>
                            <li>· Dải số câu trên đầu: xanh lá là câu đã trả lời, xanh đậm là câu đang xem.</li>
                            <li>· Nút <span class="font-bold">Chạy test</span> chỉ chạy thử với dữ liệu bạn tự gõ, <span class="font-bold">không tính điểm</span> và không tính là một lần nộp.</li>
                            <li>· Nút <span class="font-bold">Ghi nhận bài làm</span> lưu ngay câu đang làm lên máy chủ cho chắc — <span class="font-bold">không phải nộp bài</span>, bấm bao nhiêu lần cũng được.</li>
                            @if ($deadlineAt ?? null)
                                <li>· Hết giờ hệ thống <span class="font-bold">tự nộp</span> bài; đồng hồ tính theo giờ máy chủ, không theo giờ máy bạn.</li>
                            @endif
                            <li>· Mỗi thí sinh chỉ nộp <span class="font-bold">một lần</span> cho vòng thi này — nộp xong không mở lại được.</li>
                            <li>· Điểm và thứ hạng hiện ở <a href="{{ $contestRoomUrl }}" class="font-bold text-[#126F91] hover:underline">Không gian thi</a> sau khi ban tổ chức chấm xong.</li>
                        </ul>

                    </article>
                </section>

                {{-- ───────── TAB: THỂ LỆ CUỘC THI ───────── --}}
                <section x-show="activeTab === 'rules'" x-cloak class="h-full min-h-0 overflow-y-auto p-2 sm:p-3">
                    <article class="min-h-full rounded-xl bg-white p-4 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-extrabold text-[#123B68]">{{ $contestTitle }}</h3>
                            <span class="rounded-full bg-[#EAF5F8] px-2.5 py-1 text-[10px] font-bold text-[#126F91]">{{ $contestRoundLabel }}</span>
                        </div>

                        <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                            <div class="rounded-lg bg-[#F4F9FB] px-3 py-2">
                                <dt class="text-[9px] font-bold uppercase tracking-wide text-[#7A92A3]">Khung giờ vòng thi</dt>
                                <dd class="mt-0.5 text-[11px] font-bold text-[#123B68]">{{ $contestTimeRange }}</dd>
                            </div>
                            <div class="rounded-lg bg-[#F4F9FB] px-3 py-2">
                                <dt class="text-[9px] font-bold uppercase tracking-wide text-[#7A92A3]">Cấu trúc đề</dt>
                                <dd class="mt-0.5 text-[11px] font-bold text-[#123B68]">{{ $questionCount }} câu · {{ $totalPoints }} điểm</dd>
                            </div>
                        </dl>

                        @if ($contestRules)
                            <h4 class="mt-4 text-[11px] font-black uppercase tracking-wide text-[#126F91]">Thể lệ ban tổ chức công bố</h4>
                            {{-- Thể lệ là cột text thường (competitions.rules) — in nguyên văn, KHÔNG render
                                 HTML: nội dung do người nhập gõ, không phải trình soạn thảo có lọc mã. --}}
                            <p class="mt-2 whitespace-pre-line text-[13px] leading-7 text-[#45657D]">{{ $contestRules }}</p>
                        @else
                            {{-- Không bịa thể lệ: cột competitions.rules đang trống thì nói thẳng là trống. --}}
                            <p class="mt-4 rounded-lg border border-dashed border-[#DDEAF0] px-3 py-4 text-center text-[12px] leading-6 text-[#7A92A3]">
                                Ban tổ chức chưa đăng thể lệ riêng cho cuộc thi này.<br>Quy định làm bài xem ở tab <span class="font-bold text-[#126F91]">Hướng dẫn</span>.
                            </p>
                        @endif

                        <a href="{{ $contestLeaderboardUrl }}" class="mt-4 inline-flex items-center gap-1 text-[11px] font-bold text-[#126F91] hover:underline">
                            Xem bảng xếp hạng vòng này <x-lucide name="chevron-right" class="h-3 w-3 shrink-0" />
                        </a>
                    </article>
                </section>
            </main>
        </div>

        {{-- ══════ FORM NỘP THẬT — giữ NGUYÊN hợp đồng tên trường như màn làm bài thường ══════ --}}
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
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.exam-workspace-script')
@endpush
