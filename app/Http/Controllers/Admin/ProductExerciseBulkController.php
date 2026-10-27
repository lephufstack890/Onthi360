<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\ContentService;
use App\Services\Admin\ProductExerciseBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * SỬA 27/10 — NHẬP HÀNG LOẠT bài tập cho tài liệu (khối "Bài tập đính kèm", chế độ "Hàng loạt"). Trả JSON cho giao diện
 * (partials/exercise-bulk-adder). Logic ở App\Services\Admin\ProductExerciseBulkImportService. Cùng nhóm route admin
 * với ProductExerciseController (chỉ Admin / Super Admin).
 */
class ProductExerciseBulkController extends Controller
{
    public function __construct(private ProductExerciseBulkImportService $bulk) {}

    /** Bước 1: tải các ZIP lên, kiểm định dạng + trùng mã. Có bài trùng / lỗi → không nhập gì, trả danh sách. */
    public function prepare(Request $request, Product $product): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'mimes:zip', 'max:'.ContentService::maxQuestionZipKb()],
            'material_id' => ['nullable', 'integer'],
        ], [], ['files' => 'Tệp ZIP', 'files.*' => 'Tệp ZIP']);

        @set_time_limit(300);

        return response()->json($this->bulk->prepare(
            $product,
            $request->user(),
            $request->file('files', []),
            $request->filled('material_id') ? (int) $request->input('material_id') : null,
        ));
    }

    /** SỬA 27/10 — nhập từng bài: chọn file xong kiểm trùng mã (= tên file ZIP) ngay, trước khi tải lên. */
    public function checkCode(Request $request, Product $product): JsonResponse
    {
        $request->validate(['file' => ['required', 'string', 'max:255']]);

        return response()->json($this->bulk->checkFilename((string) $request->query('file')));
    }

    /** Bước 2: nhập 1 bài (trình duyệt gọi lần lượt từng bài để hiện tiến độ, tránh quá giờ máy chủ). */
    public function import(Request $request, Product $product, string $token, int $index): JsonResponse
    {
        @set_time_limit(120);

        try {
            return response()->json(['ok' => true] + $this->bulk->importOne($product, $request->user(), $token, $index));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'message' => (string) collect($e->errors())->flatten()->first()], 422);
        }
    }

    /** Xong (hoặc dừng giữa chừng): dọn thư mục tạm. */
    public function finish(Product $product, string $token): JsonResponse
    {
        $this->bulk->finish($product, $token);

        return response()->json(['ok' => true]);
    }
}
