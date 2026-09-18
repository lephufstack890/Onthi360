{{--
  SỬA 18/9 (2) (khách: "kiểm tra đáp án xong không cần hiển thị ra màn này mà hiển thị kết quả
  bên chỗ modal luôn") — KẾT QUẢ của câu KHÔNG phải Lập trình (trắc nghiệm / điền đáp án /
  nhiều phần), hiện NGAY TRONG panel "Trả lời câu hỏi".

  Trước đây trả lời xong là cả khung bị thay bằng một thẻ trắng canh giữa — người dùng thấy như
  bị nhảy sang màn khác. Giờ panel giữ nguyên chỗ, chỉ chuyển sang trạng thái ĐÃ CHẤM: đáp án
  đúng/sai tô màu tại chỗ, băng kết quả và nút Hoàn tất nằm ngay dưới. Cùng bảng màu và cùng
  cách bố cục với khối kết quả bài Lập trình (partials.practice-coding-result) để hai loại câu
  nhìn ra là một hệ thống.

  Cần $question và $feedback (xem Student\PracticeByQuestionService::answer()).
--}}
<div class="space-y-2">

    {{-- ══════ Bài làm đã chấm ══════ --}}
    @if ($question->type->value === 'mcq')
        @foreach ($options as $i => $opt)
            @if ($opt !== '' && $opt !== null)
                @php
                    $isCorrectOpt = in_array((int) $i, array_map('intval', $feedback['correctOptions']), true);
                    $isYourPick = (string) $feedback['yourSelectedOption'] === (string) $i;
                @endphp
                <div @class([
                    'flex items-center gap-3 rounded-lg px-3 py-2.5 text-xs font-semibold ring-1 ring-inset',
                    'bg-[#EFF9F5] text-[#2F8A6B] ring-[#B9E3D1]' => $isCorrectOpt,
                    'bg-[#EEF4FC] text-[#2C6BB0] ring-[#C3D8F2]' => $isYourPick && ! $isCorrectOpt,
                    'bg-white text-[#7A92A3] ring-[#E7EFF3]' => ! $isCorrectOpt && ! $isYourPick,
                ])>
                    <span class="grid h-5 w-5 shrink-0 place-items-center">
                        @if ($isCorrectOpt)
                            <x-lucide name="check" class="h-4 w-4" />
                        @elseif ($isYourPick)
                            <x-lucide name="x" class="h-4 w-4" />
                        @endif
                    </span>
                    <span class="min-w-0">{{ chr(65 + (int) $i) }}. {{ $opt }}</span>
                    @if ($isYourPick)
                        <span class="ml-auto shrink-0 rounded-md bg-white/70 px-2 py-0.5 text-[10px] font-bold">Bạn chọn</span>
                    @endif
                </div>
            @endif
        @endforeach

    @elseif ($question->type->value === 'fill_blank')
        <div class="rounded-lg bg-white px-3 py-2.5 text-xs ring-1 ring-inset ring-[#E7EFF3]">
            <span class="text-[#7A92A3]">Bạn trả lời:</span>
            <span class="font-bold text-[#123B68]">{{ $feedback['yourText'] !== null && $feedback['yourText'] !== '' ? $feedback['yourText'] : '(để trống)' }}</span>
        </div>
        <div class="rounded-lg bg-[#EFF9F5] px-3 py-2.5 text-xs text-[#2F8A6B] ring-1 ring-inset ring-[#B9E3D1]">
            <span class="opacity-80">Đáp án đúng:</span>
            <span class="font-bold">{{ implode(', ', $feedback['acceptedAnswers']) ?: '—' }}</span>
        </div>

    @elseif ($question->type->value === 'composite')
        @foreach (($feedback['compositeParts'] ?? []) as $part)
            <div @class([
                'rounded-lg px-3 py-2.5 text-xs ring-1 ring-inset',
                'bg-[#EFF9F5] text-[#2F8A6B] ring-[#B9E3D1]' => $part['gradable'] && $part['isCorrect'],
                'bg-[#EEF4FC] text-[#2C6BB0] ring-[#C3D8F2]' => $part['gradable'] && ! $part['isCorrect'],
                'bg-[#EAF5F8] text-[#126F91] ring-[#CFE6EE]' => ! $part['gradable'],
            ])>
                <p class="mb-1 font-bold">Phần {{ strtoupper($part['code']) }} <span class="font-normal opacity-75">({{ $part['points'] }} điểm)</span></p>
                <p class="opacity-90">Bạn trả lời: {{ is_bool($part['yourAnswer']) ? ($part['yourAnswer'] ? 'Đúng' : 'Sai') : ($part['yourAnswer'] ?: '—') }}</p>
                @if ($part['gradable'])
                    <p class="mt-0.5 font-semibold">
                        {{ $part['isCorrect'] ? '✓ Chính xác' : '✕ Chưa đúng — đáp án đúng: '.$part['correctAnswer'] }}
                    </p>
                @else
                    {{-- Phần tự luận: ghi nhận, không tự chấm — nói rõ để học sinh khỏi chờ điểm. --}}
                    <p class="mt-0.5 font-semibold">Đã ghi nhận — phần tự luận chưa có chấm tự động.</p>
                @endif
            </div>
        @endforeach
    @endif

    {{-- ══════ Băng kết quả ══════ --}}
    @if ($feedback['gradable'])
        <div @class([
            'flex items-center gap-3 rounded-xl p-3.5',
            'bg-[#EFF9F5]' => $feedback['isCorrect'],
            'bg-[#EEF4FC]' => ! $feedback['isCorrect'],
        ])>
            <span @class([
                'grid h-9 w-9 shrink-0 place-items-center rounded-full',
                'bg-[#D4EDE2] text-[#2F8A6B]' => $feedback['isCorrect'],
                'bg-[#DCE8F8] text-[#2C6BB0]' => ! $feedback['isCorrect'],
            ])><x-lucide :name="$feedback['isCorrect'] ? 'check' : 'x'" class="h-5 w-5" /></span>
            <p @class([
                'text-[14px] font-extrabold',
                'text-[#2F8A6B]' => $feedback['isCorrect'],
                'text-[#2C6BB0]' => ! $feedback['isCorrect'],
            ])>{{ $feedback['isCorrect'] ? 'Chính xác!' : 'Chưa đúng — xem đáp án ở trên.' }}</p>
        </div>
    @else
        <div class="flex items-center gap-3 rounded-xl bg-[#EAF5F8] p-3.5">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#CFE6EE] text-[#126F91]"><x-lucide name="mail" class="h-4 w-4" /></span>
            <p class="text-[13px] font-bold text-[#126F91]">Đã ghi nhận bài làm — chưa có chấm tự động cho phần này.</p>
        </div>
    @endif

    {{-- Nút này trỏ tới form đặt NGOÀI form chấm (HTML không cho lồng form) — xem
         exercise-play.blade.php, khối #practice-finish-form. --}}
    <button type="submit" form="practice-finish-form"
            class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-[#126F91] px-4 py-3 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77]">
        Hoàn tất bài tập
        <x-lucide name="arrow-right" class="h-3.5 w-3.5" />
    </button>
</div>
