{{-- SỬA 10/10 (khách: "câu đọc từ tệp thì hiện tên INP và OUT tương ứng ở thanh dưới, không phải đọc từ tệp thì hiện như cũ") —
     dòng "Input / Output" ở thanh dưới cùng của màn làm bài. Nhận $fileIo = CodeJudgingService::fileIoNames(...).
       · đọc/ghi qua tệp : Input: TEN.INP · Output: TEN.OUT   (chỉ khai một phía thì phía kia vẫn là chuẩn)
       · bàn phím/màn hình : Input chuẩn (stdin) · Output chuẩn (stdout)
     Bản chạy bằng Alpine (màn làm ĐỀ, mỗi câu một tên tệp) nằm thẳng trong take.blade.php. --}}
@php $fio = \App\Services\CodeJudgingService::fileIoNames($fileIo ?? null); @endphp
<p class="oi-io-line" data-io-line>
    {{-- SỬA 16/10 — đúng ProgrammingIONote.jsx: nhãn đậm ("Input chuẩn" / "Input:"), phần trong ngoặc / tên tệp chữ mono thường. --}}
    <span>@if ($fio && $fio['input'] !== null)<strong>Input:</strong> <code title="Đọc dữ liệu từ tệp này">{{ $fio['input'] }}</code>@else<strong>Input chuẩn</strong> <code>(stdin)</code>@endif</span>
    <span>@if ($fio && $fio['output'] !== null)<strong>Output:</strong> <code title="Ghi kết quả ra tệp này">{{ $fio['output'] }}</code>@else<strong>Output chuẩn</strong> <code>(stdout)</code>@endif</span>
</p>
@once
    <style>
        .oi-io-line { display: flex; flex-wrap: wrap; align-items: center; column-gap: 16px; row-gap: 2px; margin: 0; font-size: 12px; line-height: 1.45; color: #45657D; }
        .oi-io-line strong { font-weight: 600; }
        .oi-io-line code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; font-weight: 400; color: inherit; }
        /* Nền tối: màu chữ viết thẳng nên luật lật màu chung không với tới — tự khai để chữ SÁNG, dễ đọc. */
        html.theme-dark .assessment-modal .oi-io-line { color: #C9D8E1; }
        html.theme-dark .assessment-modal .oi-io-line code { color: inherit; }
    </style>
@endonce
