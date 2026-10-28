{{--
    SỬA 28/10 (khách: "thêm cột Input/Output để biết bài nào đang đọc từ tệp, bài nào chưa để còn chỉnh — cả giáo viên + admin")
    — ô cột Input/Output dùng chung cho: admin Kho bài tập (Bài chung / Bài của đề), tab Bài của tài liệu, Kho câu hỏi giáo viên.
    Biến: $io = Question::ioInfo() — null (không phải câu Lập trình) | ['file' => bool, 'input' => ?string, 'output' => ?string].
--}}
@if ($io === null)
    <span style="color:#94A3B8">—</span>
@elseif ($io['file'])
    <span style="display:inline-block;padding:3px 9px;border-radius:999px;background:#DCFCE7;color:#166534;font-size:12px;font-weight:700;white-space:nowrap">Đọc từ tệp</span>
    <div style="margin-top:4px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:11.5px;color:#475569;line-height:1.5">
        @if ($io['input'])<div title="Tệp dữ liệu vào">{{ $io['input'] }}</div>@else<div style="color:#B45309" title="Chưa khai tệp vào — đang đọc bàn phím">vào: bàn phím</div>@endif
        @if ($io['output'])<div title="Tệp kết quả">{{ $io['output'] }}</div>@else<div style="color:#B45309" title="Chưa khai tệp ra — đang in màn hình">ra: màn hình</div>@endif
    </div>
@else
    <span style="display:inline-block;padding:3px 9px;border-radius:999px;background:#FEF3C7;color:#92400E;font-size:12px;font-weight:700;white-space:nowrap" title="Bài này đọc bàn phím / in màn hình — chưa khai tên tệp vào/ra">Bàn phím / màn hình</span>
    <div style="margin-top:4px;font-size:11.5px;color:#B45309">Chưa đọc từ tệp</div>
@endif
