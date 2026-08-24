@extends('layouts.student')

@section('title', 'Luyện tập theo câu')
@section('page-title', 'Luyện tập theo câu')

@section('content')
    @php
        $finished = $finished ?? false;
        $feedback = $feedback ?? null;
        $options = $options ?? [];
    @endphp

    @if ($finished)
        <div class="max-w-xl mx-auto text-center bg-white rounded-2xl border border-slate-200 p-8 mt-8">
            <div class="text-5xl mb-3">🎉</div>
            <h2 class="text-xl font-semibold text-slate-800 mb-2">Đã luyện hết {{ $total }} câu!</h2>
            <p class="text-slate-500 mb-6">Đúng <span class="font-semibold text-emerald-600">{{ $correct }}</span>/{{ $answered }} câu đã trả lời{{ $answered < $total ? ' ('.($total - $answered).' câu bỏ qua)' : '' }}.</p>
            <div class="flex items-center justify-center gap-3">
                <a href="{{ route('student.practiceByQuestion.setup') }}" class="px-5 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">Luyện lại ›</a>
                <a href="{{ route('student.practice.index') }}" class="px-5 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm font-medium hover:border-rose-200 hover:text-rose-600">Về Luyện tập</a>
            </div>
        </div>
    @else
        <div class="max-w-2xl mx-auto">
            <div class="flex items-center justify-between mb-4">
                <p class="text-sm text-slate-500">Câu {{ $progress['current'] }}/{{ $progress['total'] }} · Đúng {{ $progress['correct'] }}/{{ $progress['answered'] }}</p>
                <form method="POST" action="{{ route('student.practiceByQuestion.stop') }}">
                    @csrf
                    <button type="submit" class="text-sm text-slate-400 hover:text-rose-600">Dừng luyện tập ✕</button>
                </form>
            </div>

            <div class="w-full h-1.5 rounded-full bg-slate-100 mb-6 overflow-hidden">
                <div class="h-full bg-rose-500 transition-all" style="width: {{ $progress['total'] > 0 ? round($progress['current'] / $progress['total'] * 100) : 0 }}%"></div>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 lg:p-6">
                @php
                    $typeBadge = match ($question->type->value) {
                        'mcq' => '🔤 Trắc nghiệm',
                        'fill_blank' => '✏️ Điền đáp án',
                        default => '💻 Lập trình',
                    };
                @endphp
                <x-status-badge tone="info">{{ $typeBadge }}</x-status-badge>
                <h3 class="font-semibold text-slate-800 text-lg mt-3 mb-1">{{ $question->title }}</h3>
                {{-- SỬA 24/8 — $question->body là HTML do CKEditor lưu ra (thẻ <p>, <ul>...),
                     KHÔNG phải text thường — {{ }} escape làm hiện nguyên thẻ ra màn hình học
                     sinh (ví dụ "<p>...</p>" hiện thành chữ). Đổi sang {!! !!} + <div> (không
                     dùng <p> bọc ngoài vì nội dung bên trong đã có thể tự chứa <p> khác, lồng
                     <p> trong <p> là HTML không hợp lệ) để hiển thị đúng định dạng đã soạn —
                     cùng class .rich-content + quy tắc ul/ol/p ở admin/content/show.blade.php. --}}
                <div class="rich-content text-sm text-slate-600 mb-5">{!! $question->body !!}</div>

                @if ($question->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-1 mb-5">
                        @foreach ($question->tags as $t)
                            <span class="text-[11px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500">📚 {{ $t->name }}</span>
                        @endforeach
                    </div>
                @endif

                @if ($feedback === null)
                    {{-- Chưa trả lời câu này — hiện form nhập đáp án. SỬA 24/8 (v4) — thêm
                         nhánh 'coding': viết code + chọn ngôn ngữ (cùng kiểu input với màn làm
                         đề student/assessment/take.blade.php), không có phương án đúng/sai để
                         so khớp nên nút bấm đổi chữ thành "Ghi nhận bài làm" thay vì "Kiểm tra
                         đáp án" — tránh ngụ ý sẽ có chấm đúng/sai ngay. --}}
                    <form method="POST" action="{{ route('student.practiceByQuestion.answer') }}" class="space-y-3">
                        @csrf
                        @if ($question->type->value === 'mcq')
                            @foreach ($options as $i => $opt)
                                @if ($opt !== '' && $opt !== null)
                                    <label class="flex items-center gap-2 p-3 rounded-lg border border-slate-200 hover:border-rose-200 cursor-pointer has-[:checked]:border-rose-300 has-[:checked]:bg-rose-50">
                                        <input type="radio" name="selected_option" value="{{ $i }}" required>
                                        <span class="text-sm text-slate-700">{{ $opt }}</span>
                                    </label>
                                @endif
                            @endforeach
                        @elseif ($question->type->value === 'fill_blank')
                            <input type="text" name="text" required maxlength="500" placeholder="Nhập đáp án..."
                                   class="w-full rounded-lg border border-slate-200 text-sm p-3">
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
                                <textarea name="code_source" data-code-editor required class="hidden"></textarea>
                            </div>
                            <p class="text-xs text-slate-400">Câu Lập trình chưa có chấm tự động — bài làm chỉ được ghi nhận, không báo đúng/sai.</p>
                        @endif
                        <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                            {{ $question->type->value === 'coding' ? 'Ghi nhận bài làm' : 'Kiểm tra đáp án' }}
                        </button>
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
                                        'flex items-center gap-2 p-3 rounded-lg border text-sm',
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
                            <div class="p-3 rounded-lg border border-slate-200 text-sm text-slate-500">
                                Bạn trả lời: <span class="font-medium text-slate-700">{{ $feedback['yourText'] }}</span>
                            </div>
                            <div class="p-3 rounded-lg border border-emerald-300 bg-emerald-50 text-sm text-emerald-700">
                                Đáp án đúng: {{ implode(', ', $feedback['acceptedAnswers']) }}
                            </div>
                        @else
                            {{-- SỬA 24/8 (v4) — câu Lập trình chưa có sandbox chấm, chỉ hiện lại
                                 bài đã nộp (code + ngôn ngữ), không có khối "đáp án đúng". --}}
                            <div class="p-3 rounded-lg border border-slate-200 text-sm text-slate-500">
                                Ngôn ngữ: <span class="font-medium text-slate-700">{{ $feedback['yourLanguage'] ?: '—' }}</span>
                            </div>
                            <pre class="p-3 rounded-lg border border-slate-700 bg-[#272822] text-xs text-slate-100 font-mono overflow-x-auto whitespace-pre-wrap">{{ $feedback['yourCode'] }}</pre>
                        @endif

                        @if ($feedback['gradable'])
                            <div @class([
                                'rounded-lg p-3 text-sm font-medium text-center',
                                'bg-emerald-50 text-emerald-700 border border-emerald-200' => $feedback['isCorrect'],
                                'bg-rose-50 text-rose-600 border border-rose-200' => ! $feedback['isCorrect'],
                            ])>
                                {{ $feedback['isCorrect'] ? '✓ Chính xác!' : '✕ Chưa đúng — xem đáp án ở trên.' }}
                            </div>
                        @else
                            <div class="rounded-lg p-3 text-sm font-medium text-center bg-sky-50 text-sky-700 border border-sky-200">
                                📨 Đã ghi nhận bài làm — chưa có chấm tự động cho Lập trình.
                            </div>
                        @endif

                        <form method="POST" action="{{ route('student.practiceByQuestion.next') }}">
                            @csrf
                            <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-rose-600 text-white text-sm font-medium">
                                {{ $progress['current'] < $progress['total'] ? 'Câu tiếp theo ›' : 'Xem kết quả ›' }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <style>
        .rich-content ul { list-style: disc; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content ol { list-style: decimal; padding-left: 1.25rem; margin-bottom: 0.5rem; }
        .rich-content p { margin-bottom: 0.5rem; }
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
        .CodeMirror { height: 320px; font-size: 13px; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
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
                // CodeMirror thay textarea gốc bằng 1 div riêng — phải gọi save() để đồng bộ
                // nội dung đang gõ ngược lại textarea gốc TRƯỚC KHI form submit thật (giống
                // cách CKEditor updateSourceElement() ở partials/rich-editor-assets.blade.php).
                form.addEventListener('submit', function () { editor.save(); });
            }
        });
    </script>
@endpush
