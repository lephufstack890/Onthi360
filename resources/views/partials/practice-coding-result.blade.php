{{--
  KẾT QUẢ CHẤM của bài Lập trình, hiện ngay ở cột phải khu soạn mã.

  SỬA 30/9 (4) (khách: "chỗ kết quả khi làm bài lập trình xong thì hiển thị theo UI này,
  check source mới cho kỹ") — dựng lại theo ĐÚNG <JudgingResultPanel> của bản mẫu mới
  (education-main/src/components/AssessmentModal.jsx, dòng 246–258):

      <section class="overflow-y-auto bg-white px-3 py-2 text-[11px] h-full rounded-xl border">
        <p><strong>Kết quả chấm: {trạng thái}.</strong> {đúng}/{tổng} test đúng.</p>
        <ul class="mt-2 divide-y border-t">
          <li class="flex items-center gap-2 py-1">
            <span class="min-w-0 flex-1 truncate">{tên test}</span>
            <strong>Đúng|Sai</strong>
            {sai thì có} <button>Tải test</button>
          </li>
        </ul>
      </section>

  Bản cũ có băng kết quả to kèm thanh phần trăm, dải 20 ô vuông và nút "Tải N test sai" —
  BỎ HẾT, bản mẫu mới không có.

  KHÁC bản mẫu ở ba chỗ, đều là chỗ bản mẫu không thể có vì nó chỉ là bản dựng hình:
    1. Bản mẫu in "Kết quả minh họa; chưa kết nối máy chấm bài." — ở đây máy chấm Judge0
       chạy thật nên in câu đó là nói sai, đã bỏ.
    2. Giữ khối "lỗi biên dịch" (có số dòng) và mách nước freopen — đây là thứ học sinh
       cần nhất khi bí, bản mẫu không có vì nó không chấm thật.
    3. Bấm vào tên một test SAI vẫn xổ ra dữ liệu vào / mong đợi / bạn in ra như cũ.
       Lúc chưa bấm thì dòng đó trông y hệt bản mẫu.

  Cần $feedback (xem Student\PracticeByQuestionService::answer()). Các thẻ data-test-case-*
  và data-download-failed-tests ăn theo script đã có sẵn ở cuối exercise-play.blade.php.
--}}
@php
    $tcs = $feedback['codingTestCases'] ?? [];
    $tcPassed = collect($tcs)->where('isAccepted', true)->count();
    $tcTotal = count($tcs);

    /*
     * SỬA 24/9 (khách: "sao giờ chấm sai hết thế này") — BẮT ĐÚNG MỘT CÁI BẪY IM LẶNG.
     *
     * Hiện trường hôm đó: bài dùng freopen("TONG.INP"/"TONG.OUT") nhưng đề lại khai vào/ra
     * CHUẨN. freopen vào file không tồn tại thì THẤT BẠI VÀ ĐÓNG LUÔN stdin (cin chết), còn
     * freopen ra file thì THÀNH CÔNG (nó tự tạo file) — thế là mọi thứ cout in ra chui hết vào
     * file, màn hình trống trơn. Máy chấm chỉ đọc màn hình nên sai sạch 20 test, mỗi test đều
     * ghi "(không có gì)". Nhìn bảng kết quả thì không tài nào đoán ra, nên nói thẳng.
     */
    $allFailed = $tcTotal > 0 && $tcPassed === 0;
    $allSilent = $allFailed && collect($tcs)->every(fn ($t) => trim((string) ($t['actualOutput'] ?? '')) === '');

    $headline = $feedback['isCorrect'] ? 'Tất cả test đúng' : 'Có test sai';
@endphp

<section class="h-full overflow-y-auto rounded-xl border border-[#DDEAF0] bg-white px-3 py-2 text-[11px]">

    @if (! empty($feedback['codingError']))
        {{-- Không chấm được (máy chấm chưa kết nối / bài chưa có test / chưa viết mã). --}}
        <p role="status" aria-atomic="true" class="text-[#45657D]">
            <strong class="text-amber-700">Kết quả chấm: Chưa chấm được bài.</strong>
        </p>
        <p class="mt-1 leading-5 text-[#607A90]">{{ $feedback['codingError'] }}</p>

    @elseif (! empty($feedback['codingCompileError']))
        {{--
          SỬA 23/9 (khách: "chương trình lỗi thì ngừng chấm luôn... lỗi code là báo lỗi ở dòng
          bao nhiêu") — mã không biên dịch được thì máy chấm dừng sau test đầu, không có bảng
          test nào để vẽ. Chỉ nói thẳng sai ở dòng nào và sai gì.
        --}}
        <p role="status" aria-atomic="true" class="text-[#45657D]">
            <strong class="text-rose-700">Kết quả chấm: Mã chưa biên dịch được.</strong>
            @if (! empty($feedback['codingErrorLine']))Lỗi ở dòng {{ $feedback['codingErrorLine'] }}.@endif
        </p>
        @if (! empty($feedback['codingErrorMessage']))
            <p class="mt-1 break-words font-mono leading-5 text-rose-700">{{ $feedback['codingErrorMessage'] }}</p>
        @endif
        <p class="mt-1 text-[#607A90]">Chưa chấm test nào — sửa lỗi rồi bấm "Nộp bài" ở thanh dưới để chấm lại.</p>
        <details class="mt-2">
            <summary class="cursor-pointer font-semibold text-[#126F91]">Xem toàn bộ thông báo của trình biên dịch</summary>
            <pre class="mt-1.5 max-h-48 overflow-auto whitespace-pre-wrap rounded-lg bg-[#FEF3F2] p-2 font-mono text-[10px] leading-5 text-rose-700">{{ $feedback['codingCompileError'] }}</pre>
        </details>

    @else
        {{-- ── Dòng trạng thái: đúng cấu trúc câu của bản mẫu ── --}}
        <p role="status" aria-atomic="true" class="text-[#45657D]">
            <strong class="{{ $feedback['isCorrect'] ? 'text-emerald-700' : 'text-rose-700' }}">Kết quả chấm: {{ $headline }}.</strong>
            @if ($tcTotal > 0){{ $tcPassed }}/{{ $tcTotal }} test đúng.@endif
        </p>

        @if ($allSilent)
            <p class="mt-2 rounded-lg bg-amber-50 px-2 py-1.5 leading-5 text-amber-900">
                <span class="font-bold">Chương trình chạy xong nhưng không in ra gì — ở tất cả các test.</span>
                Hay gặp nhất là do <span class="font-mono font-bold">freopen</span>: bài này nhận dữ liệu qua
                <span class="font-bold">màn hình (nhập/xuất chuẩn)</span>, mà mã của bạn lại đang đọc/ghi ra tệp.
                Bỏ hai dòng <span class="font-mono">freopen</span> đi, dùng thẳng
                <span class="font-mono">cin</span> / <span class="font-mono">cout</span> là chạy được.
            </p>
        @endif

        @if ($tcTotal > 0)
            <ul class="mt-2 divide-y divide-[#E7EFF3] border-t border-[#E7EFF3]">
                @foreach ($tcs as $tc)
                    @php
                        $tcNo = 'Test '.str_pad((string) $tc['index'], 2, '0', STR_PAD_LEFT);
                        // Test sai: ghi luôn lý do (Wrong Answer / quá giờ / lỗi chạy…) — đó là
                        // thông tin duy nhất giúp học sinh biết sửa gì. Test đúng: ghi thời gian
                        // và bộ nhớ, vì với bài nặng đó mới là con số đáng nhìn.
                        $tcNote = $tc['isAccepted']
                            ? trim(implode(' · ', array_filter([
                                $tc['time'] !== null ? $tc['time'].'s' : null,
                                $tc['memory'] !== null ? round($tc['memory'] / 1024).'MB' : null,
                            ])))
                            : $tc['statusLabel'];
                    @endphp
                    <li data-test-case-row>
                        <div class="flex items-center gap-2 py-1 text-[#607A90]">
                            @if ($tc['isAccepted'])
                                <span class="min-w-0 flex-1 truncate">{{ $tcNo }}@if ($tcNote) · {{ $tcNote }}@endif</span>
                            @else
                                {{-- Trông y hệt một dòng chữ thường của bản mẫu; bấm vào mới xổ chi tiết. --}}
                                <button type="button" data-test-case-toggle
                                        title="Bấm để xem dữ liệu vào, kết quả mong đợi và kết quả chương trình in ra"
                                        class="min-w-0 flex-1 truncate text-left hover:text-[#126F91]">{{ $tcNo }}@if ($tcNote) · {{ $tcNote }}@endif<span data-test-case-arrow class="ml-1 text-[#8AA0B0]">▾</span></button>
                            @endif

                            <strong class="{{ $tc['isAccepted'] ? 'text-emerald-700' : 'text-rose-700' }}">{{ $tc['isAccepted'] ? 'Đúng' : 'Sai' }}</strong>

                            @unless ($tc['isAccepted'])
                                {{-- SỬA 1/10 (khách: "bỏ Tải test đi, thêm Tải input và Tải output;
                                     bấm Tải input thì tải .in, bấm Tải output thì tải .out") — tách
                                     thành HAI nút, mỗi nút đúng một tệp. Trước đây một nút bắn ra
                                     cả hai tệp một lúc, trình duyệt hỏi "cho phép tải nhiều tệp?"
                                     và học sinh không chủ động được muốn lấy tệp nào. --}}
                                @foreach ([['in', 'Tải input'], ['out', 'Tải output']] as [$part, $partLabel])
                                    <button type="button"
                                            data-download-failed-tests
                                            data-part="{{ $part }}"
                                            data-question-id="{{ $question->id }}"
                                            data-tests="{{ json_encode([$tc], JSON_UNESCAPED_UNICODE) }}"
                                            title="Tải tệp {{ $part === 'in' ? 'dữ liệu vào' : 'kết quả mong đợi' }} của test {{ $tc['index'] }} (.{{ $part }})"
                                            class="inline-flex min-h-7 items-center px-1.5 font-semibold text-[#126F91] underline underline-offset-2 hover:text-[#0F607E]">{{ $partLabel }}</button>
                                @endforeach
                            @endunless
                        </div>

                        @unless ($tc['isAccepted'])
                            <div class="hidden space-y-2 bg-[#F9FBFC] px-1 py-2" data-test-case-detail>
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
                                        <p class="mb-1 text-[10px] font-black uppercase tracking-wide text-rose-700">Lỗi</p>
                                        <pre class="max-h-32 overflow-auto whitespace-pre-wrap rounded-lg bg-[#FEF3F2] p-2 font-mono text-rose-700 ring-1 ring-[#FECDCA]">{{ trim(($tc['compileOutput'] ?? '')."\n".($tc['stderr'] ?? '')) }}</pre>
                                    </div>
                                @endif
                            </div>
                        @endunless
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
