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
             questions: @js($vm),
             answers: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => $q['kind'] === 'fill' ? (string) ($q['textAnswer'] ?? '') : (($q['selectedOption'] === null) ? '' : (string) $q['selectedOption'])])),
             codes: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => (string) ($q['codeSource'] ?? '')])),
             languages: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => $q['language'] ?: 'cpp'])),
             firstId: @js($firstId),
             lastId: @js($lastId),
         })"
         x-init="init()">

        <div class="assessment-modal-shell flex h-full w-full max-w-none flex-col overflow-hidden bg-[#F8FBFC] shadow-2xl sm:h-[calc(100dvh-16px)] sm:max-w-[calc(100vw-16px)] sm:rounded-xl">

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
            <a href="{{ route('student.practice.index') }}" aria-label="Thoát phòng thi" title="Thoát (bài làm đã tự lưu)"
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

            <button type="button" @click="confirmOpen = true" :disabled="expired || submitting"
                    class="flex shrink-0 items-center gap-1.5 rounded-xl bg-[#126F91] px-3 py-2 text-xs font-bold text-white shadow-sm transition hover:bg-[#0D5B77] disabled:cursor-wait disabled:opacity-60">
                <x-lucide name="send" class="h-4 w-4" />Nộp đề
            </button>
        </header>

        {{-- ═══════════════════ RAIL 4 TAB + NỘI DUNG ═══════════════════ --}}
        <div class="assessment-modal-main flex min-h-0 flex-1 flex-col md:flex-row">
            <aside class="assessment-modal-tabs shrink-0 border-b border-[#DDEAF0] bg-white md:w-12 md:border-b-0 md:border-r">
                <div class="grid h-full grid-cols-4 gap-1 p-1.5 md:flex md:flex-col md:gap-1 md:p-2">
                    @foreach ([['pdf', 'Đề bài PDF'], ['work', 'Làm bài'], ['guide', 'Hướng dẫn'], ['sample', 'Bài mẫu']] as [$tabId, $tabLabel])
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
                    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2">
                        <span class="truncate text-[10px] font-bold uppercase tracking-[.08em] text-[#7A92A3]" x-text="currentKind() === 'code' ? 'Soạn mã' : 'Trả lời câu hỏi'"></span>
                        <div class="flex items-center gap-1">
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
                                                {{-- Chỉ 2 ngôn ngữ vì máy chấm CHỈ nhận 2 (config/judge0.php: languages
                                                     = cpp, python). Bày thêm C++14 như bản mẫu là hứa cái hệ thống
                                                     không chấm được. Giá trị gửi lên vẫn là 'cpp'/'python' như cũ. --}}
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

                                            {{-- SỬA 18/9 (khách: "tab làm bài là để làm bài chứ không cần hiển thị đề") —
                                                 câu CÓ bản PDF thì chỉ để tên câu + chỉ chỗ đọc đề, dành hết chiều cao
                                                 cho chỗ gõ code. Câu KHÔNG có PDF vẫn in đề ở đây, vì đó là chỗ DUY
                                                 NHẤT học sinh đọc được đề. ($q['body'] là HTML do CKEditor lưu — giữ
                                                 nguyên cách render RAW như bản cũ.) --}}
                                            <div class="shrink-0 px-4 pb-3 pt-3 text-xs leading-5 text-[#45657D]">
                                                <p class="font-bold text-[#123B68]">Câu {{ $q['no'] }} · {{ $q['typeLabel'] }} · {{ $q['points'] }} điểm</p>
                                                <p class="mt-1">{{ $q['title'] }}</p>
                                                @if ($q['statementPdfUrl'])
                                                    <p class="mt-1 text-[11px] text-[#7A92A3]">Đề bài ở tab <span class="font-bold text-[#126F91]">Đề bài PDF</span>.</p>
                                                @elseif ($q['body'])
                                                    <div class="rich-content mt-1 max-h-[26vh] overflow-y-auto">{!! $q['body'] !!}</div>
                                                @endif
                                            </div>

                                            {{-- Lớp tô màu cú pháp nằm dưới, textarea trong suốt nằm trên — đúng cách bản mẫu làm. --}}
                                            <div class="relative min-h-0 flex-1 overflow-hidden">
                                                <pre aria-hidden="true" x-ref="hl{{ $qid }}"
                                                     class="pointer-events-none absolute inset-0 z-20 overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6"><code x-html="highlight(codes[{{ $qid }}], languages[{{ $qid }}])"></code></pre>
                                                {{-- data-code-source: DẤU NHẬN BIẾT cho CSS, không có JS nào đọc.
                                                     Chế độ tối có luật "textarea nền #172a35" (chép từ bản mẫu) — ô này
                                                     phải trong suốt để lộ lớp tô màu bên dưới, xem app.css. --}}
                                                <textarea data-code-source x-model="codes[{{ $qid }}]" :disabled="expired" spellcheck="false"
                                                          @scroll="syncScroll($event, 'hl{{ $qid }}')"
                                                          @input.debounce.700ms="onCode({{ $qid }})"
                                                          aria-label="Trình soạn mã có tô màu cú pháp"
                                                          style="color: transparent; -webkit-text-fill-color: transparent;"
                                                          class="absolute inset-0 z-10 h-full w-full resize-none overflow-auto whitespace-pre bg-transparent px-4 pb-4 font-mono text-[12px] leading-6 outline-none selection:bg-[#2F8A6B]/40"></textarea>
                                            </div>
                                        </section>

                                        {{-- ── INPUT / OUTPUT ── --}}
                                        <div class="grid min-h-[420px] min-w-0 grid-rows-2 gap-2 overflow-hidden">
                                            <section class="flex min-h-0 flex-col overflow-hidden rounded-xl bg-[#EEF6F8]">
                                                <div class="flex shrink-0 items-center justify-between gap-2 px-3 py-2.5">
                                                    <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#126F91]">Input</span>
                                                    {{-- Chưa có route chạy thử riêng (CodeJudgingService hiện chỉ được gọi lúc
                                                         NỘP: AttemptService::gradeCoding). Để nút đúng chỗ như bản mẫu nhưng
                                                         khoá lại + nói rõ lý do, thay vì bấm vào không có gì xảy ra. --}}
                                                    <button type="button" disabled title="Chạy thử chưa nối máy chấm — bấm Nộp đề để được chấm thật"
                                                            class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg bg-[#2F8A6B] px-2.5 py-1.5 text-[10px] font-bold text-white opacity-50 shadow-sm"><x-lucide name="play" class="h-3.5 w-3.5" />Chạy test</button>
                                                </div>
                                                {{-- Ô này KHÔNG đổ sẵn test từ database: test_cases trong grading_config là
                                                     test CHẤM ĐIỂM, không có cờ phân biệt test mẫu/test ẩn, in ra đây là
                                                     đưa luôn dữ liệu chấm cho học sinh. --}}
                                                <textarea x-model="testInputs[{{ $qid }}]" :disabled="expired" spellcheck="false"
                                                          placeholder="Nhập dữ liệu vào để thử nghiệm…" aria-label="Dữ liệu đầu vào test"
                                                          class="min-h-0 flex-1 resize-none bg-white/80 px-3 py-3 font-mono text-[11px] leading-5 text-[#123B68] outline-none disabled:cursor-not-allowed disabled:opacity-60"></textarea>
                                            </section>

                                            <section class="flex min-h-0 flex-col overflow-hidden rounded-xl bg-[#F7F9FA]">
                                                <div class="flex shrink-0 items-center justify-between gap-2 px-3 py-2.5">
                                                    <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#607A90]">Output</span>
                                                </div>
                                                <pre class="min-h-0 flex-1 overflow-y-auto whitespace-pre-wrap bg-white/80 px-3 py-3 font-mono text-[11px] leading-5 text-[#45657D]">Chưa chạy test</pre>
                                            </section>
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
            </main>
        </div>

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
        </div>
    </div>
@endsection

@push('scripts')
    <style>
        /* Nội dung đề do CKEditor lưu — cùng quy tắc với các trang khác đang dùng .rich-content. */
        .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: .5rem; }
        .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: .5rem; }
        .rich-content p { margin-bottom: .5rem; }
        .no-scrollbar { scrollbar-width: none; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
    </style>

    @include('partials.pdf-fit-viewer')

    {{-- Bộ tô màu cú pháp + mã khởi tạo, dùng chung với màn luyện 1 bài. --}}
    @include('partials.code-editor-runtime')

    <script>

        function examWorkspace(config) {
            return {
                // ── Cấu hình từ server ──
                questions: config.questions || [],
                firstId: config.firstId,
                lastId: config.lastId,
                saveUrl: config.saveUrl,
                deadlineAt: config.deadlineAt ? new Date(config.deadlineAt).getTime() : null,
                // Bù lệch giờ máy học sinh vs máy chủ — GIỮ NGUYÊN cách tính của bản cũ. Đây chỉ
                // là hiển thị; chặn THẬT vẫn nằm ở server (AttemptService::isExpired()).
                clockOffsetMs: config.serverNow ? (new Date(config.serverNow).getTime() - Date.now()) : 0,

                // ── Trạng thái bài làm ──
                answers: Object.assign({}, config.answers),
                codes: Object.assign({}, config.codes),
                languages: Object.assign({}, config.languages),
                testInputs: {},

                // ── Trạng thái giao diện ──
                activeId: config.firstId,
                activeTab: 'work',
                theme: 'light',
                remainingLabel: '',
                tone: 'normal',
                expired: false,
                submitting: false,
                confirmOpen: false,
                saving: false,
                timerId: null,
                inFlight: 0,

                init() {
                    var self = this;
                    this.questions.forEach(function (q) {
                        if (self.testInputs[q.id] === undefined) self.testInputs[q.id] = '';
                        if (q.kind === 'code' && !String(self.codes[q.id] || '').length) {
                            self.codes[q.id] = STARTER_CODE[self.languages[q.id]] || STARTER_CODE.cpp;
                        }
                    });

                    try {
                        if (window.localStorage.getItem('onthi360-exam-theme') === 'dark') this.setTheme('dark');
                    } catch (e) { /* trình duyệt chặn localStorage — cứ dùng nền sáng */ }

                    if (this.deadlineAt === null) return;
                    this.tick();
                    this.timerId = setInterval(function () { self.tick(); }, 1000);
                },

                // ── Điều hướng câu ──
                currentKind() {
                    var q = this.questions.find((item) => item.id === this.activeId);
                    return q ? q.kind : 'choice';
                },
                setActive(id) {
                    if (this.expired || this.submitting) return;
                    this.activeId = id;
                    this.followActive();
                },
                goPrev() {
                    if (this.expired || this.submitting) return;
                    var i = this.questions.findIndex((q) => q.id === this.activeId);
                    if (i > 0) { this.activeId = this.questions[i - 1].id; this.followActive(); }
                },
                goNext() {
                    if (this.expired || this.submitting) return;
                    var i = this.questions.findIndex((q) => q.id === this.activeId);
                    if (i > -1 && i < this.questions.length - 1) { this.activeId = this.questions[i + 1].id; this.followActive(); }
                },
                // SỬA 18/9 — thay cho hàm cuộn dải cũ: đề nhiều câu thì dải số chỉ hiện được vài
                // viên, bấm mũi tên đi quá khung là viên đang xem khuất mất. Giờ dải TỰ CUỘN theo
                // câu đang xem, không phải kéo tay.
                followActive() {
                    var self = this;
                    this.$nextTick(function () {
                        var rail = self.$refs.rail;
                        if (!rail) return;
                        var pill = rail.querySelector('[data-rail-pill="' + self.activeId + '"]');
                        if (pill && typeof pill.scrollIntoView === 'function') {
                            pill.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                        }
                    });
                },

                // ── Nền sáng/tối (bản mẫu dùng useWorkspaceTheme, ở đây gọn lại đúng phần cần) ──
                setTheme(next) {
                    this.theme = next;
                    document.documentElement.classList.toggle('theme-dark', next === 'dark');
                },
                toggleTheme() {
                    this.setTheme(this.theme === 'dark' ? 'light' : 'dark');
                    try { window.localStorage.setItem('onthi360-exam-theme', this.theme); } catch (e) { /* bỏ qua */ }
                },

                // ── Tô màu + cuộn đồng bộ ──
                highlight(code, language) { return highlightCode(code, language, this.theme); },
                syncScroll(event, refName) {
                    var target = this.$refs[refName];
                    if (!target) return;
                    target.scrollTop = event.currentTarget.scrollTop;
                    target.scrollLeft = event.currentTarget.scrollLeft;
                },

                // ── Đếm đã trả lời ──
                isAnswered(id) {
                    var q = this.questions.find((item) => item.id === id);
                    if (q && q.kind === 'code') return String(this.codes[id] || '').trim().length > 0;
                    return String(this.answers[id] === undefined || this.answers[id] === null ? '' : this.answers[id]).trim().length > 0;
                },
                answeredCount() {
                    var self = this;
                    return this.questions.filter(function (q) { return self.isAnswered(q.id); }).length;
                },

                // ── Soạn mã ──
                onLanguageChange(id) {
                    // Đổi ngôn ngữ thì thay mã khởi tạo, ĐÚNG như bản mẫu — nhưng chỉ khi học
                    // sinh chưa viết gì khác mã khởi tạo, tránh xoá mất bài đang viết dở.
                    var current = String(this.codes[id] || '').trim();
                    var isStarter = current === '' || current === String(STARTER_CODE.cpp).trim() || current === String(STARTER_CODE.python).trim();
                    if (isStarter) this.codes[id] = STARTER_CODE[this.languages[id]] || STARTER_CODE.cpp;
                    this.onCode(id);
                },
                resetCode(id) {
                    this.codes[id] = STARTER_CODE[this.languages[id]] || STARTER_CODE.cpp;
                    this.onCode(id);
                },
                loadCodeFile(id, event) {
                    var self = this;
                    var file = event.target.files && event.target.files[0];
                    if (!file) return;
                    if (/\.py$/i.test(file.name)) self.languages[id] = 'python';
                    file.text().then(function (content) {
                        self.codes[id] = content;
                        self.onCode(id);
                    });
                    event.target.value = '';
                },
                onCode(id) {
                    this.save(id, { code_source: this.codes[id], language: this.languages[id] });
                },

                // ── Trắc nghiệm / điền đáp án ──
                onAnswer(id) {
                    var q = this.questions.find((item) => item.id === id);
                    var value = this.answers[id];
                    this.save(id, q && q.kind === 'fill' ? { text: value } : { selected_option: value });
                },

                // ── Tự lưu (GIỮ NGUYÊN giao thức cũ với student.assessment.take.save) ──
                async save(questionId, payload) {
                    if (this.expired || this.submitting) return;

                    this.inFlight++;
                    this.saving = true;

                    try {
                        var res = await fetch(this.saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({ answers: { [questionId]: payload } }),
                        });
                        var data = await res.json().catch(() => null);
                        if (data && data.expired) this.handleTimeUp(data.resultUrl);
                    } catch (e) {
                        // Mất mạng thoáng qua — không làm phiền học sinh giữa giờ thi; bài vẫn còn
                        // trên màn hình, lần sửa tiếp theo sẽ lưu lại.
                    } finally {
                        this.inFlight = Math.max(0, this.inFlight - 1);
                        this.saving = this.inFlight > 0;
                    }
                },

                // ── Đồng hồ ──
                tick() {
                    var now = Date.now() + this.clockOffsetMs;
                    var remainingMs = this.deadlineAt - now;

                    if (remainingMs <= 0) {
                        this.remainingLabel = '00:00';
                        this.tone = 'danger';
                        if (!this.expired) this.handleTimeUp();
                        return;
                    }

                    var total = Math.floor(remainingMs / 1000);
                    var h = Math.floor(total / 3600);
                    var m = Math.floor((total % 3600) / 60);
                    var s = total % 60;
                    var pad = function (n) { return String(n).padStart(2, '0'); };
                    // Bản mẫu hiện dạng 01:27:42 — giữ đúng định dạng đó.
                    this.remainingLabel = pad(h) + ':' + pad(m) + ':' + pad(s);
                    this.tone = total <= 60 ? 'danger' : (total <= 300 ? 'warning' : 'normal');
                },

                // ── Nộp bài ──
                // Đổ trạng thái Alpine vào đúng các input ẩn của <form> rồi mới submit. Câu chưa
                // trả lời thì DISABLE input để trình duyệt không gửi lên — giữ nguyên hành vi cũ
                // (trước đây input của câu chưa trả lời đơn giản là không có giá trị), tránh tạo
                // ra bản ghi "đã trả lời" rỗng ở server.
                syncForm() {
                    var form = this.$refs.examForm;
                    if (!form) return;
                    var self = this;

                    var put = function (name, value) {
                        var el = form.querySelector('[name="' + name + '"]');
                        if (!el) return;
                        var text = (value === undefined || value === null) ? '' : String(value);
                        el.value = text;
                        el.disabled = text.trim() === '';
                    };

                    this.questions.forEach(function (q) {
                        if (q.kind === 'code') {
                            var code = self.codes[q.id];
                            put('answers[' + q.id + '][code_source]', code);
                            // Ngôn ngữ chỉ có nghĩa khi có mã — bám theo trạng thái của ô mã.
                            put('answers[' + q.id + '][language]', String(code || '').trim() === '' ? '' : self.languages[q.id]);
                        } else if (q.kind === 'fill') {
                            put('answers[' + q.id + '][text]', self.answers[q.id]);
                        } else {
                            put('answers[' + q.id + '][selected_option]', self.answers[q.id]);
                        }
                    });
                },

                handleTimeUp(resultUrl) {
                    this.expired = true;
                    clearInterval(this.timerId);

                    if (resultUrl) {
                        window.location.href = resultUrl;
                        return;
                    }

                    this.submitting = true;
                    this.syncForm();
                    this.$nextTick(() => this.$refs.examForm.submit());
                },

                doSubmit() {
                    this.submitting = true;
                    this.syncForm();
                    this.$nextTick(() => this.$refs.examForm.submit());
                },
            };
        }
    </script>
@endpush
