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
    {{-- SỬA 15/9 (khách: "logic đúng rồi, làm lại UI trang này đi xấu quá, thiết kế theo style
         các trang khác") — CHỈ đổi trình bày, KHÔNG đụng logic/route/controller/service nào.
         3 lỗi trình bày đã sửa:
           1. Khung ngoài cũ ghi class 'max-w-8xl' — Tailwind KHÔNG có cỡ 8xl (chỉ tới 7xl) và
              dự án cũng không khai báo thêm trong @theme của resources/css/app.css, nên class
              đó rơi vào hư không: trang trải hết bề ngang 1780px của layouts/workspace, chữ và
              ô nhập kéo dài hết màn 27" rất khó đọc. Đổi về 'max-w-7xl' — ĐÚNG cỡ mà trang anh
              em student/practice/exercise-play.blade.php đang dùng.
           2. Đề bài và vùng làm bài xếp DỌC chồng lên nhau nên học sinh phải cuộn lên cuộn
              xuống liên tục khi viết code. Đổi sang lưới 2 cột từ breakpoint lg (đề bài trái,
              bài làm phải) — cùng bố cục exercise-play.blade.php đã làm, cột đề bài dính
              (sticky) để cuộn kết quả dài vẫn thấy đề.
           3. Khung PDF đề bài rộng gấp đôi chiều cao nên trang A4 dựng đứng bị co lại giữa 2
              mảng xám to ở 2 bên. Cột trái hẹp lại đã đỡ, thêm '#view=FitH' (tham số mở PDF
              chuẩn, trình duyệt nào không hiểu thì bỏ qua) để trình xem PDF canh vừa BỀ NGANG.
              Chỉ thêm vào src của iframe — KHÔNG thêm vào link "Mở đề bài trong tab mới"; phần
              sau dấu # không bao giờ được gửi lên server nên route/kiểm tra quyền y nguyên.
         Cỡ chữ/đệm/bo góc/màu lấy đúng bộ dùng chung (x-ws.card, x-ws.badge, x-ws.btn):
         rounded-3xl + border-sky-100 + shadow-[0_2px_8px_rgba(0,90,180,.04)], chữ 11-13px,
         icon lucide thay emoji. Mọi name=, data-*, id, route giữ NGUYÊN 100% để script AJAX
         cuối trang (submit/CodeMirror/mở test sai/tải test sai) chạy y như cũ. --}}
    <div id="practice-container">
    @if ($finished)
        <div class="mx-auto mt-6 max-w-2xl rounded-3xl border border-sky-100 bg-gradient-to-br from-sky-50 via-white to-blue-50 p-6 text-center shadow-[0_2px_8px_rgba(0,90,180,.04)] sm:p-8">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl border border-amber-100 bg-amber-50 text-amber-600">
                <x-lucide name="trophy" class="h-6 w-6" />
            </div>
            <p class="mt-3 text-[10px] font-bold uppercase tracking-[.14em] text-blue-500">Hoàn thành</p>
            <h2 class="mt-1 text-xl font-bold text-slate-800 sm:text-2xl">Đã luyện hết {{ $total }} câu!</h2>
            <p class="mt-2 text-[13px] text-slate-500">Đúng <span class="font-bold text-emerald-600">{{ $correct }}</span>/{{ $answered }} câu đã trả lời{{ $answered < $total ? ' ('.($total - $answered).' câu bỏ qua)' : '' }}.</p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                <x-ws.btn :href="route('student.practiceByQuestion.setup')" size="lg" icon-right="arrow-right">Luyện lại</x-ws.btn>
                <x-ws.btn :href="route('student.practice.index')" variant="ghost" size="lg">Về Luyện tập</x-ws.btn>
            </div>
        </div>
    @else
        @php
            $typeValue = $question->type->value;
            // Nhãn + icon theo dạng câu. Trước dùng emoji trơn ('🔤', '💻'...) — đổi sang icon
            // lucide cho khớp mọi trang khác trong khu làm việc. Nhãn chữ giữ nguyên.
            $typeMeta = match ($typeValue) {
                'mcq' => ['label' => 'Trắc nghiệm', 'icon' => 'list'],
                'fill_blank' => ['label' => 'Điền đáp án', 'icon' => 'pencil'],
                'composite' => ['label' => 'Câu hỏi nhiều phần', 'icon' => 'layers'],
                default => ['label' => 'Lập trình', 'icon' => 'code-2'],
            };
            $assets = $assets ?? [];
            // SỬA 3/9 (khách chốt: "hiển thị thẳng file ra luôn") — câu hỏi nhập từ ZIP có đề
            // bài THẬT nằm trong statement.pdf đính kèm ($question->body chỉ là text trích thô,
            // có thể mất định dạng công thức/bảng...) — nhúng THẲNG file PDF làm đề bài chính,
            // cùng cách đã làm ở student/practice/exercise-play.blade.php (route
            // student.practiceByQuestion.statement — kiểm tra quyền y hệt asset() ở
            // PracticeByQuestionController, khác route access.resource.exerciseAttachment bên
            // exercise-play vì route đó ĐÒI product_id thật, câu hỏi ở "Luyện tập theo câu" có
            // thể thuộc Kho chung (product_id null)).
            $statementUrl = $question->attachmentInfo('statement') !== null
                ? route('student.practiceByQuestion.statement', $question)
                : null;
            $progressPercent = $progress['total'] > 0 ? round($progress['current'] / $progress['total'] * 100) : 0;
        @endphp

        <div class="mx-auto w-full max-w-7xl">
            {{-- Thanh đầu trang: dạng câu + tên câu bên trái, tiến độ + nút dừng bên phải, thanh
                 tiến độ chạy hết bề ngang phía dưới. Gom 3 khối rời rạc cũ (2 chip, nút dừng,
                 thanh tiến độ) vào 1 thẻ cho gọn, đúng kiểu thẻ dùng chung x-ws.card. --}}
            <div class="rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <x-ws.icon-tile :icon="$typeMeta['icon']" tone="blue" />
                        <div class="min-w-0">
                            <p class="text-[10px] font-bold uppercase tracking-[.14em] text-blue-500">Luyện tập theo câu</p>
                            <h2 class="mt-0.5 truncate text-base font-bold leading-tight text-slate-800 sm:text-lg">{{ $question->title }}</h2>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <x-ws.badge tone="brand">Câu {{ $progress['current'] }}/{{ $progress['total'] }}</x-ws.badge>
                        <x-ws.badge tone="success">
                            <x-lucide name="check" class="h-3 w-3 shrink-0" />Đúng {{ $progress['correct'] }}/{{ $progress['answered'] }}
                        </x-ws.badge>
                        <form method="POST" action="{{ route('student.practiceByQuestion.stop') }}">
                            @csrf
                            <button type="submit" class="inline-flex items-center gap-1 rounded-full border border-sky-100 px-2.5 py-1 text-[11px] font-bold text-slate-400 transition-colors hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600">
                                <x-lucide name="x" class="h-3 w-3 shrink-0" />Dừng luyện tập
                            </button>
                        </form>
                    </div>
                </div>

                <div class="mt-3.5 flex items-center gap-2.5">
                    <div class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-sky-400 transition-all" style="width: {{ $progressPercent }}%"></div>
                    </div>
                    <span class="shrink-0 text-[11px] font-bold tabular-nums text-slate-400">{{ $progressPercent }}%</span>
                </div>
            </div>

            {{-- Lưới 2 cột: ĐỀ BÀI trái · BÀI LÀM phải (từ lg trở lên; dưới lg vẫn xếp dọc như
                 cũ nên điện thoại không đổi gì). items-start để cột trái dính được. --}}
            <div class="mt-4 grid grid-cols-1 items-start gap-4 lg:grid-cols-2 xl:gap-5">

                {{-- ── CỘT TRÁI: ĐỀ BÀI ────────────────────────────────────────────────── --}}
                <div class="rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] sm:p-5 lg:sticky lg:top-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600">
                                <x-lucide name="scroll-text" class="h-3.5 w-3.5" />
                            </span>
                            <h3 class="min-w-0 truncate text-sm font-bold text-slate-800">Đề bài</h3>
                        </div>
                        <x-ws.badge tone="info">
                            <x-lucide :name="$typeMeta['icon']" class="h-3 w-3 shrink-0" />{{ $typeMeta['label'] }}
                        </x-ws.badge>
                    </div>

                    @if ($statementUrl)
                        <div class="overflow-hidden rounded-2xl border border-sky-100 bg-slate-100">
                            <iframe src="{{ $statementUrl }}#view=FitH" class="block h-[420px] w-full sm:h-[520px] xl:h-[600px]" title="Đề bài"></iframe>
                        </div>
                        <a href="{{ $statementUrl }}" target="_blank" rel="noopener"
                           class="mt-2.5 inline-flex items-center gap-1 rounded-xl bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-blue-600 transition-colors hover:bg-blue-100 hover:text-blue-700">
                            <x-lucide name="file-text" class="h-3.5 w-3.5 shrink-0" />Mở đề bài trong tab mới
                            <x-lucide name="chevron-right" class="h-3 w-3 shrink-0" />
                        </a>
                    @else
                        {{-- SỬA 24/8 — $question->body là HTML do CKEditor lưu ra (thẻ <p>, <ul>...),
                             KHÔNG phải text thường — {{ }} escape làm hiện nguyên thẻ ra màn hình học
                             sinh (ví dụ "<p>...</p>" hiện thành chữ). Đổi sang {!! !!} + <div> (không
                             dùng <p> bọc ngoài vì nội dung bên trong đã có thể tự chứa <p> khác, lồng
                             <p> trong <p> là HTML không hợp lệ) để hiển thị đúng định dạng đã soạn —
                             cùng class .rich-content + quy tắc ul/ol/p ở admin/content/show.blade.php.
                             Fallback: chỉ hiện khi câu hỏi KHÔNG có statement.pdf (câu tự soạn tay). --}}
                        <div class="rich-content text-[13px] leading-relaxed text-slate-600">{!! $question->body !!}</div>
                    @endif

                    @if ($question->tags->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            @foreach ($question->tags as $t)
                                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">
                                    <x-lucide name="book-open" class="h-3 w-3 shrink-0" />{{ $t->name }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    {{-- SỬA 31/8 (2, "mở rộng ZIP bài tập" — audio/ảnh...): học liệu CẦN để trả lời
                         (vd nghe audio nghe-hiểu) — hiện NGAY ở đây (khác đề bài PDF, chỉ xem qua
                         link riêng ở "Xem đề bài"), luôn hiện bất kể đã trả lời hay chưa. --}}
                    @if (! empty($assets))
                        <div class="mt-3 space-y-2.5">
                            @foreach ($assets as $asset)
                                <div class="rounded-2xl border border-sky-100 bg-slate-50 p-3">
                                    @if ($asset['kind'] === 'audio')
                                        <audio controls preload="none" class="w-full" src="{{ $asset['url'] }}"></audio>
                                    @elseif ($asset['kind'] === 'image')
                                        {{-- max-width:100%/height:auto đã có sẵn ở Tailwind preflight cho <img>, không cần class max-w-full. --}}
                                        <img src="{{ $asset['url'] }}" alt="{{ $asset['altText'] ?? '' }}" class="rounded-xl">
                                    @else
                                        <a href="{{ $asset['url'] }}" class="inline-flex items-center gap-1 text-[12px] font-bold text-blue-600 hover:text-blue-700">
                                            <x-lucide name="file-text" class="h-3.5 w-3.5 shrink-0" />{{ $asset['filename'] ?? 'Tệp đính kèm' }}
                                        </a>
                                    @endif
                                    @if (! empty($asset['altText']))
                                        <p class="mt-1 inline-flex items-center gap-1 text-[11px] text-slate-400">
                                            <x-lucide name="headphones" class="h-3 w-3 shrink-0" />{{ $asset['altText'] }}
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ── CỘT PHẢI: BÀI LÀM ───────────────────────────────────────────────── --}}
                <div class="rounded-3xl border border-sky-100 bg-white p-4 shadow-[0_2px_8px_rgba(0,90,180,.04)] sm:p-5">
                    <div class="mb-3 flex items-center gap-2">
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-blue-50 text-blue-600">
                            <x-lucide name="pen-line" class="h-3.5 w-3.5" />
                        </span>
                        <h3 class="min-w-0 truncate text-sm font-bold text-slate-800">{{ $feedback === null ? 'Bài làm của bạn' : 'Kết quả' }}</h3>
                    </div>

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
                    <form method="POST" action="{{ route('student.practiceByQuestion.answer') }}" class="space-y-2.5" data-ajax-answer>
                        @csrf
                        @if ($typeValue === 'mcq')
                            @foreach ($options as $i => $opt)
                                @if ($opt !== '' && $opt !== null)
                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-2xl border border-sky-100 p-3.5 transition-colors hover:border-blue-200 hover:bg-sky-50 has-[:checked]:border-blue-300 has-[:checked]:bg-blue-50">
                                        <input type="radio" name="selected_option" value="{{ $i }}" required>
                                        <span class="text-[13px] leading-relaxed text-slate-700">{{ $opt }}</span>
                                    </label>
                                @endif
                            @endforeach
                        @elseif ($typeValue === 'fill_blank')
                            <input type="text" name="text" required maxlength="500" placeholder="Nhập đáp án..."
                                   class="w-full rounded-2xl border border-sky-100 p-3.5 text-[13px] focus:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-100">
                        @elseif ($typeValue === 'composite')
                            {{-- SỬA 31/8 (2, "mở rộng ZIP bài tập" nhiều dạng câu) — câu nhiều
                                 phần, mỗi phần 1 dạng con khác nhau (xem $compositeParts —
                                 SANITIZED, không có đáp án đúng, xem PracticeByQuestionService::
                                 sanitizedCompositeParts()). Mỗi phần gửi lên qua
                                 name="parts[<code phần>]" — PracticeByQuestionService::
                                 gradeCompositeParts() đọc đúng cấu trúc này. --}}
                            @foreach (($compositeParts ?? []) as $part)
                                <div class="rounded-2xl border border-sky-100 p-3.5">
                                    <p class="mb-2 text-[12px] font-bold text-slate-700">Phần {{ strtoupper($part['code']) }} <span class="font-medium text-slate-400">({{ $part['points'] }} điểm)</span></p>
                                    @if ($part['responseType'] === 'single_choice')
                                        <div class="flex flex-wrap gap-2">
                                            @foreach ($part['choices'] as $choice)
                                                <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-sky-100 px-3 py-1.5 text-[12px] transition-colors hover:border-blue-200 has-[:checked]:border-blue-300 has-[:checked]:bg-blue-50">
                                                    <input type="radio" name="parts[{{ $part['code'] }}]" value="{{ $choice }}" required> {{ $choice }}
                                                </label>
                                            @endforeach
                                        </div>
                                    @elseif ($part['responseType'] === 'true_false')
                                        <div class="flex gap-2">
                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-sky-100 px-3 py-1.5 text-[12px] transition-colors hover:border-blue-200 has-[:checked]:border-blue-300 has-[:checked]:bg-blue-50">
                                                <input type="radio" name="parts[{{ $part['code'] }}]" value="true" required> Đúng
                                            </label>
                                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-sky-100 px-3 py-1.5 text-[12px] transition-colors hover:border-blue-200 has-[:checked]:border-blue-300 has-[:checked]:bg-blue-50">
                                                <input type="radio" name="parts[{{ $part['code'] }}]" value="false" required> Sai
                                            </label>
                                        </div>
                                    @elseif ($part['responseType'] === 'short_answer')
                                        <input type="text" name="parts[{{ $part['code'] }}]" maxlength="500" placeholder="Nhập đáp án..."
                                               class="admin-input">
                                    @else
                                        {{-- 'essay' hoặc dạng lạ chưa hỗ trợ — chỉ ghi nhận. --}}
                                        <textarea name="parts[{{ $part['code'] }}]" rows="4" maxlength="5000" placeholder="Viết câu trả lời của bạn..."
                                                  class="admin-input"></textarea>
                                        <p class="mt-1 text-[11px] text-slate-400">Phần tự luận chưa có chấm tự động — chỉ được ghi nhận.</p>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            {{-- SỬA 24/8 (v5) — khách yêu cầu ô viết code "như VSCode" thay vì
                                 textarea trơn: nhúng CodeMirror 5 qua CDN (script init ở
                                 @push('scripts') cuối trang) — có số dòng, tô màu cú pháp theo
                                 ngôn ngữ chọn ở dropdown, theme tối "monokai". --}}
                            <div class="overflow-hidden rounded-2xl border border-slate-700 shadow-sm">
                                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-700 bg-[#1f211c] px-3.5 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center gap-1.5 text-[11px] font-bold text-slate-300">
                                            <x-lucide name="code-2" class="h-3.5 w-3.5 shrink-0" />Ngôn ngữ
                                        </span>
                                        <select name="language" data-code-language
                                                class="rounded-lg border border-slate-600 bg-[#3a3d31] px-2 py-1 text-[11px] font-bold text-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-400">
                                            <option value="cpp" selected>C++</option>
                                            <option value="c">C</option>
                                            <option value="java">Java</option>
                                            <option value="python">Python</option>
                                            <option value="csharp">C#</option>
                                        </select>
                                    </div>
                                    <span class="text-[11px] font-medium text-slate-500">Đọc từ bàn phím · in ra màn hình</span>
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

                        <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-3 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">
                            {{ $typeValue === 'coding' ? 'Ghi nhận bài làm' : 'Kiểm tra đáp án' }}
                        </button>
                        {{-- SỬA 3/9 — chỗ hiện lỗi khi gửi AJAX thất bại (mất mạng...), thay vì
                             alert() gây gián đoạn. Ẩn mặc định, script cuối trang bật lên khi cần. --}}
                        <p data-ajax-error class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-center text-[12px] font-medium text-rose-600"></p>
                    </form>
                @else
                    {{-- Đã trả lời — hiện kết quả đúng/sai + đáp án đúng, khoá form lại. --}}
                    <div class="space-y-2.5">
                        @if ($typeValue === 'mcq')
                            @foreach ($options as $i => $opt)
                                @if ($opt !== '' && $opt !== null)
                                    @php
                                        $isCorrectOpt = in_array((int) $i, array_map('intval', $feedback['correctOptions']), true);
                                        $isYourPick = (string) $feedback['yourSelectedOption'] === (string) $i;
                                    @endphp
                                    <div @class([
                                        'flex items-center gap-2.5 rounded-2xl border p-3.5 text-[13px]',
                                        'border-emerald-300 bg-emerald-50 text-emerald-700' => $isCorrectOpt,
                                        'border-rose-300 bg-rose-50 text-rose-600' => $isYourPick && ! $isCorrectOpt,
                                        'border-sky-100 text-slate-500' => ! $isCorrectOpt && ! $isYourPick,
                                    ])>
                                        @if ($isCorrectOpt)
                                            <x-lucide name="check" class="h-4 w-4 shrink-0" />
                                        @elseif ($isYourPick)
                                            <x-lucide name="x" class="h-4 w-4 shrink-0" />
                                        @else
                                            <span class="h-4 w-4 shrink-0"></span>
                                        @endif
                                        <span class="leading-relaxed">{{ $opt }}</span>
                                    </div>
                                @endif
                            @endforeach
                        @elseif ($typeValue === 'fill_blank')
                            <div class="rounded-2xl border border-sky-100 p-3.5 text-[13px] text-slate-500">
                                Bạn trả lời: <span class="font-bold text-slate-700">{{ $feedback['yourText'] }}</span>
                            </div>
                            <div class="rounded-2xl border border-emerald-300 bg-emerald-50 p-3.5 text-[13px] text-emerald-700">
                                Đáp án đúng: <span class="font-bold">{{ implode(', ', $feedback['acceptedAnswers']) }}</span>
                            </div>
                        @elseif ($typeValue === 'composite')
                            @foreach (($feedback['compositeParts'] ?? []) as $part)
                                <div @class([
                                    'rounded-2xl border p-3.5 text-[13px]',
                                    'border-emerald-300 bg-emerald-50 text-emerald-700' => $part['gradable'] && $part['isCorrect'],
                                    'border-rose-300 bg-rose-50 text-rose-600' => $part['gradable'] && ! $part['isCorrect'],
                                    'border-sky-200 bg-sky-50 text-sky-700' => ! $part['gradable'],
                                ])>
                                    <p class="mb-1 font-bold">Phần {{ strtoupper($part['code']) }} ({{ $part['points'] }} điểm)</p>
                                    <p>Bạn trả lời: {{ is_bool($part['yourAnswer']) ? ($part['yourAnswer'] ? 'Đúng' : 'Sai') : ($part['yourAnswer'] ?: '—') }}</p>
                                    @if ($part['gradable'])
                                        <p>{{ $part['isCorrect'] ? '✓ Chính xác' : '✕ Chưa đúng — đáp án đúng: '.$part['correctAnswer'] }}</p>
                                    @else
                                        <p class="inline-flex items-center gap-1"><x-lucide name="mail" class="h-3.5 w-3.5 shrink-0" />Đã ghi nhận — phần tự luận chưa có chấm tự động.</p>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            {{-- SỬA 24/8 (v4) — câu Lập trình chỉ hiện lại bài đã nộp (code +
                                 ngôn ngữ), không có khối "đáp án đúng"; kết quả chấm nằm ở khối
                                 verdict + danh sách test bên dưới. --}}
                            <div class="overflow-hidden rounded-2xl border border-slate-700">
                                <div class="flex items-center gap-1.5 border-b border-slate-700 bg-[#1f211c] px-3.5 py-2 text-[11px] font-bold text-slate-300">
                                    <x-lucide name="code-2" class="h-3.5 w-3.5 shrink-0" />Bài đã nộp · {{ $feedback['yourLanguage'] ?: '—' }}
                                </div>
                                <pre class="max-h-72 overflow-auto whitespace-pre-wrap bg-[#272822] p-3.5 font-mono text-[12px] leading-relaxed text-slate-100">{{ $feedback['yourCode'] }}</pre>
                            </div>
                        @endif

                        @if ($feedback['gradable'])
                            <div @class([
                                'flex items-center gap-3 rounded-2xl border p-3.5',
                                'border-emerald-200 bg-emerald-50' => $feedback['isCorrect'],
                                'border-rose-200 bg-rose-50' => ! $feedback['isCorrect'],
                            ])>
                                <span @class([
                                    'grid h-9 w-9 shrink-0 place-items-center rounded-xl',
                                    'bg-emerald-100 text-emerald-700' => $feedback['isCorrect'],
                                    'bg-rose-100 text-rose-600' => ! $feedback['isCorrect'],
                                ])>
                                    <x-lucide :name="$feedback['isCorrect'] ? 'check-circle-2' : 'x'" class="h-4 w-4" />
                                </span>
                                <span @class([
                                    'text-[13px] font-bold',
                                    'text-emerald-700' => $feedback['isCorrect'],
                                    'text-rose-600' => ! $feedback['isCorrect'],
                                ])>
                                    {{-- SỬA 3/9 (khách hỏi "Chưa đúng là sao") — câu Lập trình hiện
                                         nhãn verdict CỤ THỂ (VerdictStatus::label(), vd "Sai kết
                                         quả (Wrong Answer)"/"Lỗi biên dịch (Compilation Error)"/
                                         "Quá thời gian (Time Limit Exceeded)") thay vì luôn "✕
                                         Chưa đúng" chung chung — MCQ/điền đáp án/composite giữ
                                         nguyên câu cũ (không có nhiều dạng verdict như Lập
                                         trình). --}}
                                    @if ($typeValue === 'coding' && ! $feedback['isCorrect'] && $feedback['codingVerdictLabel'])
                                        {{ $feedback['codingVerdictLabel'] }}
                                    @else
                                        {{ $feedback['isCorrect'] ? 'Chính xác!' : 'Chưa đúng — xem đáp án ở trên.' }}
                                    @endif
                                </span>
                            </div>
                            {{-- SỬA 3/9 (2, khách yêu cầu: hiện chi tiết từng test đúng/sai +
                                 cho tải test sai về) — thay khối <pre> gộp 1 lỗi đầu tiên (cũ)
                                 bằng danh sách ĐẦY ĐỦ từng test case (PracticeByQuestionService::
                                 judgeCodingAnswer() giờ trả 'codingTestCases' — mảng mỗi phần tử
                                 {index,isAccepted,statusLabel,input,expectedOutput,actualOutput,
                                 stderr,compileOutput}, xem CodeJudgingService::judge()). Test
                                 ĐÚNG chỉ hiện 1 dòng khoá cứng (không bấm mở được, không có gì
                                 để xem) — test SAI bấm vào mới xổ chi tiết (script cuối trang). --}}
                            @if ($typeValue === 'coding' && ! empty($feedback['codingTestCases']))
                                @php
                                    $tcs = $feedback['codingTestCases'];
                                    $tcPassed = collect($tcs)->where('isAccepted', true)->count();
                                    $tcFailed = collect($tcs)->reject(fn ($t) => $t['isAccepted'])->values();
                                @endphp
                                <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-sky-100">
                                    <div class="flex items-center justify-between gap-2 bg-slate-50 px-3.5 py-2">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Kết quả từng test</p>
                                        <span class="text-[11px] font-bold tabular-nums text-slate-600">Đúng {{ $tcPassed }}/{{ count($tcs) }}</span>
                                    </div>
                                    @foreach ($tcs as $tc)
                                        <div data-test-case-row>
                                            <button type="button"
                                                    @class([
                                                        'flex w-full items-center justify-between gap-2 px-3.5 py-2 text-left text-[12px] font-medium transition-colors',
                                                        'text-emerald-700' => $tc['isAccepted'],
                                                        'text-rose-600 hover:bg-rose-50' => ! $tc['isAccepted'],
                                                    ])
                                                    @if ($tc['isAccepted']) disabled @else data-test-case-toggle @endif>
                                                <span class="inline-flex items-center gap-2">
                                                    <span @class([
                                                        'grid h-5 w-5 shrink-0 place-items-center rounded-full',
                                                        'bg-emerald-100 text-emerald-700' => $tc['isAccepted'],
                                                        'bg-rose-100 text-rose-600' => ! $tc['isAccepted'],
                                                    ])>
                                                        <x-lucide :name="$tc['isAccepted'] ? 'check' : 'x'" class="h-3 w-3" />
                                                    </span>
                                                    Test {{ $tc['index'] }} — {{ $tc['statusLabel'] }}
                                                </span>
                                                @if (! $tc['isAccepted'])
                                                    <span data-test-case-arrow class="text-slate-400">▾</span>
                                                @endif
                                            </button>
                                            @if (! $tc['isAccepted'])
                                                <div class="hidden space-y-2 border-t border-slate-100 bg-slate-50 px-3.5 py-2.5 text-[11px] text-slate-600" data-test-case-detail>
                                                    <div>
                                                        <p class="mb-1 font-bold uppercase tracking-wide text-slate-400">Dữ liệu vào</p>
                                                        <pre class="overflow-x-auto whitespace-pre-wrap rounded-xl border border-sky-100 bg-white p-2 font-mono">{{ $tc['input'] !== '' ? $tc['input'] : '(rỗng)' }}</pre>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 font-bold uppercase tracking-wide text-slate-400">Kết quả mong đợi</p>
                                                        <pre class="overflow-x-auto whitespace-pre-wrap rounded-xl border border-emerald-100 bg-emerald-50/50 p-2 font-mono">{{ $tc['expectedOutput'] }}</pre>
                                                    </div>
                                                    <div>
                                                        <p class="mb-1 font-bold uppercase tracking-wide text-slate-400">Chương trình của bạn in ra</p>
                                                        <pre class="overflow-x-auto whitespace-pre-wrap rounded-xl border border-sky-100 bg-white p-2 font-mono">{{ $tc['actualOutput'] !== null && $tc['actualOutput'] !== '' ? $tc['actualOutput'] : '(không có gì)' }}</pre>
                                                    </div>
                                                    @if ($tc['compileOutput'] || $tc['stderr'])
                                                        <div>
                                                            <p class="mb-1 font-bold uppercase tracking-wide text-rose-400">Lỗi</p>
                                                            <pre class="overflow-x-auto whitespace-pre-wrap rounded-xl border border-rose-200 bg-rose-50 p-2 font-mono text-rose-700">{{ trim(($tc['compileOutput'] ?? '')."\n".($tc['stderr'] ?? '')) }}</pre>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                                @if ($tcFailed->isNotEmpty())
                                    <button type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-blue-600 transition-colors hover:bg-blue-100 hover:text-blue-700"
                                            data-download-failed-tests
                                            data-question-id="{{ $question->id }}"
                                            data-tests="{{ $tcFailed->toJson() }}">
                                        <x-lucide name="download" class="h-3.5 w-3.5 shrink-0" />Tải test sai (.txt)
                                    </button>
                                @endif
                            @endif
                        @else
                            {{-- Máy chấm không phản hồi (Judge0 chưa bật / đường hầm SSH đứt / token
                                 lệch). Chữ hiện cho HỌC SINH nên nói theo cách học sinh hiểu —
                                 nguyên nhân kỹ thuật thật đã được ghi đầy đủ vào
                                 storage/logs/laravel.log (PracticeByQuestionService bắt
                                 RuntimeException rồi Log::warning kèm message của Judge0Client),
                                 đó mới là chỗ người quản trị đọc để sửa. --}}
                            <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-3.5">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700">
                                    <x-lucide name="alert-triangle" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[13px] font-bold text-amber-800">Đã ghi nhận bài làm</p>
                                    <p class="mt-0.5 text-[12px] leading-relaxed text-amber-700">Máy chấm đang không phản hồi nên chưa có kết quả đúng/sai. Bạn thử nộp lại sau ít phút nhé.</p>
                                </div>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('student.practiceByQuestion.next') }}">
                            @csrf
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl bg-blue-600 px-4 py-3 text-[13px] font-bold text-white shadow-sm shadow-blue-200 transition-colors hover:bg-blue-700">
                                {{ $progress['current'] < $progress['total'] ? 'Câu tiếp theo' : 'Xem kết quả' }}
                                <x-lucide name="arrow-right" class="h-3.5 w-3.5 shrink-0" />
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
        /* SỬA 15/9 (UI) — chiều cao ô code bám theo chiều cao khung PDF đề bài ở cột trái
           (h-[420px] / sm:h-[520px] / xl:h-[600px]) trừ đi phần thanh chọn ngôn ngữ + nút bấm,
           để 2 cột kết thúc gần ngang nhau thay vì lệch nhau một đoạn dài. Viết bằng CSS
           thường (không phải class Tailwind) vì .CodeMirror là class do thư viện CDN tự sinh
           ra lúc chạy, Tailwind không quét thấy trong mã nguồn nên không build ra được. */
        .CodeMirror { height: 360px; font-size: 13.5px; }
        @media (min-width: 640px) { .CodeMirror { height: 460px; } }
        @media (min-width: 1280px) { .CodeMirror { height: 540px; } }
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

        // SỬA 3/9 (2, khách yêu cầu: bấm mở rộng xem chi tiết test sai + tải file test sai) —
        // delegation trên toàn trang (giống submit handler trên) vì các nút này nằm TRONG
        // #practice-container, có thể bị thay mới sau mỗi lần AJAX — gắn 1 lần ở document là đủ,
        // luôn bắt được nút mới mà không cần gọi lại hàm init nào khác.
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
