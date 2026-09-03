@extends('layouts.student')

@section('title', 'Luyện tập theo câu')
@section('page-title', 'Luyện tập theo câu')

@section('content')
    @php
        $finished = $finished ?? false;
        $feedback = $feedback ?? null;
        $options = $options ?? [];
    @endphp

    {{-- SỬA 31/8 (3, khách yêu cầu tách riêng UI) — view này giờ CHỈ còn phục vụ đúng 1 luồng
         "Luyện tập theo câu" (mode luôn null/'pool' ở đây) — luồng "Làm bài" 1 bài tập sản phẩm
         (mode='single_question') đã có UI riêng, xem student.practice.exercise-play +
         PracticeByQuestionController::play(). Bỏ nhánh mode==='single_question' từng có ở đây
         (KHÔNG còn được gọi tới nữa, tránh code chết gây hiểu nhầm còn dùng chung). --}}
    {{-- SỬA 3/9 (khách yêu cầu: bấm chấm bài KHÔNG reload cả trang, chỉ hiện spinner ở nút rồi
         hiện kết quả tại chỗ) — bọc id="practice-container" quanh CẢ 2 nhánh (đã xong pool /
         chưa xong) làm 1 khối DUY NHẤT có thể thay nguyên khối bằng JS: form "Ghi nhận bài
         làm"/"Kiểm tra đáp án" giờ submit qua fetch() (script cuối trang) thay vì để trình
         duyệt tự điều hướng — fetch tới ĐÚNG route cũ (student.practiceByQuestion.answer),
         Laravel vẫn redirect sang GET .play như cũ (KHÔNG đổi controller/route nào), fetch tự
         đi theo redirect đó và nhận về HTML trang mới, JS chỉ lấy đúng #practice-container
         trong HTML đó rồi thay vào chỗ cũ — không cần sửa gì ở backend. --}}
    <div id="practice-container">
    @if ($finished)
        <div class="max-w-3xl mx-auto text-center bg-white rounded-2xl border border-slate-200 p-8 mt-8">
            <div class="text-5xl mb-3">🎉</div>
            <h2 class="text-2xl font-semibold text-slate-800 mb-2">Đã luyện hết {{ $total }} câu!</h2>
            <p class="text-base text-slate-500 mb-6">Đúng <span class="font-semibold text-emerald-600">{{ $correct }}</span>/{{ $answered }} câu đã trả lời{{ $answered < $total ? ' ('.($total - $answered).' câu bỏ qua)' : '' }}.</p>
            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('student.practiceByQuestion.setup') }}" class="px-5 py-3 rounded-lg bg-rose-600 text-white text-base font-medium">Luyện lại ›</a>
                <a href="{{ route('student.practice.index') }}" class="px-5 py-3 rounded-lg border border-slate-200 text-slate-600 text-base font-medium hover:border-rose-200 hover:text-rose-600">Về Luyện tập</a>
            </div>
        </div>
    @else
        <div class="max-w-8xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <p class="text-base text-slate-500">Câu {{ $progress['current'] }}/{{ $progress['total'] }} · Đúng {{ $progress['correct'] }}/{{ $progress['answered'] }}</p>
                <form method="POST" action="{{ route('student.practiceByQuestion.stop') }}">
                    @csrf
                    <button type="submit" class="text-base text-slate-400 hover:text-rose-600">Dừng luyện tập ✕</button>
                </form>
            </div>

            <div class="w-full h-2 rounded-full bg-slate-100 mb-6 overflow-hidden">
                <div class="h-full bg-rose-500 transition-all" style="width: {{ $progress['total'] > 0 ? round($progress['current'] / $progress['total'] * 100) : 0 }}%"></div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-6 lg:p-8">
                @php
                    $typeBadge = match ($question->type->value) {
                        'mcq' => '🔤 Trắc nghiệm',
                        'fill_blank' => '✏️ Điền đáp án',
                        'composite' => '🧩 Câu hỏi nhiều phần',
                        default => '💻 Lập trình',
                    };
                    $assets = $assets ?? [];
                    // SỬA 3/9 (khách chốt: "hiển thị thẳng file ra luôn") — câu hỏi nhập từ ZIP
                    // có đề bài THẬT nằm trong statement.pdf đính kèm ($question->body chỉ là
                    // text trích thô, có thể mất định dạng công thức/bảng...) — nhúng THẲNG file
                    // PDF làm đề bài chính, cùng cách đã làm ở student/practice/exercise-play.
                    // blade.php (route student.practiceByQuestion.statement — kiểm tra quyền y
                    // hệt asset() ở PracticeByQuestionController, khác route access.resource.
                    // exerciseAttachment bên exercise-play vì route đó ĐÒI product_id thật, câu
                    // hỏi ở "Luyện tập theo câu" có thể thuộc Kho chung (product_id null)).
                    $statementUrl = $question->attachmentInfo('statement') !== null
                        ? route('student.practiceByQuestion.statement', $question)
                        : null;
                @endphp
                <x-status-badge tone="info">{{ $typeBadge }}</x-status-badge>
                <h3 class="font-semibold text-slate-800 text-2xl mt-3 mb-1">{{ $question->title }}</h3>

                @if ($statementUrl)
                    <div class="rounded-lg overflow-hidden border border-slate-200 mb-2">
                        <iframe src="{{ $statementUrl }}" class="w-full" style="height: 560px;" title="Đề bài"></iframe>
                    </div>
                    <a href="{{ $statementUrl }}" target="_blank" rel="noopener" class="text-xs text-rose-600 font-medium mb-5 inline-block">Mở đề bài trong tab mới ›</a>
                @else
                    {{-- SỬA 24/8 — $question->body là HTML do CKEditor lưu ra (thẻ <p>, <ul>...),
                         KHÔNG phải text thường — {{ }} escape làm hiện nguyên thẻ ra màn hình học
                         sinh (ví dụ "<p>...</p>" hiện thành chữ). Đổi sang {!! !!} + <div> (không
                         dùng <p> bọc ngoài vì nội dung bên trong đã có thể tự chứa <p> khác, lồng
                         <p> trong <p> là HTML không hợp lệ) để hiển thị đúng định dạng đã soạn —
                         cùng class .rich-content + quy tắc ul/ol/p ở admin/content/show.blade.php.
                         Fallback: chỉ hiện khi câu hỏi KHÔNG có statement.pdf (câu tự soạn tay). --}}
                    <div class="rich-content text-base text-slate-600 mb-5">{!! $question->body !!}</div>
                @endif

                @if ($question->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mb-5">
                        @foreach ($question->tags as $t)
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">📚 {{ $t->name }}</span>
                        @endforeach
                    </div>
                @endif

                {{-- SỬA 31/8 (2, "mở rộng ZIP bài tập" — audio/ảnh...): học liệu CẦN để trả lời
                     (vd nghe audio nghe-hiểu) — hiện NGAY ở đây (khác đề bài PDF, chỉ xem qua
                     link riêng ở "Xem đề bài"), luôn hiện bất kể đã trả lời hay chưa. --}}
                @if (! empty($assets))
                    <div class="mb-5 space-y-3">
                        @foreach ($assets as $asset)
                            <div class="p-3 rounded-lg border border-slate-200 bg-slate-50">
                                @if ($asset['kind'] === 'audio')
                                    <audio controls preload="none" class="w-full" src="{{ $asset['url'] }}"></audio>
                                @elseif ($asset['kind'] === 'image')
                                    {{-- max-width:100%/height:auto đã có sẵn ở Tailwind preflight cho <img>, không cần class max-w-full. --}}
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

                @if ($feedback === null)
                    {{-- Chưa trả lời câu này — hiện form nhập đáp án. SỬA 24/8 (v4) — thêm
                         nhánh 'coding': viết code + chọn ngôn ngữ (cùng kiểu input với màn làm
                         đề student/assessment/take.blade.php), không có phương án đúng/sai để
                         so khớp nên nút bấm đổi chữ thành "Ghi nhận bài làm" thay vì "Kiểm tra
                         đáp án" — tránh ngụ ý sẽ có chấm đúng/sai ngay. --}}
                    {{-- SỬA 3/9 (khách yêu cầu: bấm nút KHÔNG reload trang, chỉ hiện spinner rồi
                         hiện kết quả tại chỗ) — data-ajax-answer đánh dấu để script cuối trang
                         bắt sự kiện submit của ĐÚNG form này (không đụng form "Dừng luyện tập"/
                         "Câu tiếp theo" — 2 form đó vẫn điều hướng bình thường vì không có độ
                         trễ mạng đáng kể). --}}
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
                            {{-- SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu) — câu nhiều
                                 phần, mỗi phần 1 dạng con khác nhau (xem $compositeParts —
                                 SANITIZED, không có đáp án đúng, xem PracticeByQuestionService::
                                 sanitizedCompositeParts()). Mỗi phần gửi lên qua
                                 name="parts[<code phần>]" — PracticeByQuestionService::
                                 gradeCompositeParts() đọc đúng cấu trúc này. --}}
                            @foreach (($compositeParts ?? []) as $part)
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
                            {{-- SỬA 24/8 (v5) — khách yêu cầu ô viết code "như VSCode" thay vì
                                 textarea trơn: nhúng CodeMirror 5 qua CDN (script init ở
                                 @push('scripts') cuối trang) — có số dòng, tô màu cú pháp theo
                                 ngôn ngữ chọn ở dropdown, theme tối "monokai". --}}
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
                                     focusable") — bỏ 'required': control này bị CodeMirror ẩn đi
                                     (class hidden/display:none) để thay bằng div riêng, nhưng
                                     trình duyệt validate HTML5 chạy TRƯỚC sự kiện 'submit' (nên
                                     editor.save() ở dưới không kịp đồng bộ nội dung), và không
                                     thể focus 1 control required đang ẩn để báo lỗi — kết quả là
                                     trình duyệt CHẶN LUÔN submit, form không gửi đi được, học
                                     sinh bấm nút không thấy gì xảy ra. Code rỗng vẫn được xử lý
                                     an toàn ở server (CodeJudgingService::judge() coi là lỗi biên
                                     dịch/không chấm được, không lỗi 500). --}}
                                <textarea name="code_source" data-code-editor class="hidden"></textarea>
                            </div>
                        @endif
                        <button type="submit" class="w-full px-4 py-3 rounded-lg bg-rose-600 text-white text-base font-medium">
                            {{ $question->type->value === 'coding' ? 'Ghi nhận bài làm' : 'Kiểm tra đáp án' }}
                        </button>
                        {{-- SỬA 3/9 — chỗ hiện lỗi khi gửi AJAX thất bại (mất mạng...), thay vì
                             alert() gây gián đoạn. Ẩn mặc định, script cuối trang bật lên khi cần. --}}
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
                            {{-- SỬA 24/8 (v4) — câu Lập trình chưa có sandbox chấm, chỉ hiện lại
                                 bài đã nộp (code + ngôn ngữ), không có khối "đáp án đúng". --}}
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
                                {{-- SỬA 3/9 (khách hỏi "Chưa đúng là sao") — câu Lập trình hiện
                                     nhãn verdict CỤ THỂ (VerdictStatus::label(), vd "Sai kết quả
                                     (Wrong Answer)"/"Lỗi biên dịch (Compilation Error)"/"Quá thời
                                     gian (Time Limit Exceeded)") thay vì luôn "✕ Chưa đúng" chung
                                     chung — MCQ/điền đáp án/composite giữ nguyên câu cũ (không
                                     có nhiều dạng verdict như Lập trình). --}}
                                @if ($question->type->value === 'coding' && ! $feedback['isCorrect'] && $feedback['codingVerdictLabel'])
                                    ✕ {{ $feedback['codingVerdictLabel'] }}
                                @else
                                    {{ $feedback['isCorrect'] ? '✓ Chính xác!' : '✕ Chưa đúng — xem đáp án ở trên.' }}
                                @endif
                            </div>
                            @if ($question->type->value === 'coding' && ! $feedback['isCorrect'] && $feedback['codingFailureDetail'])
                                <pre class="p-4 rounded-lg border border-rose-200 bg-rose-50 text-sm text-rose-700 font-mono overflow-x-auto whitespace-pre-wrap">{{ $feedback['codingFailureDetail'] }}</pre>
                            @endif
                        @else
                            <div class="rounded-lg p-4 text-base font-medium text-center bg-sky-50 text-sky-700 border border-sky-200">
                                📨 Đã ghi nhận bài làm — máy chấm không phản hồi được lúc này (kiểm tra lại đường hầm/kết nối tới máy chấm), thử nộp lại sau.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('student.practiceByQuestion.next') }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-3 rounded-lg bg-rose-600 text-white text-base font-medium">
                                {{ $progress['current'] < $progress['total'] ? 'Câu tiếp theo ›' : 'Xem kết quả ›' }}
                            </button>
                        </form>
                    </div>
                @endif
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

    {{-- SỬA 3/9 (khách yêu cầu nút loading xoay xoay lúc chấm) — viết CSS thường (không dùng
         class Tailwind) vì màu trắng-trên-nền-rose-600 cho spinner này CHƯA có sẵn trong file
         CSS đã build (public/build/assets/*.css là build JIT theo class thực tế đang dùng —
         thêm class Tailwind MỚI ở đây sẽ không hiện ra gì cho tới khi ai đó chạy `npm run
         build`, mà tôi không có node/npm ở môi trường triển khai để tự chạy) — CSS thường luôn
         hoạt động ngay, không cần build lại gì. --}}
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

    {{-- SỬA 24/8 (v5) — ô viết code "như VSCode": nhúng CodeMirror 5 qua CDN (cùng kiểu nhúng
         thư viện ngoài qua CDN như CKEditor ở partials/rich-editor-assets.blade.php). Chỉ tải ở
         trang này — không đụng màn làm đề khác vì khách chỉ yêu cầu đổi ở "Luyện tập theo câu". --}}
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
        // mỗi lần thay #practice-container bằng AJAX (xem submit handler bên dưới) — câu tiếp
        // theo cũng có thể là câu Lập trình, cần CodeMirror mới cho <textarea> mới trong DOM
        // vừa thay vào, DOMContentLoaded chỉ chạy 1 lần lúc tải trang nên không tự chạy lại.
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
        // hiện kết quả tại chỗ) — nghe sự kiện submit kiểu delegation trên toàn trang (không
        // gắn trực tiếp vào 1 form cố định) vì form thật sự tồn tại lúc chạy đoạn script này có
        // thể bị THAY MỚI hoàn toàn sau mỗi lần nộp bài (xem replaceWith() bên dưới) — gắn
        // listener kiểu delegation thì luôn bắt được form MỚI mà không cần gắn lại tay.
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
    </script>
@endpush
