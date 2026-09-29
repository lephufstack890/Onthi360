<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\Product;
use App\Services\Admin\ContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 4/9 (khách yêu cầu "Chương/Phần/Đề" — "nếu loại sách thì thêm chương, loại chuyên đề
 * là thêm phần, loại bộ đề thì thêm đề, field chỉ cần title") — CRUD gọn cho mục lục chương/
 * phần/đề của 1 sản phẩm, tái dùng Material (type=chapter) có sẵn qua ContentService::
 * productChapter*(). CHỈ Admin/Super Admin quản lý (cùng nhóm middleware role:admin,
 * super_admin với ProductController/ProductExerciseController, xem routes/web.php).
 */
class ProductChapterController extends Controller
{
    public function __construct(private ContentService $contentService) {}

    /** Chặn admin sửa/xoá "nhầm" chương của sản phẩm KHÁC qua việc tự sửa URL — cùng nguyên tắc với ProductExerciseController::assertBelongsToProduct(). */
    private function assertBelongsToProduct(Product $product, Material $chapter): void
    {
        abort_unless($chapter->product_id === $product->id && $chapter->type === 'chapter', 404);
    }

    /**
     * SỬA 29/9 (khách chốt: "bỏ file pdf sách đi, chỗ chương mỗi chương là thêm từng file pdf")
     * — thêm ô tệp PDF cho chính chương/phần/đề. Vẫn TÙY CHỌN: tạo mục lục rỗng rồi tải tệp
     * sau vẫn được. Giới hạn dung lượng dùng chung ContentService::maxPdfKb() như mọi ô PDF khác.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.ContentService::maxPdfKb()],
        ], [], ['pdf' => 'Tệp PDF nội dung']);

        try {
            $this->contentService->productChapterStore($product, $data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.products.show', $product->id)->with('status', 'chapter-created');
    }

    public function update(Request $request, Product $product, Material $chapter): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $chapter);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'order' => ['nullable', 'integer', 'min:0'],
            // SỬA 29/9 — thay tệp PDF của chương, hoặc tích 'remove_pdf' để bỏ tệp hiện có.
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.ContentService::maxPdfKb()],
            'remove_pdf' => ['nullable', 'boolean'],
        ], [], ['pdf' => 'Tệp PDF nội dung']);

        try {
            $this->contentService->productChapterUpdate($chapter, $data);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.products.show', $product->id)->with('status', 'chapter-updated');
    }

    public function destroy(Product $product, Material $chapter): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $chapter);

        try {
            $this->contentService->productChapterDestroy($chapter);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('admin.products.show', $product->id)->with('status', 'chapter-deleted');
    }
}
