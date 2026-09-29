<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\ProductReadService;
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
    public function __construct(private ProductReadService $productRead) {}

    /** Trang đọc — dải cuộn gồm PDF của mọi chương/phần/đề theo thứ tự. */
    public function read(Request $request, int $product): View|RedirectResponse
    {
        $user = $request->user();
        $productModel = $this->productRead->findOrFail($product);

        if (! $this->productRead->decisionFor($user, $productModel)->allowed) {
            // Dùng lại đúng trang "Bị khoá" đã có, không dựng màn từ chối riêng.
            return redirect()->route('access.checkout', $productModel->id);
        }

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
}
