<?php

namespace App\Http\Controllers;

use App\Services\Contest\ContestScoreSheetPdf;
use App\Services\Contest\ContestScoreSheetService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SỬA 4/11 (khách: "in bảng điểm xoay tùm lum, muốn lấy PDF ngang") — nút "Tải PDF" trong hộp "In bảng điểm": hộp gửi lên đúng nội dung
 * đã dàn sẵn (hạng, giải, ghi chú… như bản in), máy chủ dựng FILE PDF khổ A4 ngang / dọc (ContestScoreSheetPdf) và trả về để tải.
 * Chỉ ban tổ chức của kỳ (kiểm như khi tải dữ liệu bảng điểm). Không ghi gì vào cơ sở dữ liệu.
 */
class ContestScoreSheetPdfController extends Controller
{
    public function __construct(private ContestScoreSheetService $sheets, private ContestScoreSheetPdf $pdf) {}

    public function __invoke(Request $request, int $competition, int $exam): Response
    {
        $this->sheets->authorizePrint($request->user(), $competition, $exam);

        $m = $request->validate([
            'orient' => ['required', 'in:landscape,portrait'],
            'heading' => ['nullable', 'string', 'max:300'],
            'subheading' => ['nullable', 'string', 'max:300'],
            'unit' => ['nullable', 'string', 'max:300'],
            'metaLeft' => ['nullable', 'string', 'max:300'],
            'dateLine' => ['nullable', 'string', 'max:100'],
            'footer' => ['nullable', 'string', 'max:300'],
            'logo' => ['nullable', 'boolean'],
            'filename' => ['nullable', 'string', 'max:150'],
            'head' => ['required', 'array', 'min:2', 'max:60'],
            'head.*' => ['nullable', 'string', 'max:200'],
            'kinds' => ['required', 'array', 'max:60'],
            'kinds.*' => ['required', 'in:rank,name,num,total,award,activity'],
            'widths' => ['required', 'array', 'max:60'],
            'widths.*' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'rows' => ['present', 'array', 'max:3000'],
            'rows.*.c' => ['required', 'array', 'max:60'],
            'rows.*.c.*' => ['nullable', 'string', 'max:600'],
            'rows.*.r' => ['nullable', 'string', 'max:300'],
            'notes' => ['nullable', 'array', 'max:50'],
            'notes.*' => ['nullable', 'string', 'max:20000'],
        ]);

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', \Illuminate\Support\Str::ascii((string) ($m['filename'] ?? ''))) ?: 'bang-tong-hop-diem';

        return response($this->pdf->render($m), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.trim($name, '-').'.pdf"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
