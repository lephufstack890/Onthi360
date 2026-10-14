<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductReadService;
use App\Services\Student\MaterialReadService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SỬA 14/10 (khách: "quyền admin thì có thể đọc tất cả tài liệu, không cần phải mua") — màn đọc tài
 * liệu cho Admin/Super Admin. Dùng lại NGUYÊN VẸN bộ đọc của học sinh/giáo viên (MaterialReadService,
 * ProductReadService), chỉ khác $routePrefix = 'admin'. Quyền vẫn hỏi AccessGateService (đã cho
 * admin qua thẳng) ở MỌI route, kể cả route trả tệp — không tin route trước đã kiểm tra.
 */
class MaterialReadController extends Controller
{
    public function __construct(
        private MaterialReadService $materialRead,
        private ProductReadService $productRead,
    ) {}

    /** admin.materials.read — đọc PDF của 1 chương/phần (cùng view với học sinh/giáo viên). */
    public function read(Request $request, int $material): View
    {
        $user = $request->user();
        $materialModel = $this->materialRead->findWithProductOrFail($material);

        abort_unless($this->materialRead->decisionFor($user, $materialModel)->allowed, 403);
        abort_if(blank($materialModel->pdf_path), 404);

        return view('student.materials.read', $this->materialRead->buildReadData($user, $materialModel, 'admin'));
    }

    /** admin.materials.file — nội dung PDF cho bộ đọc (gọi bằng fetch()). */
    public function pdfFile(Request $request, int $material): StreamedResponse
    {
        $materialModel = $this->materialRead->findWithProductOrFail($material);
        abort_unless($this->materialRead->decisionFor($request->user(), $materialModel)->allowed, 403);

        return $this->materialRead->streamPdf($materialModel);
    }

    /** admin.products.read — đọc liền mạch cả tài liệu (tài liệu cũ chỉ có 1 tệp PDF, chưa chia chương). */
    public function product(Request $request, int $product): View
    {
        $user = $request->user();
        $productModel = $this->productRead->findOrFail($product);
        abort_unless($this->productRead->decisionFor($user, $productModel)->allowed, 403);

        return view('materials.product-read', $this->productRead->buildReadData($user, $productModel, 'admin'));
    }

    /** admin.products.read.file — PDF của 1 chương trong màn đọc liền mạch. */
    public function productFile(Request $request, int $product, int $material): StreamedResponse
    {
        $productModel = $this->productRead->findOrFail($product);
        abort_unless($this->productRead->decisionFor($request->user(), $productModel)->allowed, 403);

        return $this->productRead->streamPdf($this->productRead->resolvePartOrFail($productModel, $material));
    }

    /** admin.products.read.asset — audio/ảnh của học liệu (cột Học liệu ở màn đọc). */
    public function productAsset(Request $request, int $product, int $material, string $kind): StreamedResponse
    {
        $productModel = $this->productRead->findOrFail($product);
        abort_unless($this->productRead->decisionFor($request->user(), $productModel)->allowed, 403);

        return $this->productRead->streamAsset($this->productRead->resolveAssetOrFail($productModel, $material, $kind), $kind);
    }
}
