@extends('layouts.student')

@section('title', 'Làm bài tập')
@section('page-title', 'Làm bài tập')

{{--
    SỬA 31/8 (3, khách yêu cầu tách riêng UI): "Làm bài" 1 bài tập cụ thể của sản phẩm (mở từ
    "Tài liệu của tôi" — xem PracticeByQuestionService::startForQuestion(), luôn
    mode='single_question') giờ có view RIÊNG, KHÔNG dùng chung với
    student/practice/by-question-play.blade.php ("Luyện tập theo câu" — chọn ngẫu nhiên nhiều
    câu theo tag/dạng). 2 luồng khác hẳn về ngữ cảnh: đây là làm ĐÚNG 1 bài tập của 1 sản phẩm đã
    mua/kích hoạt, có đề bài PDF thật cần hiện ra để làm bài — KHÔNG có khái niệm "câu tiếp theo/
    điểm luyện tập" như bên kia. Vẫn cùng 1 route/state/logic chấm ở PracticeByQuestionService
    (không tạo state riêng) — chỉ khác template hiển thị, chọn ở PracticeByQuestionController::
    play().

    SỬA 31/8 (3, khách báo lỗi): trước đây $question->body của bài tập nhập từ ZIP chỉ là 1 dòng
    ghi chú trung lập ("Đề bài đầy đủ nằm trong tệp PDF đính kèm... xem mục Tệp đính kèm ở trang
    Sửa câu hỏi") — dòng này viết cho ADMIN đọc (trỏ tới 1 trang Admin học sinh không vào được),
    hiện thẳng ra cho học sinh là VÔ NGHĨA. Giờ nhúng THẲNG file statement.pdf thật (qua route
    access.resource.exerciseAttachment đã có sẵn — dùng lại nguyên vẹn, không tạo route mới) làm
    đề bài chính — chỉ rơi về hiện $question->body (fallback) nếu bài tập này (hiếm/không đúng
    quy ước) không có tệp statement.pdf đính kèm.
--}}
@section('content')
    @php
        $finished = $finished ?? false;
        $feedback = $feedback ?? null;
        $options = $options ?? [];
        $compositeParts = $compositeParts ?? [];
        $assets = $assets ?? [];
        $backUrl = $returnUrl ?? route('student.library.index');

        $statementUrl = null;
        if (! $finished) {
            $hasStatement = isset($question->metadata['attachments']['statement']['path']);
            if ($hasStatement) {
                $statementUrl = route('access.resource.exerciseAttachment', [$question->product_id, $question->id, 'statement']);
            }
        }
    @endphp

    {{-- SỬA 3/9 (khách yêu cầu: bấm chấm bài KHÔNG reload cả trang, chỉ hiện spinner ở nút rồi
         hiện kết quả tại chỗ — CÙNG hành vi đã làm ở by-question-play.blade.php, khách báo trang
         "Làm bài" 1 bài tập cụ thể này — cùng route/action student.practiceByQuestion.play,
         khác template do PracticeByQuestionController::play() chọn theo mode — vẫn còn load lại
         trang) — bọc id="practice-container" quanh CẢ 2 nhánh (đã xong/chưa xong) làm 1 khối
         DUY NHẤT có thể thay nguyên khối bằng JS: form "Ghi nhận bài làm"/"Kiểm tra đáp án" giờ
         submit qua fetch() (script cuối trang) thay vì để trình duyệt tự điều hướng — fetch tới
         ĐÚNG route cũ (student.practiceByQuestion.answer), Laravel vẫn redirect sang GET .play
         như cũ (KHÔNG đổi controller/route nào), fetch tự đi theo redirect đó và nhận về HTML
         trang mới, JS chỉ lấy đúng #practice-container trong HTML đó rồi thay vào chỗ cũ —
         không cần sửa gì ở backend. --}}
    <div id="practice-container">
    @if ($finished)
        <div class="max-w-3xl mx-auto text-center bg-white rounded-2xl border border-slate-200 p-8 mt-8">
            <div class="text-5xl mb-3">🎉</div>
            <h2 class="text-2xl font-semibold text-slate-800 mb-2">Đã ghi nhận bài làm!</h2>
            <p class="text-base text-slate-500 mb-6">Bài tập của bạn đã được ghi nhận — phần nào tự chấm được đã báo đúng/sai ngay, phần tự luận/lập trình (nếu có) chờ chấm sau.</p>
            <a href="{{ $backUrl }}" class="px-5 py-3 rounded-lg bg-rose-600 text-white text-base font-medium inline-block">‹ Quay lại Tài liệu của tôi</a>
        </div>
    @else
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <a href="{{ $backUrl }}" class="text-sm text-slate-500 hover:text-rose-600 inline-flex items-center gap-1">‹ Quay lại Tài liệu của tôi</a>
                <form method="POST" action="{{ route('student.practiceByQuestion.stop') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-400 hover:text-rose-600">Thoát bài tập ✕</button>
                </form>
            </div>

            <h2 class="text-xl font-semibold text-slate-800 mb-4">🧪 {{ $question->title }}</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                {{-- Cột trái: đề bài --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-5 lg:p-6">
                    <p class="text-xs font-medium text-slate-400 uppercase tracking-wide mb-3">Đề bài</p>

                    @if ($statementUrl)
                        <div class="rounded-lg overflow-hidden border border-slate-200 mb-2">
                            <iframe src="{{ $statementUrl }}" class="w-full" style="height: 560px;" title="Đề bài"></iframe>
                        </div>
                        <a href="{{ $statementUrl }}" target="_blank" rel="noopener" class="text-xs text-rose-600 font-medium">Mở đề bài trong tab mới ›</a>
                    @else
                        {{-- Fallback hiếm gặp: bài tập không có statement.pdf đính kèm — hiện
                             thẳng $question->body (cùng cách hiển thị rich-content như trước). --}}
                        <div class="rich-content text-base text-slate-600">{!! $question->body !!}</div>
                    @endif

                    @if ($question->tags->isNotEmpty())
                        <div class="flex flex-wrap gap-1 mt-4">
                            @foreach ($question->tags as $t)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">📚 {{ $t->name }}</span>
                            @endforeach
                        </div>
                    @endif

                    {{-- Học liệu CẦN để trả lời (vd nghe audio nghe-hiểu) — khác đề bài PDF ở
                         trên (chỉ để xem/tham khảo), asset ở đây là nội dung PHẢI dùng để làm
                         bài, nên luôn hiện kể cả đã trả lời hay chưa. --}}
                    @if (! empty($assets))
                        <div class="mt-4 space-y-3">
                            @foreach ($assets as $asset)
                                <div class="p-3 rounded-lg border border-slate-200 bg-slate-50">
                                    @if ($asset['kind'] === 'audio')
                                        <audio controls preload="none" class="w-full" src="{{ $asset['url'] }}"></audio>
                                    @elseif ($asset['kind'] === 'image')
                                        <img src="{{ $asset['url'] }}" alt="{{ $asset['altText'] ?? '' }}" class="rounded-lg">
                                    @else
                                        <a href="{{ $asset['url'] }}" class="text-sm text-rose-600 font-medium">📎 {{ $asset['filename'] ?? 'Tệp đính kèm' }}</a>
                                    @endif
                                    @if (! empty($asset['altText']))
                                        <p class="text-xs text-slate-400 mt-1">🔊 {{ $asset['altText'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Cột phải: trả lời / kết quả --}}
                <div class="bg-white rounded-2xl border border-slate-200 p-5 lg:p-6">
                    @if ($feedback === null)
                        {{-- SỬA 3/9 (khách yêu cầu: bấm nút KHÔNG reload trang, chỉ hiện spinner
                             rồi hiện kết quả tại chỗ) — data-ajax-answer đánh dấu để script cuối
                             trang bắt sự kiện submit của ĐÚNG form này (không đụng form "Thoát
                             bài tập"/"Hoàn tất bài tập" — 2 form đó vẫn điều hướng bình thường vì
                             không có độ trễ mạng đáng kể). --}}
                        <form method="POST" action="{{ route('student.practiceByQuestion.answer') }}" class="space-y-3" data-ajax-answer>
                            @csrf
                            @if ($question->type->value === 'mcq')
                                @foreach ($options as $i => $opt)
                                    @if ($opt !== '' && $opt !== null)
                                        <label class="flex items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-rose-200 cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                                            <input type="radio" name="selected_option" value="{{ $i }}" required>
                                            <span class="text-base text-slate-700">{{ $opt }}</span>
                                        </label>
                                    @endif
                                @endforeach
                            @elseif ($question->type->value === 'fill_blank')
                                <input type="text" name="text" required maxlength="500" placeholder="Nhập đáp án..."
                                       class="w-full rounded-lg border border-slate-200 text-base p-4">
                            @elseif ($question->type->value === 'composite')
                                @foreach ($compositeParts as $part)
                                    <div class="p-4 rounded-lg border border-slate-200">
                                        <p class="text-sm font-medium text-slate-700 mb-2">Phần {{ strtoupper($part['code']) }} <span class="text-slate-400 font-normal">({{ $part['points'] }} điểm)</span></p>
                                        @if ($part['responseType'] === 'single_choice')
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ($part['choices'] as $choice)
                                                    <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-sm cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                                                        <input type="radio" name="parts[{{ $part['code'] }}]" value="{{ $choice }}" required> {{ $choice }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        @elseif ($part['responseType'] === 'true_false')
                                            <div class="flex gap-2">
                                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-sm cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                                                    <input type="radio" name="parts[{{ $part['code'] }}]" value="true" required> Đúng
                                                </label>
                                                <label class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 text-sm cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                                                    <input type="radio" name="parts[{{ $part['code'] }}]" value="false" required> Sai
                                                </label>
                                            </div>
                                        @elseif ($part['responseType'] === 'short_answer')
                                            <input type="text" name="parts[{{ $part['code'] }}]" maxlength="500" placeholder="Nhập đáp án..."
                                                   class="w-full rounded-lg border border-slate-200 text-sm p-3">
                                        @else
                                            {{-- 'essay' hoặc dạng lạ chưa hỗ trợ — chỉ ghi nhận. --}}
                                            <textarea name="parts[{{ $part['code'] }}]" rows="4" maxlength="5000" placeholder="Viết câu trả lời của bạn..."
                                                      class="w-full rounded-lg border border-slate-200 text-sm p-3"></textarea>
                                            <p class="text-xs text-slate-400 mt-1">Phần tự luận chưa có chấm tự động — chỉ được ghi nhận.</p>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                {{-- Lập trình — ô viết code "như VSCode": nhúng CodeMirror 5 qua
                                     CDN (script init ở @push('scripts') cuối trang). --}}
                                <div class="rounded-lg overflow-hidden border border-slate-700">
                                    <div class="flex items-center gap-2 bg-[#272822] px-3 py-2 border-b border-slate-700">
                                        <span class="text-xs text-slate-300 font-medium">Ngôn ngữ:</span>
                                        <select name="language" data-code-language
                                                class="text-xs rounded-md border border-slate-600 bg-[#3a3d31] text-slate-100 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-rose-400">
                                            <option value="cpp" selected>C++</option>
                                            <option value="c">C</option>
                                            <option value="java">Java</option>
                                            <option value="python">Python</option>
                                            <option value="csharp">C#</option>
                                        </select>
                                    </div>
                                    {{-- SỬA (khách báo lỗi console "invalid form control ... not
                                         focusable") — bỏ 'required': control này bị CodeMirror ẩn
                                         đi (class hidden/display:none), nhưng trình duyệt validate
                                         HTML5 chạy TRƯỚC sự kiện 'submit' nên không thể focus 1
                                         control required đang ẩn để báo lỗi — kết quả là trình
                                         duyệt CHẶN LUÔN submit, học sinh bấm nút không thấy gì xảy
                                         ra. Xem giải thích đầy đủ ở by-question-play.blade.php
                                         (cùng lỗi, sửa cùng lúc). --}}
                                    <textarea name="code_source" data-code-editor class="hidden"></textarea>
                                </div>
                            @endif
                            <button type="submit" class="w-full px-4 py-3 rounded-lg bg-rose-600 text-white text-base font-medium">
                                {{ $question->type->value === 'coding' ? 'Ghi nhận bài làm' : 'Kiểm tra đáp án' }}
                            </button>
                            {{-- SỬA 3/9 — chỗ hiện lỗi khi gửi AJAX thất bại (mất mạng...), thay
                                 vì alert() gây gián đoạn. Ẩn mặc định, script cuối trang bật lên
                                 khi cần. --}}
                            <p data-ajax-error class="hidden text-sm text-rose-600 text-center"></p>
                        </form>
                    @else
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
                                            'flex items-center gap-2 p-4 rounded-lg border text-base',
                                            'border-emerald-300 bg-emerald-50 text-emerald-700' => $isCorrectOpt,
                                            'border-rose-300 bg-rose-50 text-rose-600' => $isYourPick && ! $isCorrectOpt,
                                            'border-slate-200 text-slate-500' => ! $isCorrectOpt && ! $isYourPick,
                                        ])>
                                            <span>{{ $isCorrectOpt ? '✓' : ($isYourPick ? '✕' : '') }}</span>
                                            <span>{{ $opt }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            @elseif ($question->type->value === 'fill_blank')
                                <div class="p-4 rounded-lg border border-slate-200 text-base text-slate-500">
                                    Bạn trả lời: <span class="font-medium text-slate-700">{{ $feedback['yourText'] }}</span>
                                </div>
                                <div class="p-4 rounded-lg border border-emerald-300 bg-emerald-50 text-base text-emerald-700">
                                    Đáp án đúng: {{ implode(', ', $feedback['acceptedAnswers']) }}
                                </div>
                            @elseif ($question->type->value === 'composite')
                                @foreach (($feedback['compositeParts'] ?? []) as $part)
                                    <div @class([
                                        'p-4 rounded-lg border text-base',
                                        'border-emerald-300 bg-emerald-50 text-emerald-700' => $part['gradable'] && $part['isCorrect'],
                                        'border-rose-300 bg-rose-50 text-rose-600' => $part['gradable'] && ! $part['isCorrect'],
                                        'border-sky-200 bg-sky-50 text-sky-700' => ! $part['gradable'],
                                    ])>
                                        <p class="font-medium mb-1">Phần {{ strtoupper($part['code']) }} ({{ $part['points'] }} điểm)</p>
                                        <p>Bạn trả lời: {{ is_bool($part['yourAnswer']) ? ($part['yourAnswer'] ? 'Đúng' : 'Sai') : ($part['yourAnswer'] ?: '—') }}</p>
                                        @if ($part['gradable'])
                                            <p>{{ $part['isCorrect'] ? '✓ Chính xác' : '✕ Chưa đúng — đáp án đúng: '.$part['correctAnswer'] }}</p>
                                        @else
                                            <p>📨 Đã ghi nhận — phần tự luận chưa có chấm tự động.</p>
                                        @endif
                                    </div>
                                @endforeach
                            @else
                                <div class="p-4 rounded-lg border border-slate-200 text-base text-slate-500">
                                    Ngôn ngữ: <span class="font-medium text-slate-700">{{ $feedback['yourLanguage'] ?: '—' }}</span>
                                </div>
                                <pre class="p-4 rounded-lg border border-slate-700 bg-[#272822] text-sm text-slate-100 font-mono overflow-x-auto whitespace-pre-wrap">{{ $feedback['yourCode'] }}</pre>
                            @endif

                            @if ($feedback['gradable'])
                                <div @class([
                                    'rounded-lg p-4 text-base font-medium text-center',
                                    'bg-emerald-50 text-emerald-700 border border-emerald-200' => $feedback['isCorrect'],
                                    'bg-rose-50 text-rose-600 border border-rose-200' => ! $feedback['isCorrect'],
                                ])>
                                    {{-- SỬA 3/9 (2, đồng bộ với by-question-play.blade.php) — câu
                                         Lập trình hiện nhãn verdict CỤ THỂ (VerdictStatus::label(),
                                         vd "Sai kết quả (Wrong Answer)"/"Lỗi biên dịch (Compilation
                                         Error)"/"Quá thời gian (Time Limit Exceeded)") thay vì luôn
                                         "✕ Chưa đúng" chung chung — MCQ/điền đáp án/composite giữ
                                         nguyên câu cũ (không có nhiều dạng verdict như Lập trình). --}}
                                    @if ($question->type->value === 'coding' && ! $feedback['isCorrect'] && $feedback['codingVerdictLabel'])
                                        ✕ {{ $feedback['codingVerdictLabel'] }}
                                    @else
                                        {{ $feedback['isCorrect'] ? '✓ Chính xác!' : '✕ Chưa đúng — xem đáp án ở trên.' }}
                                    @endif
                                </div>
                                {{-- SỬA 3/9 (2, khách yêu cầu: hiện chi tiết từng test đúng/sai +
                                     cho tải test sai về) — danh sách ĐẦY ĐỦ từng test case
                                     (PracticeByQuestionService::judgeCodingAnswer() trả
                                     'codingTestCases', xem CodeJudgingService::judge()). Test
                                     ĐÚNG chỉ hiện 1 dòng khoá cứng — test SAI bấm vào mới xổ chi
                                     tiết (script cuối trang, cùng logic by-question-play.blade.php). --}}
                                @if ($question->type->value === 'coding' && ! empty($feedback['codingTestCases']))
                                    @php
                                        $tcs = $feedback['codingTestCases'];
                                        $tcPassed = collect($tcs)->where('isAccepted', true)->count();
                                        $tcFailed = collect($tcs)->reject(fn ($t) => $t['isAccepted'])->values();
                                    @endphp
                                    <div class="mt-1">
                                        <p class="text-sm text-slate-500 mb-2">Kết quả từng test: <span class="font-medium text-slate-700">Đúng {{ $tcPassed }}/{{ count($tcs) }}</span></p>
                                        <div class="space-y-1">
                                            @foreach ($tcs as $tc)
                                                <div class="rounded-lg border border-slate-200 overflow-hidden" data-test-case-row>
                                                    <button type="button"
                                                            @class([
                                                                'w-full flex items-center justify-between gap-2 px-3 py-2 text-sm text-left',
                                                                'bg-emerald-50 text-emerald-700' => $tc['isAccepted'],
                                                                'bg-rose-50 text-rose-600 hover:bg-rose-100' => ! $tc['isAccepted'],
                                                            ])
                                                            @if ($tc['isAccepted']) disabled @else data-test-case-toggle @endif>
                                                        <span>{{ $tc['isAccepted'] ? '✓' : '✕' }} Test {{ $tc['index'] }} — {{ $tc['statusLabel'] }}</span>
                                                        @if (! $tc['isAccepted'])
                                                            <span data-test-case-arrow>▾</span>
                                                        @endif
                                                    </button>
                                                    @if (! $tc['isAccepted'])
                                                        <div class="hidden px-3 py-2 text-xs text-slate-600 bg-white border-t border-slate-200 space-y-2" data-test-case-detail>
                                                            <div>
                                                                <p class="font-medium text-slate-500 mb-1">Dữ liệu vào</p>
                                                                <pre class="p-2 rounded bg-slate-50 border border-slate-200 overflow-x-auto whitespace-pre-wrap">{{ $tc['input'] !== '' ? $tc['input'] : '(rỗng)' }}</pre>
                                                            </div>
                                                            <div>
                                                                <p class="font-medium text-slate-500 mb-1">Kết quả mong đợi</p>
                                                                <pre class="p-2 rounded bg-slate-50 border border-slate-200 overflow-x-auto whitespace-pre-wrap">{{ $tc['expectedOutput'] }}</pre>
                                                            </div>
                                                            <div>
                                                                <p class="font-medium text-slate-500 mb-1">Chương trình của bạn in ra</p>
                                                                <pre class="p-2 rounded bg-slate-50 border border-slate-200 overflow-x-auto whitespace-pre-wrap">{{ $tc['actualOutput'] !== null && $tc['actualOutput'] !== '' ? $tc['actualOutput'] : '(không có gì)' }}</pre>
                                                            </div>
                                                            @if ($tc['compileOutput'] || $tc['stderr'])
                                                                <div>
                                                                    <p class="font-medium text-rose-500 mb-1">Lỗi</p>
                                                                    <pre class="p-2 rounded bg-rose-50 border border-rose-200 text-rose-700 overflow-x-auto whitespace-pre-wrap">{{ trim(($tc['compileOutput'] ?? '')."\n".($tc['stderr'] ?? '')) }}</pre>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        @if ($tcFailed->isNotEmpty())
                                            <button type="button" class="mt-2 text-sm text-rose-600 font-medium"
                                                    data-download-failed-tests
                                                    data-question-id="{{ $question->id }}"
                                                    data-tests="{{ $tcFailed->toJson() }}">
                                                ⬇️ Tải test sai (.txt)
                                            </button>
                                        @endif
                                    </div>
                                @endif
                            @else
                                <div class="rounded-lg p-4 text-base font-medium text-center bg-sky-50 text-sky-700 border border-sky-200">
                                    📨 Đã ghi nhận bài làm — chưa có chấm tự động cho phần này.
                                </div>
                            @endif

                            <form method="POST" action="{{ route('student.practiceByQuestion.next') }}">
                                @csrf
                                <button type="submit" class="w-full px-4 py-3 rounded-lg bg-rose-600 text-white text-base font-medium">
                                    Hoàn tất bài tập ›
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
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

    {{-- Ô viết code "như VSCode" (chỉ dùng khi bài tập là Lập trình) — cùng kiểu nhúng CodeMirror
         qua CDN như student/practice/by-question-play.blade.php, tải riêng ở view này. --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.21/theme/monokai.css">
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/clike/clike.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/python/python.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.21/addon/display/placeholder.js"></script>
    <style>
        .CodeMirror { height: 420px; font-size: 14px; }
    </style>
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
