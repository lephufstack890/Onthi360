<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\ProductReadService;
use App\Services\Public\MaterialAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SỬA 29/9 — đọc LIỀN MẠCH cả sản phẩm (student.products.read): nối PDF của từng chương/phần/đề
 * thành một dải cuộn. Mỏng đúng như Material\Controller: mọi luật quyền nằm ở
 * App\Services\ProductReadService::decisionFor() (giao cho AccessGateService::canAccessProduct()
 * — qua được 1 trong 2 cửa "đã mua" HOẶC "giáo viên gắn sản phẩm vào lớp mình học" là đọc được).
 */
class ProductReadController extends Controller
{
    public function __construct(private ProductReadService $productRead, private MaterialAssignmentService $materialAssignments) {}

    /** Trang đọc — dải cuộn gồm PDF của mọi chương/phần/đề theo thứ tự. */
    public function read(Request $request, int $product): View|RedirectResponse
    {
        $user = $request->user();
        $productModel = $this->productRead->findOrFail($product);

        if (! $this->productRead->decisionFor($user, $productModel)->allowed) {
            // Dùng lại đúng trang "Bị khoá" đã có, không dựng màn từ chối riêng.
            return redirect()->route('access.checkout', $productModel->id);
        }

        // SỬA 9/10 — ghi nhận học sinh đã MỞ tài liệu được giao (trạng thái "Đã mở" ở trang Tài liệu).
        $this->materialAssignments->markOpened($user, $productModel->id);

        return view('materials.product-read', $this->productRead->buildReadData($user, $productModel, 'student'));
    }

    /**
     * Trả NỘI DUNG 1 tệp PDF cho bộ đọc (gọi bằng fetch() từ trang read, không phải link điều
     * hướng). Kiểm tra quyền LẠI TỪ ĐẦU ở đây — 2 request độc lập nhau.
     */
    public function file(Request $request, int $product, int $material): StreamedResponse
    {
        $user = $request->user();
        $productModel = $this->productRead->findOrFail($product);

        abort_unless($this->productRead->decisionFor($user, $productModel)->allowed, 403);

        return $this->productRead->streamPdf($this->productRead->resolvePartOrFail($productModel, $material));
    }

    /**
     * SỬA 29/9 (2) — trả tệp AUDIO/ẢNH của 1 học liệu cho cột "Học liệu" ở trang đọc. Tệp nằm ở
     * disk riêng tư 'local' nên trước đây không có đường nào xem được trong khu học sinh; kiểm
     * tra quyền y như route file() ở trên (mua HOẶC được cấp qua lớp).
     */
    public function asset(Request $request, int $product, int $material, string $kind): StreamedResponse
    {
        $user = $request->user();
        $productModel = $this->productRead->findOrFail($product);

        abort_unless($this->productRead->decisionFor($user, $productModel)->allowed, 403);

        return $this->productRead->streamAsset(
            $this->productRead->resolveAssetOrFail($productModel, $material, $kind),
            $kind
        );
    }
}
