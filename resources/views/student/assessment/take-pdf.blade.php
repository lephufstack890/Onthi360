@extends('layouts.student')

@section('title', 'Làm bài')
@section('page-title', '')

@section('content')
    @php
        $answerRows = $answerRows ?? [];
        $codingRows = $codingRows ?? [];
        $assessmentTitle = $assessmentModel->title ?? 'Đề';
        $maxAttempts = $assessmentModel->resubmission_policy['max_attempts'] ?? null;
        $resubmissionNote = $maxAttempts ? 'Nộp lại tối đa '.$maxAttempts.' lần' : 'Không giới hạn số lần nộp lại';
        $totalCount = count($answerRows) + count($codingRows);
        $typeMeta = [
            'single_choice' => ['label' => 'Trắc nghiệm 1 đáp án', 'icon' => '🔤'],
            'true_false_group' => ['label' => 'Đúng/Sai từng ý', 'icon' => '✅'],
            'short_answer' => ['label' => 'Trả lời ngắn', 'icon' => '✏️'],
        ];
    @endphp

    <style>[x-cloak] { display: none !important; }</style>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div
        x-data="pdfExamTake({
            deadlineAt: @js($deadlineAt ?? null),
            serverNow: @js($serverNow ?? null),
            saveUrl: @js(route('student.assessment.take.save', $attempt->id)),
            initialAnswerStatus: @js(collect($answerRows)->mapWithKeys(fn ($r) => [$r['answerKeyId'] => $r['answered']])),
            initialCodingStatus: @js(collect($codingRows)->mapWithKeys(fn ($r) => [$r['codingItemId'] => $r['answered']])),
        })"
        x-init="init()"
    >
        {{-- Lớp phủ khoá bài khi hết giờ — chặn thật (input :disabled) chứ không chỉ là hiệu
             ứng, phòng khi học sinh vẫn cố bấm/gõ trong lúc form đang tự nộp. --}}
        <div x-cloak x-show="expired" x-transition.opacity
             class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-xl">
                <div class="text-4xl mb-3">⏰</div>
                <h2 class="text-lg font-semibold text-slate-800">Đã hết giờ làm bài</h2>
                <p class="text-sm text-slate-500 mt-2">Bài làm của bạn đang được tự động nộp, vui lòng đợi trong giây lát...</p>
                <div class="mt-4 flex justify-center">
                    <span class="inline-block w-6 h-6 border-2 border-rose-200 border-t-rose-600 rounded-full animate-spin"></span>
                </div>
            </div>
        </div>

        {{-- Modal xác nhận nộp bài --}}
        <div x-cloak x-show="confirmOpen" x-transition.opacity
             class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4" @keydown.escape.window="confirmOpen = false">
            <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">Nộp bài ngay?</h2>
                <p class="text-sm text-slate-500 mt-2">
                    Bạn đã trả lời <span x-text="answeredCount()"></span>/{{ $totalCount }} câu.
                    <template x-if="answeredCount() < {{ max($totalCount, 1) }}">
                        <span class="text-amber-600">Vẫn còn câu chưa trả lời.</span>
                    </template>
                    Sau khi nộp sẽ không thể sửa lại câu trả lời.
                </p>
                <div class="flex gap-3 mt-5">
                    <button type="button" @click="confirmOpen = false" class="flex-1 px-4 py-2 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium">Làm tiếp</button>
                    <button type="button" @click="confirmOpen = false; doSubmit()" class="flex-1 px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium">Nộp bài</button>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('student.assessment.take.submit', $attempt->id) }}" id="take-pdf-form" x-ref="examForm">
            @csrf

            {{-- Header sticky: tên đề, tiến độ, đồng hồ, trạng thái tự lưu, nộp bài --}}
            <div class="sticky top-0 z-10 -mx-4 lg:-mx-6 px-4 lg:px-6 py-3 bg-white/90 backdrop-blur border-b border-slate-200 mb-6">
                <div class="flex items-center justify-between gap-4 flex-wrap">
                    <div class="min-w-0">
                        <h1 class="font-medium text-slate-800 truncate">{{ $assessmentTitle }}</h1>
                        <p class="text-xs text-slate-400">
                            {{ $resubmissionNote }}
                            <span class="mx-1">·</span>
                            <span x-show="saving" class="text-slate-400">Đang lưu…</span>
                            <span x-show="!saving" x-cloak class="text-emerald-500">Đã lưu tự động ✓</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <template x-if="deadlineAt !== null">
                            <div class="px-3 py-1.5 rounded-lg text-sm font-semibold tabular-nums transition-colors"
                                 :class="{
                                     'bg-rose-50 text-rose-600': tone === 'normal',
                                     'bg-amber-100 text-amber-700': tone === 'warning',
                                     'bg-rose-600 text-white animate-pulse': tone === 'danger',
                                 }">
                                ⏱ <span x-text="remainingLabel"></span>
                            </div>
                        </template>
                        <button type="button" @click="confirmOpen = true" :disabled="expired || submitting"
                                class="px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium disabled:opacity-50">
                            Nộp bài
                        </button>
                    </div>
                </div>
                <div class="mt-2.5 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full bg-rose-500 transition-all duration-300" :style="`width: ${(answeredCount() / {{ max($totalCount, 1) }}) * 100}%`"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Nửa trái: xem đề PDF --}}
                <div class="order-1">
                    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden sticky top-28" style="height: calc(100vh - 8rem);">
                        <iframe src="{{ $pdfUrl }}" title="Đề thi PDF" class="w-full h-full"></iframe>
                    </div>
                </div>

                {{-- Nửa phải: phiếu trả lời + bài lập trình --}}
                <div class="order-2 space-y-5">
                    <p class="text-xs text-slate-400">
                        <span x-text="answeredCount()"></span>/{{ $totalCount }} câu đã trả lời · Xem đề PDF ở khung bên trái, chọn/nhập đáp án ở đây.
                    </p>

                    @foreach ($answerRows as $row)
                        @php $meta = $typeMeta[$row['type']] ?? ['label' => $row['type'], 'icon' => '📝']; @endphp
                        <div class="bg-white rounded-2xl border border-slate-200 p-5">
                            <div class="flex items-center justify-between mb-3 gap-2">
                                <x-status-badge tone="info">
                                    {{ $meta['icon'] }} Câu {{ $row['no'] }} · {{ $meta['label'] }} · {{ $row['points'] }} điểm
                                </x-status-badge>
                                <span x-show="answeredAnswerMap[{{ $row['answerKeyId'] }}]" x-cloak class="text-xs font-medium text-emerald-600">Đã lưu ✓</span>
                            </div>

                            @if ($row['type'] === 'single_choice')
                                <div class="grid grid-cols-4 gap-2">
                                    @foreach (['A', 'B', 'C', 'D'] as $letter)
                                        <label class="flex items-center justify-center gap-1.5 px-3 py-2.5 rounded-xl border border-slate-200 hover:border-rose-300 cursor-pointer text-sm text-slate-700 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 transition-colors">
                                            <input type="radio" name="answers[answer_keys][{{ $row['answerKeyId'] }}]" value="{{ $letter }}"
                                                   :disabled="expired"
                                                   @checked($row['submittedAnswer'] === $letter)
                                                   @change="onAnswerKey({{ $row['answerKeyId'] }}, $event.target.value, true)">
                                            {{ $letter }}
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($row['type'] === 'true_false_group')
                                <div class="space-y-2" x-data="{ parts: @js(collect(['a', 'b', 'c', 'd'])->mapWithKeys(fn ($p) => [$p => $row['submittedAnswer'][$p] ?? null])->all()) }">
                                    @foreach (['a', 'b', 'c', 'd'] as $part)
                                        <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-xl border border-slate-200">
                                            <span class="text-sm text-slate-600">Ý {{ strtoupper($part) }}</span>
                                            <div class="flex gap-2">
                                                <label class="flex items-center gap-1 text-xs px-2.5 py-1 rounded-lg border border-slate-200 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50 cursor-pointer">
                                                    <input type="radio" name="answers[answer_keys][{{ $row['answerKeyId'] }}][{{ $part }}]" value="1"
                                                           :disabled="expired"
                                                           :checked="parts.{{ $part }} === true"
                                                           @change="parts.{{ $part }} = true; onAnswerKey({{ $row['answerKeyId'] }}, parts, true)">
                                                    Đúng
                                                </label>
                                                <label class="flex items-center gap-1 text-xs px-2.5 py-1 rounded-lg border border-slate-200 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 cursor-pointer">
                                                    <input type="radio" name="answers[answer_keys][{{ $row['answerKeyId'] }}][{{ $part }}]" value="0"
                                                           :disabled="expired"
                                                           :checked="parts.{{ $part }} === false"
                                                           @change="parts.{{ $part }} = false; onAnswerKey({{ $row['answerKeyId'] }}, parts, true)">
                                                    Sai
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <input type="text" name="answers[answer_keys][{{ $row['answerKeyId'] }}]" value="{{ $row['submittedAnswer'] }}"
                                       placeholder="Nhập đáp án (số)..."
                                       :disabled="expired"
                                       @input.debounce.700ms="onAnswerKey({{ $row['answerKeyId'] }}, $event.target.value, $event.target.value.trim() !== '')"
                                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition disabled:bg-slate-50 disabled:text-slate-400">
                            @endif
                        </div>
                    @endforeach

                    @foreach ($codingRows as $row)
                        <div class="bg-white rounded-2xl border border-slate-200 p-5"
                             x-data="{ language: @js($row['language'] ?? ($row['allowedLanguages'][0] ?? 'cpp')), code: @js($row['codeSource'] ?? '') }">
                            <div class="flex items-center justify-between mb-3 gap-2">
                                <x-status-badge tone="info">
                                    💻 {{ $row['code'] }} · {{ $row['title'] }} · {{ $row['points'] }} điểm
                                    @if ($row['pdfPage'])
                                        · Trang {{ $row['pdfPage'] }}
                                    @endif
                                </x-status-badge>
                                <span x-show="answeredCodingMap[{{ $row['codingItemId'] }}]" x-cloak class="text-xs font-medium text-emerald-600">Đã lưu ✓</span>
                            </div>

                            <select name="answers[coding_items][{{ $row['codingItemId'] }}][language]" x-model="language"
                                    :disabled="expired"
                                    @change="onCodingItem({{ $row['codingItemId'] }}, { code_source: code, language }, code.trim() !== '')"
                                    class="w-full rounded-lg border border-slate-200 text-sm p-2.5 mb-2 disabled:bg-slate-50 disabled:text-slate-400">
                                @foreach (($row['allowedLanguages'] ?: ['cpp', 'python']) as $lang)
                                    <option value="{{ $lang }}">{{ $lang }}</option>
                                @endforeach
                            </select>
                            <textarea name="answers[coding_items][{{ $row['codingItemId'] }}][code_source]" x-model="code" rows="10"
                                      placeholder="Viết code ở đây..."
                                      :disabled="expired"
                                      @input.debounce.700ms="onCodingItem({{ $row['codingItemId'] }}, { code_source: code, language }, code.trim() !== '')"
                                      class="w-full rounded-lg border border-slate-200 text-sm p-2.5 font-mono disabled:bg-slate-50 disabled:text-slate-400"></textarea>
                            <p class="text-xs text-slate-400 mt-2">Bài nộp sẽ ở trạng thái "Đang chấm" — hệ thống chấm code tự động chưa nối vào ở phiên bản này.</p>
                        </div>
                    @endforeach

                    <p class="text-xs text-slate-400 text-center">Câu trả lời tự động lưu ngay khi bạn trả lời — không cần bấm nút nào để lưu, chỉ cần bấm "Nộp bài" khi làm xong.</p>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        function pdfExamTake(config) {
            return {
                deadlineAt: config.deadlineAt ? new Date(config.deadlineAt).getTime() : null,
                clockOffsetMs: config.serverNow ? (new Date(config.serverNow).getTime() - Date.now()) : 0,
                saveUrl: config.saveUrl,
                answeredAnswerMap: { ...config.initialAnswerStatus },
                answeredCodingMap: { ...config.initialCodingStatus },
                remainingLabel: '',
                tone: 'normal',
                expired: false,
                submitting: false,
                confirmOpen: false,
                saving: false,
                timerId: null,
                inFlight: 0,

                init() {
                    if (this.deadlineAt === null) {
                        return;
                    }
                    this.tick();
                    this.timerId = setInterval(() => this.tick(), 1000);
                },

                tick() {
                    const now = Date.now() + this.clockOffsetMs;
                    const remainingMs = this.deadlineAt - now;

                    if (remainingMs <= 0) {
                        this.remainingLabel = '0:00';
                        this.tone = 'danger';
                        if (!this.expired) {
                            this.handleTimeUp();
                        }
                        return;
                    }

                    const totalSeconds = Math.floor(remainingMs / 1000);
                    const h = Math.floor(totalSeconds / 3600);
                    const m = Math.floor((totalSeconds % 3600) / 60);
                    const s = totalSeconds % 60;
                    this.remainingLabel = h > 0
                        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
                        : `${m}:${String(s).padStart(2, '0')}`;
                    this.tone = totalSeconds <= 60 ? 'danger' : (totalSeconds <= 300 ? 'warning' : 'normal');
                },

                answeredCount() {
                    return Object.values(this.answeredAnswerMap).filter(Boolean).length
                        + Object.values(this.answeredCodingMap).filter(Boolean).length;
                },

                onAnswerKey(answerKeyId, payload, isAnswered) {
                    this.answeredAnswerMap[answerKeyId] = isAnswered;
                    this.save({ answer_keys: { [answerKeyId]: payload } });
                },

                onCodingItem(codingItemId, payload, isAnswered) {
                    this.answeredCodingMap[codingItemId] = isAnswered;
                    this.save({ coding_items: { [codingItemId]: payload } });
                },

                async save(partial) {
                    if (this.expired || this.submitting) {
                        return;
                    }

                    this.inFlight++;
                    this.saving = true;

                    try {
                        const res = await fetch(this.saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                            },
                            body: JSON.stringify({ answers: partial }),
                        });
                        const data = await res.json().catch(() => null);

                        if (data && data.expired) {
                            this.handleTimeUp(data.resultUrl);
                        }
                    } catch (e) {
                        // Mất mạng thoáng qua — không làm phiền học sinh giữa giờ thi bằng lỗi
                        // đỏ; câu trả lời vẫn còn nguyên trên form, lần sửa tiếp theo sẽ lưu lại.
                    } finally {
                        this.inFlight = Math.max(0, this.inFlight - 1);
                        this.saving = this.inFlight > 0;
                    }
                },

                handleTimeUp(resultUrl) {
                    this.expired = true;
                    clearInterval(this.timerId);

                    if (resultUrl) {
                        window.location.href = resultUrl;
                        return;
                    }

                    this.submitting = true;
                    this.$nextTick(() => this.$refs.examForm.submit());
                },

                doSubmit() {
                    this.submitting = true;
                    this.$nextTick(() => this.$refs.examForm.submit());
                },
            };
        }
    </script>
@endpush
