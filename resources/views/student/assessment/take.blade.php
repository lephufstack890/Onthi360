@extends('layouts.student')

@section('title', 'Làm bài')
@section('page-title', '')

@section('content')
    @php
        $questions = $questions ?? [];
        $assessmentTitle = $assessmentModel->title ?? 'Đề';
        $maxAttempts = $assessmentModel->resubmission_policy['max_attempts'] ?? null;
        $resubmissionNote = $maxAttempts ? 'Nộp lại tối đa '.$maxAttempts.' lần' : 'Không giới hạn số lần nộp lại';
        $typeMeta = [
            'mcq' => ['label' => 'Trắc nghiệm', 'icon' => '🔤'],
            'fill_blank' => ['label' => 'Điền đáp án', 'icon' => '✏️'],
            'coding' => ['label' => 'Lập trình', 'icon' => '💻'],
        ];
    @endphp

    <style>[x-cloak] { display: none !important; }</style>

    @if ($errors->any())
        @include('partials.toast-flash', ['type' => 'error', 'message' => implode(' ', $errors->all())])
    @endif

    <div
        x-data="examTake({
            deadlineAt: @js($deadlineAt ?? null),
            serverNow: @js($serverNow ?? null),
            saveUrl: @js(route('student.assessment.take.save', $attempt->id)),
            initialStatus: @js(collect($questions)->mapWithKeys(fn ($q) => [$q['questionId'] => $q['status'] === 'answered'])),
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

        {{-- Modal xác nhận nộp bài — thay cho confirm() mặc định của trình duyệt cho thân thiện hơn. --}}
        <div x-cloak x-show="confirmOpen" x-transition.opacity
             class="fixed inset-0 z-50 bg-slate-900/50 flex items-center justify-center p-4" @keydown.escape.window="confirmOpen = false">
            <div class="bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl">
                <h2 class="text-base font-semibold text-slate-800">Nộp bài ngay?</h2>
                <p class="text-sm text-slate-500 mt-2">
                    Bạn đã trả lời <span x-text="answeredCount()"></span>/{{ count($questions) }} câu.
                    <template x-if="answeredCount() < {{ count($questions) }}">
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

        <form method="POST" action="{{ route('student.assessment.take.submit', $attempt->id) }}" id="take-form" x-ref="examForm">
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
                {{-- Thanh tiến độ số câu đã trả lời — cập nhật thời gian thực theo answeredMap, không cần reload trang. --}}
                <div class="mt-2.5 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full bg-rose-500 transition-all duration-300" :style="`width: ${(answeredCount() / {{ max(count($questions), 1) }}) * 100}%`"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                {{-- Điều hướng câu --}}
                <div class="lg:col-span-1 order-2 lg:order-1">
                    <div class="bg-white rounded-2xl border border-slate-200 p-4 sticky top-28">
                        <p class="text-xs text-slate-400 mb-3">
                            <span x-text="answeredCount()"></span>/{{ count($questions) }} câu đã trả lời · Hỗn hợp trắc nghiệm/điền đáp án/lập trình
                        </p>
                        <div class="grid grid-cols-5 lg:grid-cols-4 gap-2">
                            @foreach ($questions as $q)
                                <a href="#question-{{ $q['no'] }}"
                                   class="w-9 h-9 flex items-center justify-center rounded-lg border text-sm font-medium transition-colors"
                                   :class="answeredMap[{{ $q['questionId'] }}] ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : 'bg-white text-slate-500 border-slate-200'">
                                    {{ $q['no'] }}
                                </a>
                            @endforeach
                        </div>
                        <div class="mt-4 space-y-1.5 text-xs text-slate-500">
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-emerald-100 border border-emerald-200 inline-block"></span> Đã trả lời</div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded bg-white border border-slate-200 inline-block"></span> Chưa trả lời</div>
                        </div>
                    </div>
                </div>

                {{-- Danh sách câu hỏi --}}
                <div class="lg:col-span-3 order-1 lg:order-2 space-y-5">
                    @foreach ($questions as $q)
                        @php $meta = $typeMeta[$q['type']] ?? ['label' => $q['type'], 'icon' => '📝']; @endphp
                        <div id="question-{{ $q['no'] }}" class="bg-white rounded-2xl border border-slate-200 p-6 scroll-mt-28">
                            <div class="flex items-center justify-between mb-4 gap-2">
                                <x-status-badge tone="info">
                                    {{ $meta['icon'] }} Câu {{ $q['no'] }} · {{ $meta['label'] }} · {{ $q['points'] }} điểm
                                </x-status-badge>
                                <span x-show="answeredMap[{{ $q['questionId'] }}]" x-cloak class="text-xs font-medium text-emerald-600">Đã lưu ✓</span>
                            </div>

                            <p class="text-slate-800 font-medium mb-4">{{ $q['title'] }}</p>
                            @if ($q['body'])
                                <p class="text-sm text-slate-500 mb-4 whitespace-pre-line">{{ $q['body'] }}</p>
                            @endif

                            @if ($q['type'] === 'mcq')
                                <div class="space-y-2">
                                    @foreach ($q['options'] as $i => $opt)
                                        <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-slate-200 hover:border-rose-300 cursor-pointer text-sm text-slate-700 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 transition-colors">
                                            <input type="radio" name="answers[{{ $q['questionId'] }}][selected_option]" value="{{ $i }}"
                                                   :disabled="expired"
                                                   @checked((string) $q['selectedOption'] === (string) $i)
                                                   @change="onAnswer({{ $q['questionId'] }}, { selected_option: $event.target.value }, true)">
                                            {{ $opt }}
                                        </label>
                                    @endforeach
                                </div>
                            @elseif ($q['type'] === 'fill_blank')
                                <input type="text" name="answers[{{ $q['questionId'] }}][text]" value="{{ $q['textAnswer'] }}"
                                       placeholder="Nhập đáp án..."
                                       :disabled="expired"
                                       @input.debounce.700ms="onAnswer({{ $q['questionId'] }}, { text: $event.target.value }, $event.target.value.trim() !== '')"
                                       class="w-full rounded-lg border border-slate-200 text-sm p-2.5 hover:border-rose-200 focus:outline-none focus:ring-2 focus:ring-rose-100 focus:border-rose-300 transition disabled:bg-slate-50 disabled:text-slate-400">
                            @else
                                <div class="space-y-2" x-data="{ language: @js($q['language'] ?? 'cpp'), code: @js($q['codeSource'] ?? '') }">
                                    <input type="text" name="answers[{{ $q['questionId'] }}][language]" x-model="language"
                                           placeholder="Ngôn ngữ (vd cpp, python, java)"
                                           :disabled="expired"
                                           @input.debounce.700ms="onAnswer({{ $q['questionId'] }}, { code_source: code, language }, code.trim() !== '')"
                                           class="w-full rounded-lg border border-slate-200 text-sm p-2.5 disabled:bg-slate-50 disabled:text-slate-400">
                                    <textarea name="answers[{{ $q['questionId'] }}][code_source]" x-model="code" rows="10"
                                              placeholder="Viết code ở đây..."
                                              :disabled="expired"
                                              @input.debounce.700ms="onAnswer({{ $q['questionId'] }}, { code_source: code, language }, code.trim() !== '')"
                                              class="w-full rounded-lg border border-slate-200 text-sm p-2.5 font-mono disabled:bg-slate-50 disabled:text-slate-400"></textarea>
                                    <p class="text-xs text-slate-400">Bài nộp sẽ ở trạng thái "Đang chấm" — hệ thống chấm code tự động chưa nối vào ở phiên bản này.</p>
                                </div>
                            @endif
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
        function examTake(config) {
            return {
                deadlineAt: config.deadlineAt ? new Date(config.deadlineAt).getTime() : null,
                // Bù lệch giờ giữa máy học sinh và máy chủ — đồng hồ đếm ngược tính theo giờ
                // MÁY CHỦ (config.serverNow), không tin đồng hồ máy client (16 mục 3). Đây chỉ
                // là hiển thị — chặn THẬT luôn nằm ở server (AttemptService::isExpired()).
                clockOffsetMs: config.serverNow ? (new Date(config.serverNow).getTime() - Date.now()) : 0,
                saveUrl: config.saveUrl,
                answeredMap: { ...config.initialStatus },
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
                    return Object.values(this.answeredMap).filter(Boolean).length;
                },

                onAnswer(questionId, payload, isAnswered) {
                    this.answeredMap[questionId] = isAnswered;
                    this.save(questionId, payload);
                },

                async save(questionId, payload) {
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
                            body: JSON.stringify({ answers: { [questionId]: payload } }),
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

                    // Server đã tự nộp bài rồi (AttemptService::isExpired() phát hiện lúc autosave
                    // gần nhất, hoặc sẽ tự phát hiện ngay trong submit() bên dưới) — có resultUrl
                    // thì sang thẳng, không thì tự nộp qua chính <form> thật để chắc chắn server
                    // luôn có 1 lượt "nộp" ghi nhận đàng hoàng (không chỉ dựa vào đồng hồ client).
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
