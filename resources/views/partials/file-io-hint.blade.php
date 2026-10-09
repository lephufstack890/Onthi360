{{--
    SỬA 9/10 (khách: đề kiểu HELLOWORLD bắt đọc HELLOWORLD.INP / ghi HELLOWORLD.OUT, thiếu freopen thì chấm sai) —
    dòng nhắc phía trên ô soạn mã cho đề khai tên tệp vào/ra. Máy chấm chỉ nhận dữ liệu đọc/ghi qua đúng các
    tệp này (CodeJudgingService::withFileIo()), nên phải nói rõ cho học sinh trước khi nộp.
    Nhận $fileIo = ['input' => ..., 'output' => ...] (từ CodeJudgingService::fileIoNames()); null thì không in gì.
--}}
@php
    $fio = \App\Services\CodeJudgingService::fileIoNames($fileIo ?? null);
@endphp
@if ($fio)
    <p class="shrink-0 px-4 pb-2 pt-2 text-[11px] leading-5" style="color:#9A3412;background:#FFF7ED;border-bottom:1px solid #FED7AA;" data-file-io-hint>
        <span class="font-bold">Bài này đọc/ghi qua tệp:</span>
        @if ($fio['input'] !== null)
            đọc dữ liệu từ <code class="font-mono font-bold">{{ $fio['input'] }}</code>@if ($fio['output'] !== null),@endif
        @endif
        @if ($fio['output'] !== null)
            ghi kết quả ra <code class="font-mono font-bold">{{ $fio['output'] }}</code>
        @endif
        (C++: <code class="font-mono">freopen</code> hoặc <code class="font-mono">ifstream/ofstream</code>; Python: <code class="font-mono">open</code>). Đọc bằng bàn phím hoặc in ra màn hình sẽ bị chấm sai.
    </p>
@endif
