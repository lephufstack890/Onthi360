{{--
  SỬA 18/9 (khách: "khi ghi nhận làm bài xong hiển thị kết quả test bên dưới luôn, khỏi cần
  phải qua trang này") — KẾT QUẢ CHẤM của bài Lập trình, hiện NGAY dưới khu soạn mã thay vì
  thay cả màn hình bằng một trang kết quả riêng. Nhờ vậy học sinh đọc test sai và sửa code ngay
  tại chỗ, không phải nhớ rồi bấm quay lại.

  Cần $feedback (xem Student\PracticeByQuestionService::answer()). Các thẻ data-test-case-* ăn
  theo script đóng/mở chi tiết + tải test sai đã có sẵn ở cuối exercise-play.blade.php — cố ý
  giữ nguyên tên để không phải thêm dòng JS nào.
--}}
@php
    $tcs = $feedback['codingTestCases'] ?? [];
    $tcPassed = collect($tcs)->where('isAccepted', true)->count();
    $tcTotal = count($tcs);
    $tcFailed = collect($tcs)->reject(fn ($t) => $t['isAccepted'])->values();
    $passPercent = $tcTotal > 0 ? (int) round($tcPassed / $tcTotal * 100) : 0;
@endphp

<section class="flex min-w-0 flex-col overflow-hidden rounded-xl bg-white shadow-[0_2px_10px_rgba(28,91,121,0.05)] ring-1 ring-[#DDEAF0]">

    @if (! empty($feedback['codingError']))
        {{-- Không chấm được (máy chấm chưa kết nối / bài chưa có test / chưa viết mã). --}}
        <div class="flex items-start gap-3 bg-amber-50 p-4">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700"><x-lucide name="alert-triangle" class="h-4 w-4" /></span>
            <div class="min-w-0">
                <p class="text-[13px] font-bold text-amber-800">Chưa chấm được bài</p>
                <p class="mt-0.5 text-[12px] leading-relaxed text-amber-800">{{ $feedback['codingError'] }}</p>
            </div>
        </div>
    @else
        {{-- ── Băng kết quả chung ── --}}
        <div @class([
            'flex flex-wrap items-center gap-3 p-4',
            'bg-[#EFF9F5]' => $feedback['isCorrect'],
            'bg-[#EEF4FC]' => ! $feedback['isCorrect'],
        ])>
            <span @class([
                'grid h-10 w-10 shrink-0 place-items-center rounded-full',
                'bg-[#D4EDE2] text-[#2F8A6B]' => $feedback['isCorrect'],
                'bg-[#DCE8F8] text-[#2C6BB0]' => ! $feedback['isCorrect'],
            ])><x-lucide :name="$feedback['isCorrect'] ? 'check' : 'x'" class="h-5 w-5" /></span>

            <div class="min-w-0 flex-1">
                <p @class([
                    'text-[15px] font-extrabold',
                    'text-[#2F8A6B]' => $feedback['isCorrect'],
                    'text-[#2C6BB0]' => ! $feedback['isCorrect'],
                ])>
                    {{ $feedback['isCorrect'] ? 'Chính xác!' : ($feedback['codingVerdictLabel'] ?: 'Chưa đúng') }}
                </p>
                @if ($tcTotal > 0)
                    <div class="mt-1.5 flex items-center gap-2">
                        <div class="h-1.5 w-full max-w-[220px] overflow-hidden rounded-full bg-white/70">
                            <div @class([
                                'h-full rounded-full transition-all',
                                'bg-[#2F8A6B]' => $feedback['isCorrect'],
                                'bg-[#4C87CE]' => ! $feedback['isCorrect'],
                            ]) style="width: {{ $passPercent }}%"></div>
                        </div>
                        <span class="shrink-0 text-[11px] font-bold text-[#607A90]">Đúng {{ $tcPassed }}/{{ $tcTotal }}</span>
                    </div>
                @endif
            </div>
        </div>

        @if ($tcTotal > 0)
            {{-- ── Dải ô vuông: liếc một cái là thấy hỏng ở quãng nào ── --}}
            <div class="flex flex-wrap items-center gap-1 border-t border-[#E7EFF3] px-4 py-2.5">
                @foreach ($tcs as $tc)
                    <span title="Test {{ $tc['index'] }} — {{ $tc['statusLabel'] }}"
                          @class([
                              'grid h-5 w-5 place-items-center rounded text-[9px] font-black',
                              'bg-[#D4EDE2] text-[#2F8A6B]' => $tc['isAccepted'],
                              'bg-[#DCE8F8] text-[#2C6BB0]' => ! $tc['isAccepted'],
                          ])>{{ $tc['index'] }}</span>
                @endforeach
            </div>

            {{-- ── Danh sách chi tiết: test ĐÚNG khoá cứng, test SAI bấm mới xổ ra ── --}}
            <div class="flex items-center justify-between gap-2 border-t border-[#E7EFF3] bg-[#F4F8FB] px-4 py-2">
                <span class="text-[10px] font-black uppercase tracking-[.12em] text-[#365B7A]">Kết quả từng test</span>
                @if ($tcFailed->isNotEmpty())
                    <button type="button"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-[11px] font-bold text-[#126F91] transition hover:bg-[#E3F0F5]"
                            data-download-failed-tests
                            data-question-id="{{ $question->id }}"
                            data-tests="{{ $tcFailed->toJson() }}">
                        <x-lucide name="download" class="h-3.5 w-3.5" />Tải {{ $tcFailed->count() }} test sai
                    </button>
                @endif
            </div>

            {{--
                SỬA 19/9 (8) (khách: "đâu thấy được test 19 20 đâu") — BỎ max-h-[320px] +
                overflow-y-auto ở đây.

                Lỗi cũ là CUỘN LỒNG NHAU: danh sách test tự cuộn trong một ô cao 320px, mà bản
                thân ô 320px đó lại nằm dưới khu soạn mã cao 420px, nên nửa dưới của chính cái ô
                đã nằm ngoài vùng nhìn thấy. Kéo chuột trong danh sách thì các dòng có chạy,
                nhưng dòng cuối cùng nhìn thấy được luôn là dòng chạm mép cắt (Test 17/18) —
                Test 19, 20 không bao giờ hiện ra, dù thanh cuộn bên trong đã ở đáy.

                Giờ danh sách đổ thẳng, chỉ còn MỘT thanh cuộn duy nhất của khung ngoài (xem
                exercise-play.blade.php, chỗ bỏ lg:overflow-hidden), cuộn tới đâu cũng tới được,
                kể cả nút "Hoàn tất bài tập" ở cuối.
            --}}
            <div class="divide-y divide-[#EEF3F6]">
                @foreach ($tcs as $tc)
                    <div data-test-case-row>
                        <button type="button"
                                @class([
                                    'flex w-full items-center justify-between gap-2 px-4 py-2 text-left text-[12px] transition-colors',
                                    'text-[#2F8A6B]' => $tc['isAccepted'],
                                    'text-[#2C6BB0] hover:bg-[#F4F8FB]' => ! $tc['isAccepted'],
                                ])
                                @if ($tc['isAccepted']) disabled @else data-test-case-toggle @endif>
                            <span class="inline-flex min-w-0 items-center gap-2">
                                <span @class([
                                    'grid h-5 w-5 shrink-0 place-items-center rounded-full',
                                    'bg-[#D4EDE2] text-[#2F8A6B]' => $tc['isAccepted'],
                                    'bg-[#DCE8F8] text-[#2C6BB0]' => ! $tc['isAccepted'],
                                ])><x-lucide :name="$tc['isAccepted'] ? 'check' : 'x'" class="h-3 w-3" /></span>
                                <span class="truncate font-semibold">Test {{ $tc['index'] }}</span>
                                <span class="truncate text-[#607A90]">— {{ $tc['statusLabel'] }}</span>
                            </span>
                            <span class="inline-flex shrink-0 items-center gap-2 text-[10px] font-semibold text-[#8AA0B0]">
                                @if ($tc['time'] !== null){{ $tc['time'] }}s @endif
                                @if ($tc['memory'] !== null)· {{ round($tc['memory'] / 1024) }}MB @endif
                                @if (! $tc['isAccepted'])<span data-test-case-arrow class="text-[#8AA0B0]">▾</span>@endif
                            </span>
                        </button>

                        @if (! $tc['isAccepted'])
                            <div class="hidden space-y-2 border-t border-[#EEF3F6] bg-[#F9FBFC] px-4 py-3 text-[11px]" data-test-case-detail>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <div class="min-w-0">
                                        <p class="mb-1 text-[10px] font-black uppercase tracking-wide text-[#8AA0B0]">Dữ liệu vào</p>
                                        <pre class="max-h-28 overflow-auto whitespace-pre-wrap rounded-lg bg-white p-2 font-mono ring-1 ring-[#E7EFF3]">{{ $tc['input'] !== '' ? $tc['input'] : '(rỗng)' }}</pre>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="mb-1 text-[10px] font-black uppercase tracking-wide text-[#8AA0B0]">Mong đợi</p>
                                        <pre class="max-h-28 overflow-auto whitespace-pre-wrap rounded-lg bg-white p-2 font-mono ring-1 ring-[#D4EDE2]">{{ $tc['expectedOutput'] }}</pre>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="mb-1 text-[10px] font-black uppercase tracking-wide text-[#8AA0B0]">Bạn in ra</p>
                                        <pre class="max-h-28 overflow-auto whitespace-pre-wrap rounded-lg bg-white p-2 font-mono ring-1 ring-[#DCE8F8]">{{ $tc['actualOutput'] !== null && $tc['actualOutput'] !== '' ? $tc['actualOutput'] : '(không có gì)' }}</pre>
                                    </div>
                                </div>
                                @if ($tc['compileOutput'] || $tc['stderr'])
                                    <div>
                                        <p class="mb-1 text-[10px] font-black uppercase tracking-wide text-[#B42318]">Lỗi</p>
                                        <pre class="max-h-32 overflow-auto whitespace-pre-wrap rounded-lg bg-[#FEF3F2] p-2 font-mono text-[#B42318] ring-1 ring-[#FECDCA]">{{ trim(($tc['compileOutput'] ?? '')."\n".($tc['stderr'] ?? '')) }}</pre>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endif

    {{-- Bấm xong bài — nút này nằm ngoài form chấm (form lồng nhau là HTML không hợp lệ, trình
         duyệt sẽ bỏ form trong), nên đặt ở cuối khối kết quả. --}}
    <div class="border-t border-[#E7EFF3] bg-white p-3">
        <button type="submit" form="practice-finish-form"
                class="flex w-full items-center justify-center gap-1.5 rounded-lg bg-[#126F91] px-4 py-2.5 text-[12px] font-bold text-white shadow-sm transition hover:bg-[#0D5B77]">
            Hoàn tất bài tập
            <x-lucide name="arrow-right" class="h-3.5 w-3.5" />
        </button>
    </div>
</section>
