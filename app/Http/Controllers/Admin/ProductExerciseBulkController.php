<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Question;
use App\Services\Admin\ContentService;
use App\Services\Admin\ProductExerciseBulkImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

    /**
     * SỬA 27/10 (khách: "thêm nút chọn tất cả rồi xoá tất cả") — xoá nhiều bài tập một lần. Mỗi bài xoá đúng như nút Xoá
     * từng bài (ContentService::productExerciseDestroy: xoá hẳn + xoá tệp). Chỉ nhận bài THUỘC tài liệu này — id lạ bị bỏ qua.
     */
    public function destroyMany(Request $request, Product $product, ContentService $content): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
        ], [], ['ids' => 'Bài tập']);

        @set_time_limit(300);

        $exercises = Question::query()->where('product_id', $product->id)->whereIn('id', $data['ids'])->get();
        foreach ($exercises as $exercise) {
            $content->productExerciseDestroy($exercise);
        }

        // SỬA 27/10 — bài DÙNG CHUNG được chọn:
        //   · mặc định chỉ GỠ khỏi tài liệu này (bài gốc ở tài liệu kia giữ nguyên);
        //   · có xoa_ca_bai_goc=1 (khách: "gỡ là gỡ ra bài dùng chung, còn xoá là xoá luôn") → XOÁ HẲN bài gốc + tệp, bài mất ở
        //     tài liệu gốc và mọi tài liệu đang dùng chung (liên kết tự mất theo). Giao diện đã hỏi xác nhận rõ ràng trước.
        $unlinked = 0;
        $deletedOriginals = 0;
        if (\App\Support\ProductExerciseLinks::supported()) {
            $linkedIds = \App\Models\ProductExerciseLink::query()->where('product_id', $product->id)->whereIn('question_id', $data['ids'])
                ->pluck('question_id')->map(fn ($id) => (int) $id)->all();
            if ($linkedIds !== [] && $request->boolean('xoa_ca_bai_goc')) {
                foreach (Question::query()->whereIn('id', $linkedIds)->get() as $original) {
                    $content->productExerciseDestroy($original);
                    $deletedOriginals++;
                }
            } elseif ($linkedIds !== []) {
                $unlinked = \App\Models\ProductExerciseLink::query()->where('product_id', $product->id)->whereIn('question_id', $linkedIds)->delete();
            }
        }

        $parts = array_filter([
            $exercises->count() > 0 ? 'xoá '.$exercises->count().' bài tập' : null,
            $deletedOriginals > 0 ? 'xoá hẳn '.$deletedOriginals.' bài dùng chung (cả bài gốc)' : null,
            $unlinked > 0 ? 'gỡ '.$unlinked.' bài dùng chung' : null,
        ]);

        return redirect()->route('admin.products.show', $product->id)
            ->with('status', 'exercises-deleted-many')
            ->with('statusMessage', $parts !== [] ? 'Đã '.implode(', ', $parts).'.' : 'Không có bài nào được xoá.');
    }

    /** Xong (hoặc dừng giữa chừng): dọn thư mục tạm. */
    public function finish(Product $product, string $token): JsonResponse
    {
        $this->bulk->finish($product, $token);

        return response()->json(['ok' => true]);
    }
}
