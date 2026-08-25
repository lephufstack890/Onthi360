<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Student\MaterialReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Đọc bài" (25/8) — student.materials.read/file. Chỉ 2 việc: hỏi
 * MaterialReadService xem có được đọc không, rồi render trang đọc hoặc trả file — mọi luật
 * "được đọc hay không" nằm hết ở MaterialReadService::decisionFor() (giao cho
 * App\Services\AccessGateService), controller không tự suy luận quyền.
 */
class MaterialController extends Controller
{
    public function __construct(private MaterialReadService $materialRead) {}

    /** student.materials.read — trang đọc PDF của 1 bài (chỉ mở khi đã có quyền + đã có PDF). */
    public function read(Request $request, int $material): View|RedirectResponse
    {
        $user = $request->user();
        $materialModel = $this->materialRead->findWithProductOrFail($material);

        $decision = $this->materialRead->decisionFor($user, $materialModel);
        if (! $decision->allowed) {
            // Dùng lại đúng trang "Bài khóa" đã có (access.blocked) — không dựng thêm màn hình
            // từ chối riêng, tránh 2 nơi hiển thị lý do khoá khác nhau.
            return redirect()->route('access.blocked', $materialModel->id);
        }

        // Bài chỉ làm mục lục/chương cha (chưa có PDF) thì không có gì để đọc — 404 thay vì
        // hiện trang đọc trống, TOC ở trang công khai cũng không link vào những bài này.
        abort_if(blank($materialModel->pdf_path), 404);

        return view('student.materials.read', $this->materialRead->buildReadData($user, $materialModel));
    }

    /**
     * student.materials.file — phục vụ NỘI DUNG PDF cho đúng bộ đọc ở trang read() (gọi qua
     * fetch() từ JS, không phải link điều hướng trực tiếp — xem read.blade.php). Kiểm tra lại
     * quyền TỪ ĐẦU ở đây, không tin route read() đã kiểm tra trước đó (2 request độc lập).
     */
    public function pdfFile(Request $request, int $material): StreamedResponse
    {
        $user = $request->user();
        $materialModel = $this->materialRead->findWithProductOrFail($material);

        abort_unless($this->materialRead->decisionFor($user, $materialModel)->allowed, 403);

        return $this->materialRead->streamPdf($materialModel);
    }
}
