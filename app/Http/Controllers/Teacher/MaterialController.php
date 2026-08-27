<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Student\MaterialReadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SỬA 27/8 ("giáo viên mua tài liệu xong đọc bị 403" / "add học liệu vào lớp xong thì giáo
 * viên xem học liệu đó như nào") — teacher.materials.read/file. TRƯỚC ĐÂY hoàn toàn KHÔNG có
 * route nào cho giáo viên tự đọc học liệu (chỉ có student.materials.read/file, khoá bởi
 * middleware role:student) — giáo viên bấm vào link đọc bài (từ trang công khai /tai-lieu/...
 * hoặc từ tab "Học liệu" ở Chi tiết lớp) đều dính 403 hoặc không có gì để bấm.
 *
 * CỐ Ý tái dùng NGUYÊN VẸN App\Services\Student\MaterialReadService — logic "được đọc hay
 * không" vẫn giao hẳn 1 nơi duy nhất cho App\Services\AccessGateService::canAccessMaterial()
 * (đã sửa để nhận cả quyền scope=TeacherTeaching, xem AccessGateService::
 * hasActivePersonalAccess()), không viết lại luật riêng cho giáo viên ở đây — tránh 2 nơi tính
 * quyền lệch nhau như docblock của AccessGateService đã nhấn mạnh. Chỉ khác Student\
 * MaterialController ở $routePrefix truyền vào buildReadData() để trang đọc dùng đúng
 * layout/route theo vai trò giáo viên (xem MaterialReadService::buildReadData()).
 */
class MaterialController extends Controller
{
    public function __construct(private MaterialReadService $materialRead) {}

    /** teacher.materials.read — trang đọc PDF của 1 bài (chỉ mở khi đã có quyền + đã có PDF). */
    public function read(Request $request, int $material): View|RedirectResponse
    {
        $user = $request->user();
        $materialModel = $this->materialRead->findWithProductOrFail($material);

        $decision = $this->materialRead->decisionFor($user, $materialModel);
        if (! $decision->allowed) {
            // Dùng lại đúng trang "Bài khóa" đã có (access.blocked, route dùng chung mọi vai
            // trò) — không dựng thêm màn hình từ chối riêng cho giáo viên.
            return redirect()->route('access.blocked', $materialModel->id);
        }

        abort_if(blank($materialModel->pdf_path), 404);

        // Dùng chung view student.materials.read — $layoutView/$readRoute do buildReadData()
        // truyền vào đã tự đổi sang 'layouts.teacher'/'teacher.materials.read' (xem view đó).
        return view('student.materials.read', $this->materialRead->buildReadData($user, $materialModel, 'teacher'));
    }

    /**
     * teacher.materials.file — phục vụ nội dung PDF cho đúng bộ đọc ở read() (gọi qua fetch()
     * từ JS). Kiểm tra lại quyền TỪ ĐẦU ở đây, không tin route read() đã kiểm tra trước đó.
     */
    public function pdfFile(Request $request, int $material): StreamedResponse
    {
        $user = $request->user();
        $materialModel = $this->materialRead->findWithProductOrFail($material);

        abort_unless($this->materialRead->decisionFor($user, $materialModel)->allowed, 403);

        return $this->materialRead->streamPdf($materialModel);
    }
}
