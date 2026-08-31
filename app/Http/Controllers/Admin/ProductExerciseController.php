<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Question;
use App\Services\Admin\ContentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SỬA 31/8 ("ZIP bài tập" gắn vào sản phẩm) — CHỈ Admin quản lý (thêm/sửa/xoá) bài tập đính
 * kèm sản phẩm; đặt cùng nhóm middleware role:admin,super_admin với ProductController (xem
 * routes/web.php) — KHÔNG có route tương đương cho giáo viên. Toàn bộ logic thật nằm ở
 * App\Services\Admin\ContentService::productExercise*() — controller chỉ validate input +
 * gọi service + redirect, giống quy ước của ProductController/ContentController.
 */
class ProductExerciseController extends Controller
{
    public function __construct(private ContentService $contentService) {}

    /**
     * Chặn 1 admin sửa/xoá "nhầm" bài tập của sản phẩm KHÁC qua việc tự sửa URL — route
     * {product}/{exercise} không tự ràng buộc quan hệ, phải kiểm tra tay ở đây (cùng nguyên
     * tắc "2 request độc lập" như AccessService::downloadResource()).
     */
    private function assertBelongsToProduct(Product $product, Question $exercise): void
    {
        abort_unless($exercise->product_id === $product->id, 404);
    }

    /** admin.products.exercises.store — chọn ZIP xong, tạo bản nháp ngay rồi chuyển thẳng sang
     *  màn Sửa để admin xem lại/hoàn tất (xem ContentService::productExerciseStoreFromZipPackage()). */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'zip_package' => ['required', 'file', 'mimes:zip', 'max:'.ContentService::maxQuestionZipKb()],
        ]);

        $exercise = $this->contentService->productExerciseStoreFromZipPackage(
            $product,
            $request->user(),
            $request->file('zip_package'),
        );

        return redirect()
            ->route('admin.products.exercises.edit', [$product->id, $exercise->id])
            ->with('status', 'exercise-parsed');
    }

    public function edit(Product $product, Question $exercise): View
    {
        $this->assertBelongsToProduct($product, $exercise);

        return view('admin.products.exercises.edit', $this->contentService->productExerciseEditFormData($product, $exercise));
    }

    /** admin.products.exercises.update — bấm "Lưu bài tập": chỉ Tiêu đề/Điểm/Tag sửa được, xem
     *  lý do ở ContentService::productExerciseSave(). Lưu xong -> Published, quay về trang sản
     *  phẩm, nút "Thêm ZIP" lại bấm được ngay (không còn nháp nào treo). */
    public function update(Request $request, Product $product, Question $exercise): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $exercise);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'points' => ['nullable', 'integer', 'min:0'],
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer'],
            'new_tags' => ['nullable', 'string', 'max:500'],
        ]);

        $this->contentService->productExerciseSave($exercise, $data);

        return redirect()->route('admin.products.show', $product->id)->with('status', 'exercise-saved');
    }

    public function destroy(Product $product, Question $exercise): RedirectResponse
    {
        $this->assertBelongsToProduct($product, $exercise);

        $this->contentService->productExerciseDestroy($exercise);

        return redirect()->route('admin.products.show', $product->id)->with('status', 'exercise-deleted');
    }

    /** admin.products.exercises.attachment — đề bài/lời giải/code mẫu đọc từ gói ZIP, xem
     *  ContentService::questionAttachmentInfo() (dùng lại nguyên vẹn với Kho câu hỏi). */
    public function attachmentDownload(Product $product, Question $exercise, string $kind): StreamedResponse
    {
        $this->assertBelongsToProduct($product, $exercise);

        $info = $this->contentService->questionAttachmentInfo($exercise, $kind);

        return Storage::disk('local')->download($info['path'], $info['filename']);
    }

    /**
     * admin.products.exercises.asset (31/8 (2), "mở rộng ZIP bài tập") — audio/ảnh... đính kèm
     * câu hỏi (xem Question::findAsset()) — KHÁC attachmentDownload() ở trên (3 tên CỐ ĐỊNH,
     * luôn tải xuống) ở 2 điểm: (1) định danh bằng asset id (không giới hạn số lượng/loại
     * trước), (2) dùng response() (hiện INLINE, không ép tải xuống) để admin xem/nghe thử ngay
     * trong màn Sửa bài tập (audio player/ảnh), không phải để tải về máy.
     */
    public function assetDownload(Product $product, Question $exercise, string $asset)
    {
        $this->assertBelongsToProduct($product, $exercise);

        $info = $exercise->findAsset($asset);
        abort_if($info === null, 404);

        return Storage::disk('local')->response($info['path'], $info['filename']);
    }
}
