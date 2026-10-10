{{-- SỬA 10/10 (khách: "câu đọc từ tệp thì hiện tên INP và OUT tương ứng ở thanh dưới, không phải đọc từ tệp thì hiện như cũ") —
     dòng "Input / Output" ở thanh dưới cùng của màn làm bài. Nhận $fileIo = CodeJudgingService::fileIoNames(...).
       · đọc/ghi qua tệp : Input: TEN.INP · Output: TEN.OUT   (chỉ khai một phía thì phía kia vẫn là chuẩn)
       · bàn phím/màn hình : Input chuẩn (stdin) · Output chuẩn (stdout)
     Bản chạy bằng Alpine (màn làm ĐỀ, mỗi câu một tên tệp) nằm thẳng trong take.blade.php. --}}
@php $fio = \App\Services\CodeJudgingService::fileIoNames($fileIo ?? null); @endphp
<p class="oi-io-line" data-io-line>
    <span>Input @if ($fio && $fio['input'] !== null)<code title="Đọc dữ liệu từ tệp này">{{ $fio['input'] }}</code>@else chuẩn <code>(stdin)</code>@endif</span>
    <span>Output @if ($fio && $fio['output'] !== null)<code title="Ghi kết quả ra tệp này">{{ $fio['output'] }}</code>@else chuẩn <code>(stdout)</code>@endif</span>
</p>
@once
    <style>
        .oi-io-line { display: flex; flex-wrap: wrap; align-items: center; column-gap: 12px; row-gap: 2px; margin: 0; font-size: 10px; line-height: 1.4; color: #607A90; }
        .oi-io-line code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 10px; font-weight: 700; color: #365B7A; }
    </style>
@endonce
